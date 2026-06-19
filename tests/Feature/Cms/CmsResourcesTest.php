<?php

namespace Tests\Feature\Cms;

use App\Enums\BannerType;
use App\Enums\NavigationLocation;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Navigations\Pages\EditNavigation;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Banner;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CmsResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_view_pages_list(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ListPages::class)->assertOk();
    }

    public function test_admin_can_create_page(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'O nás',
                'slug' => 'o-nas',
                'status' => 'published',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pages', ['slug' => 'o-nas', 'title' => 'O nás']);
    }

    public function test_page_requires_title_and_slug(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => null, 'slug' => null])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required', 'slug' => 'required']);
    }

    public function test_system_page_slug_field_is_disabled(): void
    {
        $page = Page::factory()->system('home')->create(['slug' => '/']);

        $this->actingAs($this->admin());

        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->assertFormFieldIsDisabled('slug');
    }

    public function test_edit_page_has_visit_action(): void
    {
        $page = Page::factory()->create(['slug' => 'o-nas']);

        $this->actingAs($this->admin());

        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->assertActionExists('visit');
    }

    public function test_admin_can_create_topbar_banner(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateBanner::class)
            ->fillForm([
                'name' => 'Oznámení',
                'type' => BannerType::Topbar->value,
                'placement' => 'all',
                'is_active' => true,
                'content' => [
                    'text' => 'Vítejte',
                    'cta_text' => 'Více',
                    'cta_url' => 'https://example.com',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::first();

        $this->assertNotNull($banner);
        $this->assertSame(BannerType::Topbar, $banner->type);
        $this->assertSame('Vítejte', $banner->content['text']);
    }

    public function test_admin_can_save_navigation_with_nested_children(): void
    {
        $nav = Navigation::create(['location' => NavigationLocation::Header->value]);

        $this->actingAs($this->admin());

        Livewire::test(EditNavigation::class, ['record' => $nav->getKey()])
            ->fillForm([
                'items' => [
                    [
                        'label' => 'Služby',
                        'link_type' => 'custom',
                        'url' => '/sluzby',
                        'target' => '_self',
                        'children' => [
                            [
                                'label' => 'Fyzioterapie',
                                'link_type' => 'custom',
                                'url' => '/fyzioterapie',
                                'target' => '_self',
                            ],
                        ],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $parent = NavigationItem::where('label', 'Služby')->first();
        $child = NavigationItem::where('label', 'Fyzioterapie')->first();

        $this->assertNotNull($parent);
        $this->assertNotNull($child);
        $this->assertSame($nav->getKey(), $parent->navigation_id);
        $this->assertSame($parent->getKey(), $child->parent_id);
    }
}
