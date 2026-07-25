<?php

namespace Tests\Feature;

use App\Enums\CourseSeriesVisibility;
use App\Enums\OfferVisibility;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\ViewOneOffEvent;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Both offer detail pages expose the hidden sign-up link and the invitation
 * action, which previously lived only on their edit pages. Both stay gated to
 * Private offers.
 */
class OfferInvitationActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_series_detail_offers_the_link_and_the_invitation(): void
    {
        $series = CourseSeries::factory()->create(['visibility' => CourseSeriesVisibility::Private]);

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->assertActionExists('presaleLink')
            ->assertActionExists('sendInvitation');
    }

    public function test_event_detail_offers_the_link_and_the_invitation(): void
    {
        $event = OneOffEvent::factory()->create([
            'visibility' => OfferVisibility::Private,
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(ViewOneOffEvent::class, ['record' => $event->getKey()])
            ->assertActionExists('presaleLink')
            ->assertActionExists('sendInvitation')
            ->assertActionExists('delete')
            ->assertActionExists('activityLog');
    }

    /**
     * A public offer takes sign-ups from anyone, so neither the hidden link nor
     * the invitation it carries has anything to add.
     */
    public function test_link_and_invitation_stay_hidden_on_public_offers(): void
    {
        $series = CourseSeries::factory()->create(['visibility' => CourseSeriesVisibility::Public]);

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->assertActionHidden('presaleLink')
            ->assertActionHidden('sendInvitation');

        $event = OneOffEvent::factory()->create([
            'visibility' => OfferVisibility::Public,
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(ViewOneOffEvent::class, ['record' => $event->getKey()])
            ->assertActionHidden('presaleLink')
            ->assertActionHidden('sendInvitation');
    }

    /**
     * The link text is copyable on click, but the modal also carries a labelled
     * copy button so the affordance is obvious.
     */
    public function test_presale_modal_carries_a_copy_button(): void
    {
        $series = CourseSeries::factory()->create(['visibility' => CourseSeriesVisibility::Private]);

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->mountAction('presaleLink')
            ->assertActionExists(TestAction::make('copyPresaleLink'));
    }
}
