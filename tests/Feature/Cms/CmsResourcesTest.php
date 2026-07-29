<?php

namespace Tests\Feature\Cms;

use App\Enums\BannerType;
use App\Enums\NavigationLocation;
use App\Enums\ServiceVisibility;
use App\Filament\Clusters\Obsah\Resources\Banners\Pages\CreateBanner;
use App\Filament\Clusters\Obsah\Resources\Navigations\Pages\EditNavigation;
use App\Filament\Clusters\Obsah\Resources\Pages\Pages\CreatePage;
use App\Filament\Clusters\Obsah\Resources\Pages\Pages\EditPage;
use App\Filament\Clusters\Obsah\Resources\Pages\Pages\ListPages;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages\EditServiceCategory;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\EditService;
use App\Models\Banner;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
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
                'published_at' => now(),
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

    public function test_edit_page_form_renders_with_pageable_owner_selector(): void
    {
        $category = ServiceCategory::factory()->create();
        $page = Page::factory()->for($category, 'pageable')->create(['slug' => 'o-nas']);

        $this->actingAs($this->admin());

        // Renders cleanly with the MorphToSelect owner picker bound to an attached page.
        Livewire::test(EditPage::class, ['record' => $page->getKey()])->assertOk();
    }

    public function test_admin_can_duplicate_a_page_as_a_draft(): void
    {
        $category = ServiceCategory::factory()->create();
        $source = Page::factory()->for($category, 'pageable')->system('cenik')->create([
            'title' => 'Ceník',
            'slug' => 'cenik',
            'published_at' => now(),
            'content' => [
                ['type' => 'masonBrick', 'attrs' => ['id' => 'hero', 'config' => ['title' => 'Ceník']]],
            ],
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListPages::class)
            ->callAction(TestAction::make('replicate')->table($source), [
                'title' => 'Ceník masáží',
                'slug' => 'cenik-masazi',
            ])
            ->assertHasNoActionErrors();

        $copy = Page::where('slug', 'cenik-masazi')->first();

        $this->assertNotNull($copy);
        $this->assertSame($source->content, $copy->content);
        // A copy is never the system page, never owns the original's public URL,
        // and always starts as a draft.
        $this->assertNull($copy->system_key);
        $this->assertFalse($copy->is_system);
        $this->assertNull($copy->pageable_type);
        $this->assertNull($copy->pageable_id);
        $this->assertNull($copy->published_at);
    }

    public function test_duplicate_rejects_a_slug_that_is_already_taken(): void
    {
        $source = Page::factory()->create(['slug' => 'o-nas']);
        Page::factory()->create(['slug' => 'kontakt']);

        $this->actingAs($this->admin());

        Livewire::test(ListPages::class)
            ->callAction(TestAction::make('replicate')->table($source), [
                'title' => 'Kontakt',
                'slug' => 'kontakt',
            ])
            ->assertHasActionErrors(['slug']);

        $this->assertSame(2, Page::count());
    }

    public function test_admin_can_copy_brick_content_from_another_page(): void
    {
        $source = Page::factory()->create([
            'title' => 'Lymfatické masáže',
            'slug' => 'lymfaticke-masaze',
            'content' => [
                ['type' => 'masonBrick', 'attrs' => ['id' => 'hero', 'config' => ['title' => 'Lymfatické masáže']]],
            ],
        ]);
        $target = Page::factory()->create(['slug' => 'klasicke-masaze', 'content' => []]);

        $this->actingAs($this->admin());

        Livewire::test(EditPage::class, ['record' => $target->getKey()])
            ->callAction(TestAction::make('copyPageContent')->schemaComponent('content'), [
                'source_page_id' => $source->getKey(),
                'mode' => 'replace',
            ])
            ->assertHasNoActionErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($source->content, $target->refresh()->content);
        // The source is only read from, never touched.
        $this->assertSame('Lymfatické masáže', $source->refresh()->title);
    }

    public function test_copying_content_can_append_instead_of_replacing(): void
    {
        $sourceBrick = ['type' => 'masonBrick', 'attrs' => ['id' => 'cta-banner', 'config' => ['title' => 'Objednejte se']]];
        $ownBrick = ['type' => 'masonBrick', 'attrs' => ['id' => 'hero', 'config' => ['title' => 'Klasické masáže']]];

        $source = Page::factory()->create(['slug' => 'lymfaticke-masaze', 'content' => [$sourceBrick]]);
        $target = Page::factory()->create(['slug' => 'klasicke-masaze', 'content' => [$ownBrick]]);

        $this->actingAs($this->admin());

        Livewire::test(EditPage::class, ['record' => $target->getKey()])
            ->callAction(TestAction::make('copyPageContent')->schemaComponent('content'), [
                'source_page_id' => $source->getKey(),
                'mode' => 'append',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$ownBrick, $sourceBrick], $target->refresh()->content);
    }

    public function test_service_custom_page_can_be_seeded_from_another_service_page(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'relaxace']);
        $lymph = Service::factory()->create(['category_id' => $category->id, 'slug' => 'lymfaticke-masaze']);
        $classic = Service::factory()->create([
            'category_id' => $category->id,
            'slug' => 'klasicka-masaz',
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);

        $brick = ['type' => 'masonBrick', 'attrs' => ['id' => 'hero', 'config' => ['title' => 'Masáže']]];
        Page::factory()->for($lymph, 'pageable')->create(['slug' => 'lymfaticke-masaze-vlastni-stranka', 'content' => [$brick]]);

        $this->actingAs($this->admin());

        // The Mason editor sits inside the customPage relationship section, so this
        // also proves the action writes to the nested state path.
        Livewire::test(EditService::class, ['record' => $classic->getKey()])
            ->callAction(TestAction::make('copyPageContent')->schemaComponent('customPage.content'), [
                'source_page_id' => $lymph->customPage->getKey(),
                'mode' => 'replace',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$brick], $classic->refresh()->customPage?->content);
    }

    public function test_admin_can_save_category_public_page_fields(): void
    {
        $category = ServiceCategory::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(EditServiceCategory::class, ['record' => $category->getKey()])
            ->fillForm(['description' => 'Krásný popis kategorie.'])
            ->call('save')
            ->assertHasNoFormErrors();

        // The perex is now a rich-text editor, so plain input is stored as HTML.
        $this->assertDatabaseHas('service_categories', [
            'id' => $category->id,
            'description' => '<p>Krásný popis kategorie.</p>',
        ]);
    }

    public function test_admin_can_author_inline_custom_page_on_category(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'relaxace']);

        $this->actingAs($this->admin());

        Livewire::test(EditServiceCategory::class, ['record' => $category->getKey()])
            ->fillForm([
                'customPage' => [
                    'published_at' => now(),
                    'content' => [
                        ['type' => 'masonBrick', 'attrs' => ['id' => 'hero', 'config' => ['title' => 'Vlastní vzhled']]],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $page = $category->refresh()->customPage;

        $this->assertNotNull($page);
        $this->assertSame($category->id, $page->pageable_id);
        $this->assertSame(ServiceCategory::class, $page->pageable_type);

        $this->get('/sluzby/relaxace')->assertOk()->assertSee('Vlastní vzhled');
    }

    public function test_empty_inline_custom_page_creates_no_page(): void
    {
        $category = ServiceCategory::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(EditServiceCategory::class, ['record' => $category->getKey()])
            ->fillForm(['description' => 'Jen výchozí rozvržení.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($category->refresh()->customPage);
        $this->assertSame(0, Page::count());
    }

    public function test_category_edit_has_open_public_page_action(): void
    {
        $category = ServiceCategory::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(EditServiceCategory::class, ['record' => $category->getKey()])
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
