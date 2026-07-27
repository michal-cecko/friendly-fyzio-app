<?php

namespace Tests\Feature\Suggestions;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\WaitlistPromotionMode;
use App\Filament\Pages\Problems;
use App\Filament\Pages\Suggestions;
use App\Filament\Widgets\ProblemsWidget;
use App\Filament\Widgets\SuggestionsWidget;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\Suggestions\SuggestionFinder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A pure therapist gets the same two surfaces as an admin, narrowed to their own
 * work — the rule being that a card must never point at a record its owner
 * cannot open.
 */
class TherapistScopedSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Cache::flush();
    }

    private function therapist(): User
    {
        return User::factory()->therapist()->create();
    }

    private function seriesWithFreeSpotAndWaiter(?User $instructor = null): CourseSeries
    {
        $course = Course::factory()->create($instructor === null ? [] : ['instructor_id' => $instructor->id]);

        $series = CourseSeries::factory()->create([
            'course_id' => $course->id,
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

    public function test_a_therapist_sees_both_surfaces_and_a_customer_does_not(): void
    {
        $this->actingAs($this->therapist());
        $this->assertTrue(SuggestionsWidget::canView());
        $this->assertTrue(ProblemsWidget::canView());
        $this->assertTrue(Suggestions::canAccess());
        $this->assertTrue(Problems::canAccess());

        $this->actingAs(User::factory()->customer()->create());
        $this->assertFalse(SuggestionsWidget::canView());
        $this->assertFalse(ProblemsWidget::canView());
        $this->assertFalse(Suggestions::canAccess());
        $this->assertFalse(Problems::canAccess());
    }

    public function test_a_therapist_is_only_told_about_their_own_courses(): void
    {
        $therapist = $this->therapist();
        $mine = $this->seriesWithFreeSpotAndWaiter($therapist);
        $this->seriesWithFreeSpotAndWaiter();

        $this->actingAs($therapist);

        $suggestions = SuggestionFinder::all();

        $this->assertCount(1, $suggestions);
        $this->assertSame($mine->id, $suggestions[0]['id']);
    }

    public function test_a_therapist_is_only_told_about_their_own_reservations(): void
    {
        $therapist = $this->therapist();
        $profile = StaffProfile::query()->where('user_id', $therapist->id)->firstOrFail();

        Reservation::factory()->create([
            'therapist_id' => $profile->id,
            'reservation_date' => today()->addDay(),
            'doctor_note_requested_at' => now()->subDay(),
            'doctor_note_resolved_at' => null,
        ]);
        Reservation::factory()->count(2)->create([
            'reservation_date' => today()->addDay(),
            'doctor_note_requested_at' => now()->subDay(),
            'doctor_note_resolved_at' => null,
        ]);

        $this->actingAs($therapist);

        $card = collect(SuggestionFinder::all())->firstWhere('type', 'doctor_note_pending');

        $this->assertNotNull($card);
        $this->assertStringContainsString('doporučení: 1', $card['detail'], 'Only their own client counts.');
    }

    public function test_office_work_stays_with_the_office(): void
    {
        Payment::factory()->create(['status' => PaymentStatus::Unpaid, 'due_at' => today()->subDay()]);
        Review::factory()->create(['visible' => false]);

        $this->actingAs($this->therapist());

        $types = array_column(SuggestionFinder::all(), 'type');

        $this->assertNotContains('payments_past_due', $types);
        $this->assertNotContains('reviews_hidden', $types);
    }

    public function test_a_therapist_only_sees_conflicts_they_are_part_of(): void
    {
        $therapist = $this->therapist();
        $profile = StaffProfile::query()->where('user_id', $therapist->id)->firstOrFail();
        $room = Room::factory()->create();

        // Their own double-booked hour.
        Reservation::factory()->create([
            'therapist_id' => $profile->id,
            'room_id' => $room->id,
            'reservation_date' => today()->addDay(),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        Reservation::factory()->create([
            'therapist_id' => $profile->id,
            'room_id' => $room->id,
            'reservation_date' => today()->addDay(),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '09:30',
            'end_time' => '10:30',
        ]);

        $this->actingAs($therapist);
        $mine = Livewire::test(ProblemsWidget::class)->viewData('total');

        $this->assertSame(2, $mine, 'Their overlap clashes on both the room and themselves.');

        // Somebody else's clash, in another room, is not their business.
        $otherRoom = Room::factory()->create();
        Reservation::factory()->count(2)->create([
            'room_id' => $otherRoom->id,
            'reservation_date' => today()->addDays(2),
            'status' => ReservationStatus::Confirmed,
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);

        $this->assertSame($mine, Livewire::test(ProblemsWidget::class)->viewData('total'));
    }
}
