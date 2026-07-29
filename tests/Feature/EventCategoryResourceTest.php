<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\CreateEventCategory;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\EditEventCategory;
use App\Models\EventCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The self-cancel window lives on the category because a workshop needs more
 * notice than a jednorázová lekce — see {@see EventCategory::cancelBeforeHours()}.
 */
class EventCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_set_the_self_cancel_window_on_a_category(): void
    {
        Livewire::test(CreateEventCategory::class)
            ->fillForm([
                'name' => 'Celodenní workshopy',
                'slug' => 'celodenni-workshopy',
                'cancel_before_hours' => 168,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(168, EventCategory::query()->where('slug', 'celodenni-workshopy')->sole()->cancelBeforeHours());
    }

    public function test_an_empty_window_falls_back_to_the_clinic_wide_setting(): void
    {
        $category = EventCategory::factory()->create(['cancel_before_hours' => 168]);

        Livewire::test(EditEventCategory::class, ['record' => $category->getKey()])
            ->fillForm(['cancel_before_hours' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        // The seeded default, not the 168 that was there a moment ago.
        $this->assertSame(24, $category->fresh()->cancelBeforeHours());
    }
}
