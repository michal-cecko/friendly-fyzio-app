<?php

namespace Tests\Feature\Suggestions;

use App\Enums\BookingStatus;
use App\Enums\ContactInquiryStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\SettingValueType;
use App\Enums\WaitlistPromotionMode;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Support\RelationManagers\WaitlistEntriesRelationManager;
use App\Models\ContactInquiry;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\EventCategory;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationDayWaitlistEntry;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\Enrollments\InviteWaitlistToSpot;
use App\Support\Settings;
use App\Support\Suggestions\Rules\DayWaitlistNotifiableRule;
use App\Support\Suggestions\Rules\DoctorNotePendingRule;
use App\Support\Suggestions\Rules\DropInPriceMissingRule;
use App\Support\Suggestions\Rules\ExpiredPaymentHoldRule;
use App\Support\Suggestions\Rules\HiddenReviewsRule;
use App\Support\Suggestions\Rules\LessonWaitlistOfferRule;
use App\Support\Suggestions\Rules\NewContactInquiriesRule;
use App\Support\Suggestions\Rules\PastDueInvoicesRule;
use App\Support\Suggestions\Rules\PastDuePaymentsRule;
use App\Support\Suggestions\Rules\SeriesWaitlistOfferRule;
use App\Support\Suggestions\Rules\UnsettledPastVisitsRule;
use App\Support\Suggestions\SuggestionFinder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every rule is exercised directly rather than through the finder, so a failure
 * names the rule that broke and one test only runs one rule's queries.
 */
class SuggestionFinderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The rules build Filament URLs, which need a bound panel.
        Filament::setCurrentPanel('admin');
        Cache::flush();

        $this->actingAs(User::factory()->admin()->create());
    }

    private function seriesWithFreeSpotAndWaiter(array $attributes = []): CourseSeries
    {
        $series = CourseSeries::factory()->create([
            'capacity' => 5,
            'start_date' => today()->subWeek(),
            'end_date' => today()->addMonth(),
            'status' => CourseSeriesStatus::Open,
            'waitlist_promotion_mode' => WaitlistPromotionMode::Manual,
            ...$attributes,
        ]);

        CourseEnrollment::factory()->create([
            'series_id' => $series->id,
            'status' => CourseEnrollmentStatus::Active,
        ]);

        WaitlistEntry::factory()->forWaitlistable($series)->create(['notified_at' => null]);

        return $series;
    }

    public function test_series_with_a_free_spot_and_a_waiting_client_is_suggested(): void
    {
        $series = $this->seriesWithFreeSpotAndWaiter();

        $suggestions = (new SeriesWaitlistOfferRule(app(InviteWaitlistToSpot::class)))->items(10);

        $this->assertCount(1, $suggestions);
        $this->assertSame('waitlist_offer_series', $suggestions[0]['type']);
        $this->assertSame($series->id, $suggestions[0]['id']);
        $this->assertStringContainsString('volných míst: 4', $suggestions[0]['detail']);
        $this->assertStringContainsString('na čekací listině: 1', $suggestions[0]['detail']);
        $this->assertSame('Oslovit čekající', $suggestions[0]['resolveLabel']);
    }

    /**
     * The rule is deliberately mode-agnostic: on Ručně nothing will ever happen
     * on its own, and on the automatic modes a round that expired leaves the
     * same silence.
     */
    #[DataProvider('promotionModeProvider')]
    public function test_series_suggestion_ignores_the_promotion_mode(WaitlistPromotionMode $mode): void
    {
        $this->seriesWithFreeSpotAndWaiter(['waitlist_promotion_mode' => $mode]);

        $this->assertCount(1, (new SeriesWaitlistOfferRule(app(InviteWaitlistToSpot::class)))->items(10));
    }

    /** @return list<array{WaitlistPromotionMode}> */
    public static function promotionModeProvider(): array
    {
        return [
            [WaitlistPromotionMode::Manual],
            [WaitlistPromotionMode::AutomaticInvite],
            [WaitlistPromotionMode::AutomaticAdd],
        ];
    }

    public function test_series_suggestion_is_silent_during_a_running_invite_round(): void
    {
        $series = $this->seriesWithFreeSpotAndWaiter();
        $series->forceFill(['waitlist_invited_until' => now()->addHours(4)])->save();

        $this->assertSame([], (new SeriesWaitlistOfferRule(app(InviteWaitlistToSpot::class)))->items(10));
    }

    public function test_series_suggestion_is_silent_when_the_series_is_full_or_nobody_waits(): void
    {
        $full = $this->seriesWithFreeSpotAndWaiter(['capacity' => 1]);
        $this->assertSame([], (new SeriesWaitlistOfferRule(app(InviteWaitlistToSpot::class)))->items(10), 'A full série has no spot to offer.');

        $full->update(['capacity' => 5]);
        WaitlistEntry::query()->update(['notified_at' => now()]);

        $this->assertSame([], (new SeriesWaitlistOfferRule(app(InviteWaitlistToSpot::class)))->items(10), 'An already-notified queue is not waiting.');
    }

    public function test_lesson_occupancy_is_counted_from_attendances_not_bookings(): void
    {
        $lesson = Lesson::factory()->standalone()->create([
            'lesson_date' => today()->addWeek(),
            'capacity' => 2,
        ]);
        WaitlistEntry::factory()->forWaitlistable($lesson)->create(['notified_at' => null]);

        $rule = new LessonWaitlistOfferRule(app(InviteWaitlistToSpot::class));

        $this->assertCount(1, $rule->items(10));

        // Seats only hold a place while their enrollment is active, so the
        // fixture has to say so explicitly.
        LessonAttendance::factory()->count(2)->create([
            'lesson_id' => $lesson->id,
            'cancelled_at' => null,
            'enrollment_id' => CourseEnrollment::factory()->state(['status' => CourseEnrollmentStatus::Active]),
        ]);

        $this->assertSame([], $rule->items(10), 'Attendances fill a lesson, so no spot is left to offer.');
    }

    public function test_expired_payment_hold_is_reported_for_courses_and_lessons(): void
    {
        $enrollment = CourseEnrollment::factory()->create([
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Unpaid,
            'series_id' => CourseSeries::factory()->create(['end_date' => today()->addMonth()]),
        ]);
        Payment::factory()->create([
            'payable_type' => $enrollment->getMorphClass(),
            'payable_id' => $enrollment->id,
            'status' => PaymentStatus::Unpaid,
            'due_at' => today()->subDay(),
        ]);

        $suggestions = (new ExpiredPaymentHoldRule)->items(10);

        $this->assertCount(1, $suggestions);
        $this->assertSame('expired_payment_hold', $suggestions[0]['type']);
        $this->assertStringContainsString('po splatnosti: 1', $suggestions[0]['detail']);
    }

    public function test_a_hold_that_has_not_run_out_is_not_reported(): void
    {
        $booking = LessonBooking::factory()->create([
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'lesson_id' => Lesson::factory()->create(['lesson_date' => today()->addWeek()]),
        ]);
        Payment::factory()->create([
            'payable_type' => $booking->getMorphClass(),
            'payable_id' => $booking->id,
            'status' => PaymentStatus::Unpaid,
            'due_at' => today()->addDay(),
        ]);

        $this->assertSame([], (new ExpiredPaymentHoldRule)->items(10));
    }

    public function test_course_without_a_drop_in_price_is_suggested_only_when_the_drop_in_category_exists(): void
    {
        $course = Course::factory()->create(['drop_in_price' => null]);
        $series = CourseSeries::factory()->create(['course_id' => $course->id, 'capacity' => 10]);
        Lesson::factory()->create(['series_id' => $series->id, 'lesson_date' => today()->addWeek()]);

        $rule = new DropInPriceMissingRule;

        EventCategory::query()->where('slug', Settings::dropInCategorySlug())->delete();
        $this->assertFalse($rule->isEnabled(), 'Without the drop-in category the release engine bails, so the card would lead nowhere.');

        EventCategory::factory()->create(['slug' => Settings::dropInCategorySlug()]);

        $this->assertTrue($rule->isEnabled());
        $suggestions = $rule->items(10);
        $this->assertCount(1, $suggestions);
        $this->assertStringContainsString($course->name, $suggestions[0]['detail']);

        $course->update(['drop_in_price' => 250]);
        $this->assertSame([], $rule->items(10));
    }

    public function test_doctor_note_pending_counts_only_unresolved_promises(): void
    {
        Reservation::factory()->create([
            'doctor_note_requested_at' => now()->subDay(),
            'doctor_note_resolved_at' => null,
        ]);
        Reservation::factory()->create([
            'doctor_note_requested_at' => now()->subDays(3),
            'doctor_note_resolved_at' => now()->subDay(),
        ]);

        $suggestions = (new DoctorNotePendingRule)->items(10);

        $this->assertCount(1, $suggestions);
        $this->assertStringContainsString('doporučení: 1', $suggestions[0]['detail']);
    }

    public function test_unsettled_past_visits_skip_imported_and_ancient_ones(): void
    {
        Reservation::factory()->create([
            'reservation_date' => today()->subDay(),
            'status' => ReservationStatus::Confirmed,
            'settled_at' => null,
        ]);
        Reservation::factory()->create([
            'reservation_date' => today()->subDays(2),
            'status' => ReservationStatus::Confirmed,
            'settled_at' => null,
            'imported_at' => now(),
        ]);
        Reservation::factory()->create([
            'reservation_date' => today()->subDays(200),
            'status' => ReservationStatus::Confirmed,
            'settled_at' => null,
        ]);

        $suggestions = (new UnsettledPastVisitsRule)->items(10);

        $this->assertCount(1, $suggestions);
        $this->assertStringContainsString('návštěv: 1', $suggestions[0]['detail']);
    }

    /**
     * `payments:mark-overdue` is part of the schedule, which is off before
     * launch — so the rule must key on the due date, not on the Overdue status.
     */
    public function test_unpaid_payment_past_its_due_date_counts_even_without_the_overdue_status(): void
    {
        Payment::factory()->create(['status' => PaymentStatus::Unpaid, 'due_at' => today()->subDay()]);
        Payment::factory()->create(['status' => PaymentStatus::Unpaid, 'due_at' => today()->addDay()]);
        Payment::factory()->paid()->create(['due_at' => today()->subWeek()]);

        $suggestions = (new PastDuePaymentsRule)->items(10);

        $this->assertCount(1, $suggestions);
        $this->assertStringContainsString('plateb po splatnosti: 1', $suggestions[0]['detail']);
    }

    public function test_invoices_past_due_are_reported_until_they_are_paid(): void
    {
        Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'due_at' => today()->subDay()]);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'due_at' => today()->subDay()]);

        $suggestions = (new PastDueInvoicesRule)->items(10);

        $this->assertCount(1, $suggestions);
        $this->assertStringContainsString('faktur po splatnosti: 1', $suggestions[0]['detail']);
    }

    public function test_new_inquiries_and_hidden_reviews_are_reported(): void
    {
        ContactInquiry::factory()->create(['status' => ContactInquiryStatus::New]);
        ContactInquiry::factory()->create(['status' => ContactInquiryStatus::Handled]);
        Review::factory()->create(['visible' => false]);
        Review::factory()->create(['visible' => true]);

        $this->assertStringContainsString('zpráv z kontaktního formuláře: 1', (new NewContactInquiriesRule)->items(10)[0]['detail']);
        $this->assertStringContainsString('recenzí: 1', (new HiddenReviewsRule)->items(10)[0]['detail']);
    }

    public function test_the_badge_count_always_matches_the_list(): void
    {
        $this->seriesWithFreeSpotAndWaiter();
        Reservation::factory()->create([
            'doctor_note_requested_at' => now()->subDay(),
            'doctor_note_resolved_at' => null,
        ]);
        Review::factory()->create(['visible' => false]);
        Payment::factory()->create(['status' => PaymentStatus::Unpaid, 'due_at' => today()->subDay()]);

        $this->assertSame(count(SuggestionFinder::all()), SuggestionFinder::count());
        $this->assertGreaterThanOrEqual(4, SuggestionFinder::count());
    }

    public function test_cards_are_ordered_by_urgency(): void
    {
        Review::factory()->create(['visible' => false]);
        $this->seriesWithFreeSpotAndWaiter();

        $types = array_column(SuggestionFinder::all(), 'type');

        $this->assertSame('waitlist_offer_series', $types[0], 'A free spot with people waiting outranks an unpublished review.');
        $this->assertSame('reviews_hidden', end($types));
    }

    public function test_one_rule_can_never_flood_the_list(): void
    {
        for ($i = 0; $i < SuggestionFinder::RULE_CAP + 2; $i++) {
            $this->seriesWithFreeSpotAndWaiter();
        }

        $rule = new SeriesWaitlistOfferRule(app(InviteWaitlistToSpot::class));

        $this->assertCount(SuggestionFinder::RULE_CAP, $rule->items(SuggestionFinder::RULE_CAP));
        $this->assertSame(SuggestionFinder::RULE_CAP, $rule->count(SuggestionFinder::RULE_CAP));
    }

    public function test_aggregate_cards_link_to_a_list_filtered_to_exactly_their_set(): void
    {
        $pending = Reservation::factory()->create([
            'doctor_note_requested_at' => now()->subDay(),
            'doctor_note_resolved_at' => null,
        ]);
        $resolved = Reservation::factory()->create([
            'doctor_note_requested_at' => now()->subDays(3),
            'doctor_note_resolved_at' => now(),
        ]);

        $url = (new DoctorNotePendingRule)->items(10)[0]['url'];

        $this->get($url)
            ->assertOk()
            ->assertSee($pending->id, escape: false)
            ->assertDontSee($resolved->id, escape: false);
    }

    /**
     * The série card deep-links the waitlist tab, whose key is the relation's
     * position in getRelations() — computed, never hard-coded, so reordering the
     * relation managers cannot silently land the admin on the wrong tab.
     */
    public function test_the_waitlist_card_opens_the_series_on_its_waitlist_tab(): void
    {
        $series = $this->seriesWithFreeSpotAndWaiter();

        $url = (new SeriesWaitlistOfferRule(app(InviteWaitlistToSpot::class)))->items(10)[0]['url'];

        $expectedTab = array_search(WaitlistEntriesRelationManager::class, CourseSeriesResource::getRelations(), true);

        $this->assertStringContainsString($series->id, $url);
        $this->assertStringContainsString('relation='.$expectedTab, $url);
        $this->get($url)->assertOk();
    }

    public function test_day_waitlist_rule_is_off_when_the_poradnik_is_disabled(): void
    {
        ReservationDayWaitlistEntry::factory()->create([
            'reservation_date' => today()->addDay(),
            'notified_at' => null,
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'reservation.day_waitlist_enabled'],
            ['value' => '0', 'type' => SettingValueType::Boolean, 'label' => 'Pořadník na plné dny'],
        );
        Cache::flush();

        $this->assertFalse(app(DayWaitlistNotifiableRule::class)->isEnabled());
        $this->assertSame([], app(DayWaitlistNotifiableRule::class)->items(10));
    }
}
