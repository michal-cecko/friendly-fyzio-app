<?php

namespace Tests\Feature\Suggestions;

use App\Enums\ReservationStatus;
use App\Models\ClientNote;
use App\Models\Reservation;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Reservations\ReservationMetrics;
use App\Support\Suggestions\SuggestionFinder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The design brief's "Pending Notes", delivered as a suggestion: visits that
 * happened and were never written up.
 */
class MissingVisitNoteRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pastVisit(array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'reservation_date' => today()->subDays(3),
            'status' => ReservationStatus::Confirmed,
            'imported_at' => null,
            ...$attributes,
        ]);
    }

    private function card(): ?array
    {
        return collect(SuggestionFinder::all())->firstWhere('type', 'missing_visit_note');
    }

    public function test_a_past_visit_without_a_note_raises_the_card(): void
    {
        $this->pastVisit();

        $this->actingAs(User::factory()->admin()->create());

        $card = $this->card();

        $this->assertNotNull($card);
        $this->assertStringContainsString('návštěv: 1', $card['detail']);
    }

    public function test_a_written_up_visit_is_not_pending(): void
    {
        $visit = $this->pastVisit();
        ClientNote::factory()->create([
            'client_id' => $visit->client_id,
            'reservation_id' => $visit->id,
        ]);

        $this->actingAs(User::factory()->admin()->create());

        $this->assertNull($this->card());
    }

    public function test_cancelled_future_and_imported_visits_are_left_alone(): void
    {
        // Nothing happened, so there is nothing to write up.
        $this->pastVisit(['status' => ReservationStatus::Cancelled]);
        // Not yet happened.
        $this->pastVisit(['reservation_date' => today()->addDay()]);
        // The Ergobody history has no notes by construction.
        $this->pastVisit(['imported_at' => now()]);
        // Too old to reconstruct from memory.
        $this->pastVisit(['reservation_date' => today()->subDays(120)]);

        $this->actingAs(User::factory()->admin()->create());

        $this->assertNull($this->card());
    }

    public function test_a_therapist_is_only_nudged_about_their_own_visits(): void
    {
        $therapist = User::factory()->therapist()->create();
        $profile = StaffProfile::query()->where('user_id', $therapist->id)->firstOrFail();

        $this->pastVisit(['therapist_id' => $profile->id]);
        $this->pastVisit();
        $this->pastVisit();

        $this->actingAs($therapist);

        $card = $this->card();

        $this->assertNotNull($card);
        $this->assertStringContainsString('návštěv: 1', $card['detail']);
    }

    /**
     * The card links to the `missing_note` table filter, so both sides must be
     * driven by the same scope — otherwise the count and the list it opens
     * disagree.
     */
    public function test_the_card_count_matches_the_filter_it_links_to(): void
    {
        $this->pastVisit();
        $this->pastVisit();
        $written = $this->pastVisit();
        ClientNote::factory()->create([
            'client_id' => $written->client_id,
            'reservation_id' => $written->id,
        ]);

        $this->actingAs(User::factory()->admin()->create());

        $this->assertSame(2, ReservationMetrics::scopeMissingVisitNote(Reservation::query())->count());
        $this->assertStringContainsString('návštěv: 2', $this->card()['detail']);
        $this->assertStringContainsString('missing_note', $this->card()['url']);
    }
}
