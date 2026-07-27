<?php

namespace Tests\Feature\Kurzy;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\EmailTemplateKey;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ViewLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\RelationManagers\AttendancesRelationManager;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\SubstituteToken;
use App\Models\User;
use App\Notifications\SubstituteTokenNotification;
use App\Support\Substitutes\MoveClientToLesson;
use App\Support\Substitutes\SubstituteException;
use App\Support\Substitutes\SubstituteOptions;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class MoveClientIntoLessonTest extends TestCase
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

    protected function lesson(CourseSeries $series, string $date, string $time = '18:00:00'): Lesson
    {
        return Lesson::factory()->for($series, 'series')->create([
            'lesson_date' => $date,
            'start_time' => $time,
            'end_time' => '23:00:00',
        ]);
    }

    protected function enrollment(CourseSeries $series, ?User $client = null): CourseEnrollment
    {
        return CourseEnrollment::factory()->for($series, 'series')->create([
            'client_id' => ($client ?? User::factory()->customer()->create())->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);
    }

    public function test_moving_places_the_client_and_excuses_them_from_the_source(): void
    {
        $sourceSeries = $this->series();
        $client = User::factory()->customer()->create();
        $enrollment = $this->enrollment($sourceSeries, $client);
        $source = $this->lesson($sourceSeries, today()->addWeek()->toDateString());

        $targetSeries = $this->series();
        $target = $this->lesson($targetSeries, today()->addWeeks(2)->toDateString());

        $attendance = app(MoveClientToLesson::class)($client, $target, $source);

        // Placed into the target, recorded against the client's own source enrollment.
        $this->assertSame($enrollment->id, $attendance->enrollment_id);
        $this->assertSame($target->id, $attendance->lesson_id);
        $this->assertNull($attendance->cancelled_at);

        // Excused from the source, without minting a make-up token.
        $this->assertDatabaseHas('lesson_attendances', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $source->id,
            'token_generated' => false,
        ]);
        $excused = LessonAttendance::where('enrollment_id', $enrollment->id)->where('lesson_id', $source->id)->first();

        $this->assertNotNull($excused->cancelled_at);

        // The two rows know about each other, so either presence list can name the other lesson.
        $this->assertSame($attendance->id, $excused->replacement_attendance_id);
        $this->assertTrue($attendance->replacementFor->is($excused));
    }

    public function test_move_works_without_a_rule_and_over_capacity(): void
    {
        $sourceSeries = $this->series();
        $client = User::factory()->customer()->create();
        $this->enrollment($sourceSeries, $client);
        $source = $this->lesson($sourceSeries, today()->addWeek()->toDateString());

        // Target run is booked solid and shares no substitute rule with the source.
        $targetSeries = $this->series();
        $targetSeries->update(['capacity' => 1]);
        $this->enrollment($targetSeries);
        $target = $this->lesson($targetSeries, today()->addWeeks(2)->toDateString());

        $this->assertSame(0, app(SubstituteOptions::class)->freeSpots($target));

        $attendance = app(MoveClientToLesson::class)($client, $target, $source);

        $this->assertSame($target->id, $attendance->lesson_id);
    }

    public function test_move_requires_an_active_enrollment_in_the_source_series(): void
    {
        $sourceSeries = $this->series();
        $client = User::factory()->customer()->create();
        // No enrollment for this client in the source series.
        $source = $this->lesson($sourceSeries, today()->addWeek()->toDateString());

        $target = $this->lesson($this->series(), today()->addWeeks(2)->toDateString());

        $this->expectException(SubstituteException::class);

        app(MoveClientToLesson::class)($client, $target, $source);
    }

    public function test_move_rejects_a_client_already_booked_in_the_target(): void
    {
        $sourceSeries = $this->series();
        $client = User::factory()->customer()->create();
        $enrollment = $this->enrollment($sourceSeries, $client);
        $source = $this->lesson($sourceSeries, today()->addWeek()->toDateString());

        $targetSeries = $this->series();
        $target = $this->lesson($targetSeries, today()->addWeeks(2)->toDateString());

        LessonAttendance::create([
            'enrollment_id' => $enrollment->getKey(),
            'lesson_id' => $target->getKey(),
            'attended' => false,
        ]);

        $this->expectException(SubstituteException::class);

        app(MoveClientToLesson::class)($client, $target, $source);
    }

    public function test_move_mints_no_token(): void
    {
        $sourceSeries = $this->series();
        $client = User::factory()->customer()->create();
        $this->enrollment($sourceSeries, $client);
        $source = $this->lesson($sourceSeries, today()->addWeek()->toDateString());

        $target = $this->lesson($this->series(), today()->addWeeks(2)->toDateString());

        app(MoveClientToLesson::class)($client, $target, $source);

        $this->assertSame(0, SubstituteToken::query()->count());
    }

    public function test_a_manual_placement_decrements_free_spots(): void
    {
        $sourceSeries = $this->series();
        $client = User::factory()->customer()->create();
        $this->enrollment($sourceSeries, $client);
        $source = $this->lesson($sourceSeries, today()->addWeek()->toDateString());

        $targetSeries = $this->series();
        $targetSeries->update(['capacity' => 5]);
        $target = $this->lesson($targetSeries, today()->addWeeks(2)->toDateString());

        $before = app(SubstituteOptions::class)->freeSpots($target);

        app(MoveClientToLesson::class)($client, $target, $source);

        $this->assertSame($before - 1, app(SubstituteOptions::class)->freeSpots($target));
    }

    public function test_the_roster_action_moves_the_client_and_notifies(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $sourceSeries = $this->series();
        $client = User::factory()->customer()->create();
        $enrollment = $this->enrollment($sourceSeries, $client);
        $source = $this->lesson($sourceSeries, today()->addWeek()->toDateString());

        $targetSeries = $this->series();
        $target = $this->lesson($targetSeries, today()->addWeeks(2)->toDateString());

        Livewire::test(AttendancesRelationManager::class, [
            'ownerRecord' => $target,
            'pageClass' => ViewLesson::class,
        ])
            ->callAction(TestAction::make('moveClientIntoLesson')->table(), [
                'client_id' => $client->getKey(),
                'source_lesson_id' => $source->getKey(),
                'notify_client' => true,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertDatabaseHas('lesson_attendances', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $target->id,
        ]);

        Notification::assertSentTo(
            $client,
            SubstituteTokenNotification::class,
            fn (SubstituteTokenNotification $notification): bool => $notification->key === EmailTemplateKey::SubstituteManualMove
                && $notification->tokens['puvodni_lekce'] === $source->startsAt()->format('j. n. Y · H:i')
                && $notification->tokens['nova_lekce'] === $target->startsAt()->format('j. n. Y · H:i'),
        );
    }
}
