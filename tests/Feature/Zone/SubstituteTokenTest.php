<?php

namespace Tests\Feature\Zone;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\EmailTemplateKey;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\LessonAttendance;
use App\Models\SubstituteRule;
use App\Models\SubstituteToken;
use App\Models\User;
use App\Notifications\SubstituteTokenNotification;
use App\Support\Substitutes\ExcuseFromLesson;
use App\Support\Substitutes\RedeemToken;
use App\Support\Substitutes\SubstituteException;
use App\Support\Substitutes\SubstituteOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubstituteTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function series(array $courseAttributes = []): CourseSeries
    {
        $course = Course::factory()->create([
            'published_at' => now(),
            'max_substitutions' => 2,
            'early_cancel_hours' => 24,
            ...$courseAttributes,
        ]);

        return CourseSeries::factory()->for($course)->create([
            'start_date' => today()->subWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(8)->toDateString(),
            'capacity' => 10,
            'status' => CourseSeriesStatus::Open,
        ]);
    }

    protected function lesson(CourseSeries $series, string $date, string $time = '18:00:00'): CourseLesson
    {
        return CourseLesson::factory()->for($series, 'series')->create([
            'lesson_date' => $date,
            'start_time' => $time,
            'end_time' => '19:00:00',
        ]);
    }

    protected function enrollment(CourseSeries $series, ?User $client = null): CourseEnrollment
    {
        return CourseEnrollment::factory()->for($series, 'series')->create([
            'client_id' => ($client ?? User::factory()->customer()->create())->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);
    }

    public function test_a_timely_excuse_generates_a_token_and_emails_the_client(): void
    {
        $series = $this->series();
        $enrollment = $this->enrollment($series);
        $lesson = $this->lesson($series, today()->addWeek()->toDateString());

        $token = app(ExcuseFromLesson::class)($enrollment, $lesson);

        $this->assertNotNull($token);
        $this->assertSame($enrollment->client_id, $token->client_id);
        $this->assertTrue($token->expires_at->isSameDay(now()->addDays(30)));

        $this->assertDatabaseHas('lesson_attendances', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'token_generated' => true,
        ]);

        Notification::assertSentTo($enrollment->client, SubstituteTokenNotification::class, fn (SubstituteTokenNotification $notification): bool => $notification->key === EmailTemplateKey::SubstituteTokenGenerated);
    }

    public function test_a_late_excuse_records_the_absence_without_a_token(): void
    {
        $series = $this->series();
        $enrollment = $this->enrollment($series);
        // Starts in 2 hours — inside the course's 24h early-cancel window.
        $lesson = $this->lesson($series, today()->toDateString(), now()->addHours(2)->format('H:i:s'));

        $token = app(ExcuseFromLesson::class)($enrollment, $lesson);

        $this->assertNull($token);
        $this->assertSame(0, SubstituteToken::query()->count());
        $this->assertDatabaseHas('lesson_attendances', [
            'lesson_id' => $lesson->id,
            'token_generated' => false,
        ]);
    }

    public function test_excuses_beyond_the_courses_limit_generate_no_token(): void
    {
        $series = $this->series(['max_substitutions' => 1]);
        $enrollment = $this->enrollment($series);

        $first = $this->lesson($series, today()->addWeek()->toDateString());
        $second = $this->lesson($series, today()->addWeeks(2)->toDateString());

        $this->assertNotNull(app(ExcuseFromLesson::class)($enrollment, $first));
        $this->assertNull(app(ExcuseFromLesson::class)($enrollment, $second));
        $this->assertSame(1, SubstituteToken::query()->count());
    }

    public function test_a_lesson_cannot_be_excused_twice(): void
    {
        $series = $this->series();
        $enrollment = $this->enrollment($series);
        $lesson = $this->lesson($series, today()->addWeek()->toDateString());

        app(ExcuseFromLesson::class)($enrollment, $lesson);

        $this->expectException(SubstituteException::class);

        app(ExcuseFromLesson::class)($enrollment, $lesson);
    }

    public function test_options_only_offer_lessons_of_courses_allowed_by_a_rule(): void
    {
        $sourceSeries = $this->series();
        $enrollment = $this->enrollment($sourceSeries);
        $missed = $this->lesson($sourceSeries, today()->addWeek()->toDateString());
        $token = app(ExcuseFromLesson::class)($enrollment, $missed);

        $allowedSeries = $this->series();
        $allowed = $this->lesson($allowedSeries, today()->addWeeks(2)->toDateString());

        $unrelatedSeries = $this->series();
        $this->lesson($unrelatedSeries, today()->addWeeks(2)->toDateString());

        SubstituteRule::create([
            'source_series_id' => $sourceSeries->id,
            'target_series_id' => $allowedSeries->id,
        ]);

        $options = app(SubstituteOptions::class)->forToken($token);

        $this->assertCount(1, $options);
        $this->assertTrue($options->first()->is($allowed));
    }

    public function test_options_exclude_lessons_the_client_is_already_booked_into(): void
    {
        $sourceSeries = $this->series();
        $client = User::factory()->customer()->create();
        $enrollment = $this->enrollment($sourceSeries, $client);
        $missed = $this->lesson($sourceSeries, today()->addWeek()->toDateString());
        $token = app(ExcuseFromLesson::class)($enrollment, $missed);

        $allowedSeries = $this->series();
        $alreadyBooked = $this->lesson($allowedSeries, today()->addWeeks(2)->toDateString());
        $free = $this->lesson($allowedSeries, today()->addWeeks(3)->toDateString());

        SubstituteRule::create([
            'source_series_id' => $sourceSeries->id,
            'target_series_id' => $allowedSeries->id,
        ]);

        // The client already attends one of the target lessons (e.g. a substitute
        // they redeemed earlier) — it must not be offered a second time.
        LessonAttendance::create([
            'enrollment_id' => $enrollment->getKey(),
            'lesson_id' => $alreadyBooked->getKey(),
            'attended' => false,
        ]);

        $options = app(SubstituteOptions::class)->forToken($token);

        $this->assertCount(1, $options);
        $this->assertTrue($options->first()->is($free));
    }

    public function test_redeeming_books_the_substitute_place_and_consumes_the_token(): void
    {
        $sourceSeries = $this->series();
        $enrollment = $this->enrollment($sourceSeries);
        $missed = $this->lesson($sourceSeries, today()->addWeek()->toDateString());
        $token = app(ExcuseFromLesson::class)($enrollment, $missed);

        $targetSeries = $this->series();
        $target = $this->lesson($targetSeries, today()->addWeeks(2)->toDateString());

        SubstituteRule::create([
            'source_series_id' => $sourceSeries->id,
            'target_series_id' => $targetSeries->id,
        ]);

        $attendance = app(RedeemToken::class)($token, $target);

        $token->refresh();

        $this->assertNotNull($token->used_at);
        $this->assertSame($target->id, $token->used_for_lesson_id);
        // Recorded against the client's own enrollment, on the target lesson.
        $this->assertSame($enrollment->id, $attendance->enrollment_id);
        $this->assertSame($target->id, $attendance->lesson_id);

        Notification::assertSentTo($enrollment->client, SubstituteTokenNotification::class, fn (SubstituteTokenNotification $notification): bool => $notification->key === EmailTemplateKey::SubstituteTokenRedeemed);
    }

    public function test_a_used_token_cannot_be_redeemed_again(): void
    {
        $sourceSeries = $this->series();
        $enrollment = $this->enrollment($sourceSeries);
        $missed = $this->lesson($sourceSeries, today()->addWeek()->toDateString());
        $token = app(ExcuseFromLesson::class)($enrollment, $missed);

        $targetSeries = $this->series();
        $target = $this->lesson($targetSeries, today()->addWeeks(2)->toDateString());
        $second = $this->lesson($targetSeries, today()->addWeeks(3)->toDateString());

        SubstituteRule::create([
            'source_series_id' => $sourceSeries->id,
            'target_series_id' => $targetSeries->id,
        ]);

        app(RedeemToken::class)($token, $target);

        $this->expectException(SubstituteException::class);

        app(RedeemToken::class)($token->fresh(), $second);
    }

    public function test_an_expired_token_cannot_be_redeemed(): void
    {
        $sourceSeries = $this->series();
        $enrollment = $this->enrollment($sourceSeries);
        $missed = $this->lesson($sourceSeries, today()->addWeek()->toDateString());
        $token = app(ExcuseFromLesson::class)($enrollment, $missed);
        $token->update(['expires_at' => now()->subDay()]);

        $targetSeries = $this->series();
        $target = $this->lesson($targetSeries, today()->addWeeks(2)->toDateString());

        SubstituteRule::create([
            'source_series_id' => $sourceSeries->id,
            'target_series_id' => $targetSeries->id,
        ]);

        $this->expectException(SubstituteException::class);

        app(RedeemToken::class)($token->fresh(), $target);
    }

    public function test_a_full_target_lesson_offers_no_place(): void
    {
        $sourceSeries = $this->series();
        $enrollment = $this->enrollment($sourceSeries);
        $missed = $this->lesson($sourceSeries, today()->addWeek()->toDateString());
        $token = app(ExcuseFromLesson::class)($enrollment, $missed);

        // Target run is booked solid: capacity 1, one active enrollment.
        $targetSeries = $this->series();
        $targetSeries->update(['capacity' => 1]);
        $this->enrollment($targetSeries);
        $target = $this->lesson($targetSeries, today()->addWeeks(2)->toDateString());

        SubstituteRule::create([
            'source_series_id' => $sourceSeries->id,
            'target_series_id' => $targetSeries->id,
        ]);

        $this->assertCount(0, app(SubstituteOptions::class)->forToken($token));

        $this->expectException(SubstituteException::class);

        app(RedeemToken::class)($token, $target);
    }

    public function test_a_place_freed_by_an_excuse_reopens_the_lesson_for_substitutes(): void
    {
        $sourceSeries = $this->series();
        $enrollment = $this->enrollment($sourceSeries);
        $missed = $this->lesson($sourceSeries, today()->addWeek()->toDateString());
        $token = app(ExcuseFromLesson::class)($enrollment, $missed);

        // Full target run…
        $targetSeries = $this->series();
        $targetSeries->update(['capacity' => 1]);
        $regular = $this->enrollment($targetSeries);
        $target = $this->lesson($targetSeries, today()->addWeeks(2)->toDateString());

        SubstituteRule::create([
            'source_series_id' => $sourceSeries->id,
            'target_series_id' => $targetSeries->id,
        ]);

        $this->assertCount(0, app(SubstituteOptions::class)->forToken($token));

        // …until its own participant excuses themselves from that lesson.
        LessonAttendance::updateOrCreate(
            ['enrollment_id' => $regular->getKey(), 'lesson_id' => $target->getKey()],
            ['attended' => false, 'cancelled_at' => now(), 'token_generated' => true],
        );

        $this->assertCount(1, app(SubstituteOptions::class)->forToken($token));
    }
}
