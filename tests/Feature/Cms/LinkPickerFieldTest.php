<?php

namespace Tests\Feature\Cms;

use App\Filament\Clusters\Obsah\Resources\Navigations\Pages\EditNavigation;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\StaffProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The link picker splits its internal target into a UI-only "kind" select plus the
 * stored link_ref. These cover the two things that split can break: hydrating the
 * right kind for already-stored links, and never writing the helper field to the
 * NavigationItem model (which has no such column).
 */
class LinkPickerFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_stored_reference_hydrates_its_kind_and_saves_without_writing_the_helper_field(): void
    {
        $therapist = StaffProfile::factory()->published()->create();
        $navigation = Navigation::create(['location' => 'header']);
        $item = NavigationItem::create([
            'navigation_id' => $navigation->id,
            'label' => 'Náš tým',
            'link_type' => 'internal',
            'link_ref' => "therapist:{$therapist->id}",
            'display_order' => 0,
        ]);

        $component = Livewire::test(EditNavigation::class, ['record' => $navigation->getKey()]);

        $items = $component->get('data.items');
        $state = reset($items);

        $this->assertSame('therapist', $state['link_kind']);
        $this->assertSame("therapist:{$therapist->id}", $state['link_ref']);

        $component->call('save')->assertHasNoFormErrors();

        $item->refresh();
        $this->assertSame("therapist:{$therapist->id}", $item->link_ref);
        $this->assertSame($therapist->permalink, $item->resolvedUrl());
    }

    public function test_a_legacy_page_id_hydrates_as_a_page_reference(): void
    {
        $page = Page::factory()->create();
        $navigation = Navigation::create(['location' => 'header']);
        NavigationItem::create([
            'navigation_id' => $navigation->id,
            'label' => 'O nás',
            'link_type' => 'page',
            'page_id' => $page->id,
            'display_order' => 0,
        ]);

        $items = Livewire::test(EditNavigation::class, ['record' => $navigation->getKey()])
            ->get('data.items');
        $state = reset($items);

        $this->assertSame('internal', $state['link_type']);
        $this->assertSame('page', $state['link_kind']);
        $this->assertSame("page:{$page->id}", $state['link_ref']);
    }

    public function test_a_custom_url_item_defaults_to_the_page_kind(): void
    {
        $navigation = Navigation::create(['location' => 'header']);
        NavigationItem::create([
            'navigation_id' => $navigation->id,
            'label' => 'Rezervace',
            'link_type' => 'custom',
            'url' => '/rezervace',
            'display_order' => 0,
        ]);

        $component = Livewire::test(EditNavigation::class, ['record' => $navigation->getKey()]);

        $items = $component->get('data.items');
        $state = reset($items);

        $this->assertSame('custom', $state['link_type']);
        $this->assertSame('page', $state['link_kind']);

        $component->call('save')->assertHasNoFormErrors();

        $this->assertSame('/rezervace', $navigation->items()->sole()->url);
    }
}
