<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\LessonExcuseReason;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ViewLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\RelationManagers\AttendancesRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Support\Actions\ToggleLessonAttendanceAction;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use App\Models\SubstituteRule;
use App\Models\SubstituteToken;
use App\Models\User;
use App\Notifications\SubstituteTokenNotification;
use App\Support\ActivityLog\ActivityPresenter;
use App\Support\Substitutes\MoveClientToLesson;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The presence switch on a lesson's Docházka tab. Before the lesson it frees a
 * spot for somebody else; afterwards it is the register. Anything that takes
 * something away asks first, and every direction lands in the activity log.
 */
class LessonAttendanceToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->admin = User::factory()->admin()->create();

        $this->actingAs($this->admin);
    }

    /**
     * A lesson starting at the given offset from now — date and time are derived
     * from the same instant, so "+2 hours" really is two hours away whatever the
     * clock says when the suite runs.
     */
    private function lesson(string $startingIn = '+1 week', array $courseAttributes = [], bool $withSubstituteTarget = true): Lesson
    {
        $course = Course::factory()->create([
            'early_cancel_hours' => 24,
            'max_substitutions' => 2,
            ...$courseAttributes,
        ]);

        $series = CourseSeries::factory()->create([
            'course_id' => $course->getKey(),
            'capacity' => 10,
        ]);

        // A poukaz is only worth issuing where it can be redeemed, so by default
        // the série has somewhere to send the client.
        if ($withSubstituteTarget) {
            SubstituteRule::create([
                'source_series_id' => $series->getKey(),
                'target_series_id' => CourseSeries::factory()->create(['capacity' => 10])->getKey(),
            ]);
        }

        $start = now()->modify($startingIn);

        return Lesson::factory()->create([
            'series_id' => $series->getKey(),
            'lesson_date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHour()->format('H:i'),
        ]);
    }

    private function enroll(Lesson $lesson): CourseEnrollment
    {
        return CourseEnrollment::factory()->create([
            'series_id' => $lesson->series_id,
            'status' => CourseEnrollmentStatus::Active,
        ]);
    }

    private function attendance(Lesson $lesson, CourseEnrollment $enrollment): LessonAttendance
    {
        return LessonAttendance::query()
            ->where('lesson_id', $lesson->getKey())
            ->where('enrollment_id', $enrollment->getKey())
            ->firstOrFail();
    }

    private function relationManager(Lesson $lesson): Testable
    {
        return Livewire::test(AttendancesRelationManager::class, [
            'ownerRecord' => $lesson,
            'pageClass' => ViewLesson::class,
        ]);
    }

    /**
     * A seat somebody bought as a single lesson: a standalone lesson and a
     * drop-in booking, with no enrollment and no course rules behind it.
     */
    private function dropInAttendance(string $startingIn = '+1 week'): LessonAttendance
    {
        $start = now()->modify($startingIn);

        $lesson = Lesson::factory()->standalone()->create([
            'name' => 'Baby massage workshop',
            'lesson_date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHour()->format('H:i'),
        ]);

        $booking = LessonBooking::factory()->create([
            'lesson_id' => $lesson->getKey(),
            'status' => 'confirmed',
        ]);

        return LessonAttendance::query()
            ->where('lesson_id', $lesson->getKey())
            ->where('booking_id', $booking->getKey())
            ->firstOrFail();
    }

    public function test_an_upcoming_lesson_asks_whether_the_client_will_attend(): void
    {
        $lesson = $this->lesson();
        $this->enroll($lesson);

        $this->relationManager($lesson)
            ->assertTableColumnExists('presence')
            ->assertSee('Zúčastní se?');
    }

    public function test_a_past_lesson_records_who_turned_up(): void
    {
        $lesson = $this->lesson('-1 week');
        $this->enroll($lesson);

        $this->relationManager($lesson)
            ->assertTableColumnExists('presence')
            ->assertSee('Účast');
    }

    /**
     * Being on the list is being present, in either tense: nobody has to tick a
     * roster row off for the client to count as having been there.
     */
    public function test_a_new_roster_row_reads_as_present(): void
    {
        $upcoming = $this->lesson();
        $upcomingEnrollment = $this->enroll($upcoming);

        $this->relationManager($upcoming)
            ->assertTableColumnStateSet('presence', true, $this->attendance($upcoming, $upcomingEnrollment));

        $past = $this->lesson('-1 week');
        $pastEnrollment = $this->enroll($past);

        $this->assertTrue($this->attendance($past, $pastEnrollment)->attended);

        $this->relationManager($past)
            ->assertTableColumnStateSet('presence', true, $this->attendance($past, $pastEnrollment));
    }

    public function test_an_excused_client_reads_as_absent(): void
    {
        $lesson = $this->lesson('-1 week');
        $enrollment = $this->enroll($lesson);

        $this->attendance($lesson, $enrollment)->update(['attended' => false, 'cancelled_at' => now()]);

        $this->relationManager($lesson)
            ->assertTableColumnStateSet('presence', false, $this->attendance($lesson, $enrollment));
    }

    public function test_the_client_name_links_to_their_client_file(): void
    {
        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);
        $attendance = $this->attendance($lesson, $enrollment);

        $this->relationManager($lesson)->assertTableColumnExists(
            'client.name',
            fn (TextColumn $column): bool => $column->getUrl() === ClientResource::getUrl('view', [
                'record' => $enrollment->client,
            ])
                && $column->getColor($column->getState()) === 'primary'
                && $column->getWeight($column->getState()) === FontWeight::Bold,
            $attendance,
        );
    }

    public function test_marking_a_client_as_not_coming_frees_a_spot(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->assertSame(1, $lesson->fresh()->takenSpots());

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => false,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertNotNull($this->attendance($lesson, $enrollment)->cancelled_at);
        $this->assertSame(0, $lesson->fresh()->takenSpots());
        $this->assertSame(10, $lesson->fresh()->spotsLeft());

        Notification::assertNothingSent();
    }

    public function test_it_can_mint_a_substitute_and_tell_the_client(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => true,
                'notify_client' => true,
            ]);

        $attendance = $this->attendance($lesson, $enrollment);
        $token = SubstituteToken::query()->where('source_lesson_id', $lesson->getKey())->sole();

        $this->assertTrue($attendance->token_generated);
        $this->assertSame($attendance->getKey(), $token->source_attendance_id);

        Notification::assertSentTo(
            $enrollment->client,
            SubstituteTokenNotification::class,
            fn (SubstituteTokenNotification $notification): bool => $notification->key === EmailTemplateKey::SubstituteTokenGenerated,
        );
    }

    public function test_a_late_excuse_mints_no_substitute_but_still_tells_the_client(): void
    {
        Notification::fake();

        $lesson = $this->lesson('+2 hours');
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => false,
                'notify_client' => true,
            ]);

        $this->assertSame(0, SubstituteToken::query()->count());
        $this->assertNotNull($this->attendance($lesson, $enrollment)->cancelled_at);

        Notification::assertSentTo(
            $enrollment->client,
            SubstituteTokenNotification::class,
            fn (SubstituteTokenNotification $notification): bool => $notification->key === EmailTemplateKey::LessonExcused,
        );
    }

    public function test_the_drop_in_modal_offers_the_notice_toggle_but_no_substitute(): void
    {
        $attendance = $this->dropInAttendance();

        $this->relationManager($attendance->lesson)
            ->mountAction(TestAction::make('toggleAttendance')->table($attendance))
            ->assertActionMounted()
            ->assertSchemaComponentExists(
                'notify_client',
                checkComponentUsing: fn (Toggle $component): bool => $component->getDefaultState() === true,
            );
    }

    public function test_a_drop_in_can_be_unenrolled_with_a_notice(): void
    {
        Notification::fake();

        $attendance = $this->dropInAttendance();

        $this->relationManager($attendance->lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($attendance), [
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertNotNull($attendance->fresh()->cancelled_at);
        $this->assertSame(0, SubstituteToken::query()->count());

        Notification::assertSentTo(
            $attendance->client,
            SubstituteTokenNotification::class,
            fn (SubstituteTokenNotification $notification): bool => $notification->key === EmailTemplateKey::LessonBookingCancelled,
        );
    }

    public function test_a_drop_in_can_be_unenrolled_without_a_notice(): void
    {
        Notification::fake();

        $attendance = $this->dropInAttendance();

        $this->relationManager($attendance->lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($attendance), [
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertNotNull($attendance->fresh()->cancelled_at);

        Notification::assertNothingSent();
    }

    public function test_staff_may_grant_a_substitute_the_course_rules_would_refuse(): void
    {
        Notification::fake();

        $lesson = $this->lesson('+2 hours');
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => true,
                'notify_client' => false,
            ]);

        $this->assertSame(1, SubstituteToken::query()->count());
        $this->assertTrue($this->attendance($lesson, $enrollment)->token_generated);

        $absence = Activity::query()->where('event', 'lesson_absence')->sole();

        $this->assertTrue($absence->properties['override']);
    }

    public function test_the_modal_reports_how_many_substitutes_are_left(): void
    {
        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->mountAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)))
            ->assertActionMounted()
            ->assertSchemaComponentExists(
                'generate_substitute',
                checkComponentUsing: fn (Toggle $component): bool => $component->getDefaultState() === true,
            );

        $helperText = ToggleLessonAttendanceAction::substituteHelperText($this->attendance($lesson, $enrollment));

        $this->assertStringContainsString('Zbývá 2 z 2', $helperText);
        // ...and where the poukaz would actually be redeemable.
        $this->assertStringContainsString('Klient si vybere volný termín v:', $helperText);
    }

    public function test_a_course_that_offers_no_substitutes_cannot_grant_one(): void
    {
        Notification::fake();

        $lesson = $this->lesson('+1 week', ['max_substitutions' => 0]);
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->mountAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)))
            ->assertSchemaComponentExists(
                'generate_substitute',
                checkComponentUsing: fn (Toggle $component): bool => $component->isDisabled()
                    && $component->getDefaultState() === false,
            );

        $this->assertStringContainsString(
            '„Max. náhrad“ je u kurzu nastavené na 0',
            ToggleLessonAttendanceAction::substituteHelperText($this->attendance($lesson, $enrollment)),
        );

        // Even asked directly, the excuse mints nothing.
        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => true,
                'notify_client' => false,
            ]);

        $this->assertSame(0, SubstituteToken::query()->count());
    }

    public function test_a_series_with_nowhere_to_redeem_cannot_grant_a_substitute(): void
    {
        $lesson = $this->lesson('+1 week', withSubstituteTarget: false);
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->mountAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)))
            ->assertSchemaComponentExists(
                'generate_substitute',
                checkComponentUsing: fn (Toggle $component): bool => $component->isDisabled(),
            );

        $this->assertStringContainsString(
            'nemá nastavené náhradní série',
            ToggleLessonAttendanceAction::substituteHelperText($this->attendance($lesson, $enrollment)),
        );
    }

    public function test_the_modal_admits_when_the_substitute_limit_is_spent(): void
    {
        Notification::fake();

        $lesson = $this->lesson('+1 week', ['max_substitutions' => 1]);
        $enrollment = $this->enroll($lesson);
        $spent = Lesson::factory()->create([
            'series_id' => $lesson->series_id,
            'lesson_date' => now()->addWeeks(3)->format('Y-m-d'),
        ]);

        $this->attendance($spent, $enrollment)->update([
            'cancelled_at' => now(),
            'token_generated' => true,
        ]);

        $this->relationManager($lesson)
            ->mountAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)))
            ->assertSchemaComponentExists(
                'generate_substitute',
                checkComponentUsing: fn (Toggle $component): bool => $component->getDefaultState() === false,
            );

        $this->assertStringContainsString(
            'Limit náhrad je vyčerpaný (1 z 1)',
            ToggleLessonAttendanceAction::substituteHelperText($this->attendance($lesson, $enrollment)),
        );
    }

    public function test_both_directions_are_written_to_the_activity_log(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => false,
                'notify_client' => false,
            ]);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        $this->assertSame(1, Activity::query()->where('event', 'lesson_absence')->count());
        $this->assertSame(1, Activity::query()->where('event', 'lesson_absence_reverted')->count());
    }

    public function test_putting_a_client_back_reclaims_the_spot_and_withdraws_the_substitute(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => true,
                'notify_client' => false,
            ]);

        $this->assertSame(1, SubstituteToken::query()->count());

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        $attendance = $this->attendance($lesson, $enrollment);

        $this->assertNull($attendance->cancelled_at);
        $this->assertFalse($attendance->token_generated);
        $this->assertSame(0, SubstituteToken::query()->count());
        $this->assertSame(1, $lesson->fresh()->takenSpots());
    }

    public function test_a_redeemed_substitute_cannot_be_taken_back(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => true,
                'notify_client' => false,
            ]);

        SubstituteToken::query()->firstOrFail()->update([
            'used_at' => now(),
            'used_for_lesson_id' => $this->lesson()->getKey(),
        ]);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        $this->assertNotNull($this->attendance($lesson, $enrollment)->cancelled_at);
        $this->assertSame(1, SubstituteToken::query()->count());
    }

    public function test_a_client_cannot_be_returned_to_a_lesson_that_filled_up(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $lesson->series->update(['capacity' => 1]);
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => false,
                'notify_client' => false,
            ]);

        // Somebody else took the freed spot in the meantime.
        $foreign = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create()->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        LessonAttendance::create([
            'enrollment_id' => $foreign->getKey(),
            'lesson_id' => $lesson->getKey(),
            'attended' => false,
        ]);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        $this->assertNotNull($this->attendance($lesson, $enrollment)->cancelled_at);
    }

    /**
     * Correcting the register after the fact takes a spot away from somebody who
     * was counted present, so it is never a single click.
     */
    public function test_marking_a_client_absent_on_a_past_lesson_asks_first(): void
    {
        $lesson = $this->lesson('-1 week');
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->mountAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)))
            ->assertActionMounted(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        $this->assertTrue($this->attendance($lesson, $enrollment)->attended);
    }

    /**
     * A presence event belongs to the seat it changed, not to the whole lesson —
     * that is what lets one client's row show one client's history.
     */
    public function test_presence_events_are_filed_against_the_attendance_row(): void
    {
        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);
        $attendance = $this->attendance($lesson, $enrollment);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($attendance), [
                'generate_substitute' => false,
                'notify_client' => false,
            ]);

        $activity = Activity::query()->where('event', 'lesson_absence')->sole();

        $this->assertSame($attendance->getMorphClass(), $activity->subject_type);
        $this->assertSame($attendance->getKey(), $activity->subject_id);
        $this->assertSame($lesson->getKey(), $activity->getProperty('lesson_id'));

        $this->assertStringContainsString(
            $enrollment->client->name,
            ActivityPresenter::summary($activity),
        );
    }

    public function test_the_activity_log_names_the_client_in_every_presence_event(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => false,
                'notify_client' => false,
            ]);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        foreach (['lesson_absence', 'lesson_absence_reverted'] as $event) {
            $this->assertStringContainsString(
                $enrollment->client->name,
                ActivityPresenter::summary(Activity::query()->where('event', $event)->sole()),
            );
        }
    }

    public function test_an_absence_recorded_after_the_lesson_keeps_the_reason(): void
    {
        Notification::fake();

        $lesson = $this->lesson('-1 week');
        $enrollment = $this->enroll($lesson);

        $this->attendance($lesson, $enrollment)->update(['attended' => true]);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'excuse_reason' => LessonExcuseReason::Illness->value,
                'excuse_note' => 'Volala ráno, chřipka.',
                'generate_substitute' => false,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $attendance = $this->attendance($lesson, $enrollment);

        $this->assertFalse($attendance->attended);
        $this->assertNotNull($attendance->cancelled_at);
        $this->assertSame(LessonExcuseReason::Illness, $attendance->excuse_reason);
        $this->assertSame('Volala ráno, chřipka.', $attendance->excuse_note);
        $this->assertSame($this->admin->getKey(), $attendance->excused_by_id);
    }

    public function test_undoing_a_past_absence_marks_the_client_present(): void
    {
        Notification::fake();

        $lesson = $this->lesson('-1 week');
        $enrollment = $this->enroll($lesson);

        $this->attendance($lesson, $enrollment)->update([
            'cancelled_at' => now(),
            'excuse_reason' => LessonExcuseReason::NoShow,
        ]);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        $attendance = $this->attendance($lesson, $enrollment);

        $this->assertTrue($attendance->attended);
        $this->assertNull($attendance->cancelled_at);
        $this->assertNull($attendance->excuse_reason);
    }

    public function test_the_substitute_column_names_the_lesson_on_both_sides_of_a_move(): void
    {
        Notification::fake();

        $source = $this->lesson();
        $enrollment = $this->enroll($source);
        $target = $this->lesson('+2 weeks');

        app(MoveClientToLesson::class)($enrollment->client, $target, $source);

        $excused = $this->attendance($source, $enrollment);
        $replacement = $this->attendance($target, $enrollment);

        $this->relationManager($source)
            ->assertTableColumnStateSet('substitute', 'Nahrazeno · '.$this->label($target), $excused)
            ->assertSee(LessonResource::getUrl('view', ['record' => $target]));

        $this->relationManager($target)
            ->assertTableColumnStateSet('substitute', 'Náhrada za · '.$this->label($source), $replacement)
            ->assertSee(LessonResource::getUrl('view', ['record' => $source]));
    }

    public function test_the_substitute_column_reports_an_unredeemed_voucher(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => true,
                'notify_client' => false,
            ]);

        $expiry = SubstituteToken::query()->sole()->expires_at->format('j. n. Y');

        $this->relationManager($lesson)
            ->assertTableColumnStateSet(
                'substitute',
                "Poukaz nevybrán (platí do {$expiry})",
                $this->attendance($lesson, $enrollment),
            );
    }

    public function test_the_substitute_column_says_when_no_make_up_is_owed(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => false,
                'notify_client' => false,
            ]);

        $this->relationManager($lesson)
            ->assertTableColumnStateSet('substitute', 'Bez náhrady', $this->attendance($lesson, $enrollment));
    }

    public function test_a_client_whose_lesson_was_already_made_up_cannot_be_put_back(): void
    {
        Notification::fake();

        $source = $this->lesson();
        $enrollment = $this->enroll($source);
        $target = $this->lesson('+2 weeks');

        app(MoveClientToLesson::class)($enrollment->client, $target, $source);

        $this->relationManager($source)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($source, $enrollment)));

        $this->assertNotNull($this->attendance($source, $enrollment)->cancelled_at);
    }

    private function label(Lesson $lesson): string
    {
        return $lesson->startsAt()->format('j. n. Y · H:i').' · '.$lesson->series->name;
    }
}
