<?php

namespace Tests\Feature\Suggestions;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\ReservationStatus;
use App\Enums\WaitlistPromotionMode;
use App\Filament\Pages\Suggestions;
use App\Filament\Widgets\SuggestionsWidget;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Reservation;
use App\Models\SuggestionDismissal;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\Suggestions\SuggestionFinder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The two things you can do to a card without leaving the page: carry it out,
 * or put it away.
 */
class SuggestionActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Cache::flush();

        $this->actingAs(User::factory()->admin()->create());
    }

    private function seriesWithFreeSpotAndWaiter(): CourseSeries
    {
        $series = CourseSeries::factory()->create([
            'capacity' => 5,
            'start_date' => today()->subWeek(),
            'end_date' => today()->addMonth(),
            'status' => CourseSeriesStatus::Open,
            'waitlist_promotion_mode' => WaitlistPromotionMode::Manual,
        ]);

        CourseEnrollment::factory()->create([
            'series_id' => $series->id,
            'status' => CourseEnrollmentStatus::Active,
        ]);

        WaitlistEntry::factory()->forWaitlistable($series)->create(['notified_at' => null]);

        return $series;
    }

    private function unresolvedDoctorNote(): Reservation
    {
        // Dated well ahead so it never also reads as an unsettled past visit,
        // even in the tests that travel forward.
        return Reservation::factory()->create([
            'reservation_date' => today()->addDays(30),
            'status' => ReservationStatus::Confirmed,
            'doctor_note_requested_at' => now()->subDay(),
            'doctor_note_resolved_at' => null,
        ]);
    }

    public function test_resolving_a_waitlist_card_invites_the_waiting_clients(): void
    {
        $series = $this->seriesWithFreeSpotAndWaiter();

        Livewire::test(SuggestionsWidget::class)
            ->callAction('resolveSuggestion', arguments: [
                'type' => 'waitlist_offer_series',
                'id' => $series->id,
            ])
            ->assertNotified();

        $this->assertNotNull($series->fresh()->waitlist_invited_until, 'The invite round must be stamped so the spot is fenced off.');
        $this->assertNotNull(WaitlistEntry::query()->first()->notified_at, 'Everyone waiting has been e-mailed.');

        // The list is memoised per request; the action flushes it, so the card
        // is gone from the very same render.
        $this->assertSame([], SuggestionFinder::all());
    }

    public function test_a_card_that_cannot_be_resolved_in_one_click_says_so_instead_of_failing(): void
    {
        $this->unresolvedDoctorNote();

        Livewire::test(SuggestionsWidget::class)
            ->callAction('resolveSuggestion', arguments: [
                'type' => 'doctor_note_pending',
                'id' => null,
            ])
            ->assertNotified();

        $this->assertCount(1, SuggestionFinder::all());
    }

    public function test_dismissing_a_card_hides_it_for_the_whole_team(): void
    {
        $this->unresolvedDoctorNote();
        $card = SuggestionFinder::all()[0];

        Livewire::test(SuggestionsWidget::class)
            ->callAction('dismissSuggestion', arguments: [
                'key' => $card['key'],
                'type' => $card['type'],
                'fingerprint' => $card['fingerprint'],
                'snooze' => $card['snoozeOnDismiss'],
            ])
            ->assertNotified();

        $this->assertDatabaseHas('suggestion_dismissals', ['key' => 'doctor_note_pending']);
        $this->assertSame([], SuggestionFinder::all());
        $this->assertSame(0, SuggestionFinder::count(), 'The badge must not count what the page no longer shows.');

        // Another admin, another request: the card stays put away.
        $this->actingAs(User::factory()->admin()->create());
        Once::flush();
        $this->assertSame([], SuggestionFinder::all());
    }

    public function test_an_aggregate_card_comes_back_when_its_snooze_runs_out(): void
    {
        $this->unresolvedDoctorNote();
        $card = SuggestionFinder::all()[0];

        Livewire::test(SuggestionsWidget::class)->callAction('dismissSuggestion', arguments: [
            'key' => $card['key'],
            'type' => $card['type'],
            'fingerprint' => $card['fingerprint'],
            'snooze' => true,
        ]);

        $this->travel(8)->days();
        Once::flush();

        $this->assertCount(1, SuggestionFinder::all(), 'A week later the decision is still not made, so it is worth saying again.');
    }

    public function test_a_per_record_card_comes_back_as_soon_as_the_situation_changes(): void
    {
        $series = $this->seriesWithFreeSpotAndWaiter();
        $card = SuggestionFinder::all()[0];

        Livewire::test(SuggestionsWidget::class)->callAction('dismissSuggestion', arguments: [
            'key' => $card['key'],
            'type' => $card['type'],
            'fingerprint' => $card['fingerprint'],
            'snooze' => false,
        ]);

        Once::flush();
        $this->assertSame([], SuggestionFinder::all());

        // A second client joins the queue: different facts, so the card is not
        // the one that was put away.
        WaitlistEntry::factory()->forWaitlistable($series)->create(['notified_at' => null]);
        Once::flush();

        $this->assertCount(1, SuggestionFinder::all());
    }

    public function test_hidden_cards_are_offered_back_on_the_page(): void
    {
        $this->unresolvedDoctorNote();
        $card = SuggestionFinder::all()[0];

        SuggestionDismissal::query()->create([
            'key' => $card['key'],
            'type' => $card['type'],
            'fingerprint' => $card['fingerprint'],
            'snoozed_until' => now()->addWeek(),
        ]);
        Once::flush();

        $this->assertCount(1, SuggestionFinder::hidden());

        Livewire::test(Suggestions::class)
            ->callAction('restoreSuggestion', arguments: ['key' => $card['key']])
            ->assertNotified();

        $this->assertDatabaseCount('suggestion_dismissals', 0);
        $this->assertCount(1, SuggestionFinder::all());
    }

    public function test_an_expired_snooze_is_pruned(): void
    {
        SuggestionDismissal::factory()->expired()->create(['key' => 'reviews_hidden', 'type' => 'reviews_hidden']);
        SuggestionDismissal::factory()->snoozed()->create(['key' => 'payments_past_due', 'type' => 'payments_past_due']);

        $this->assertSame(1, SuggestionDismissal::prune());
        $this->assertDatabaseCount('suggestion_dismissals', 1);
    }
}
