<?php

namespace Tests\Feature\Zone;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Livewire\Zone\Courses;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\SubstituteToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Moje kurzy": the per-lesson excuse now runs through a confirmation modal
 * (no accidental excuse on a misclick) and lands the client on the substitute
 * entries page once a token is minted. Unpaid sign-ups link to the pay modal.
 */
class ZoneCoursesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_the_excuse_button_only_arms_the_confirmation_and_never_excuses_directly(): void
    {
        [$enrollment, $lesson] = $this->enrollmentWithUpcomingLesson();

        Livewire::actingAs($enrollment->client)
            ->test(Courses::class)
            ->call('confirmExcuse', $enrollment->getKey(), $lesson->getKey())
            ->assertSet('confirmingExcuseLessonId', $lesson->getKey())
            ->assertSee('Omluvit se z lekce?');

        // Arming the modal must not excuse: no token, attendance stays present.
        $this->assertSame(0, SubstituteToken::query()->count());
        $this->assertNull(
            $enrollment->attendances()->where('lesson_id', $lesson->getKey())->value('cancelled_at'),
        );
    }

    public function test_a_confirmed_timely_excuse_mints_a_token_and_redirects_to_the_substitute_entries(): void
    {
        [$enrollment, $lesson] = $this->enrollmentWithUpcomingLesson();

        Livewire::actingAs($enrollment->client)
            ->test(Courses::class)
            ->call('confirmExcuse', $enrollment->getKey(), $lesson->getKey())
            ->call('excuseFromLesson')
            ->assertRedirect(route('zone.tokens'))
            ->assertSet('confirmingExcuseLessonId', null);

        $this->assertSame(1, SubstituteToken::query()->count());
        $this->assertDatabaseHas('lesson_attendances', [
            'enrollment_id' => $enrollment->getKey(),
            'lesson_id' => $lesson->getKey(),
            'token_generated' => true,
        ]);
    }

    public function test_a_confirmed_late_excuse_records_the_absence_without_redirecting(): void
    {
        // Lesson starts in 2 hours — inside the course's 24h early-cancel window.
        [$enrollment, $lesson] = $this->enrollmentWithUpcomingLesson(
            today()->toDateString(),
            now()->addHours(2)->format('H:i:s'),
        );

        Livewire::actingAs($enrollment->client)
            ->test(Courses::class)
            ->call('confirmExcuse', $enrollment->getKey(), $lesson->getKey())
            ->call('excuseFromLesson')
            ->assertNoRedirect()
            ->assertSet('confirmingExcuseLessonId', null);

        $this->assertSame(0, SubstituteToken::query()->count());
        $this->assertDatabaseHas('lesson_attendances', [
            'lesson_id' => $lesson->getKey(),
            'token_generated' => false,
        ]);
    }

    public function test_an_unpaid_enrollment_links_to_the_pay_modal(): void
    {
        [$enrollment] = $this->enrollmentWithUpcomingLesson();

        $payment = $enrollment->payments()->create([
            'client_id' => $enrollment->client_id,
            'amount' => 1200,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => today()->addWeek(),
        ]);

        Livewire::actingAs($enrollment->client)
            ->test(Courses::class)
            ->assertSeeHtml(route('zone.payments', ['platba' => $payment->getKey()]));
    }

    /**
     * @return array{0: CourseEnrollment, 1: Lesson}
     */
    protected function enrollmentWithUpcomingLesson(?string $date = null, string $time = '18:00:00'): array
    {
        $course = Course::factory()->create([
            'published_at' => now(),
            'max_substitutions' => 2,
            'early_cancel_hours' => 24,
        ]);

        $series = CourseSeries::factory()->for($course)->create([
            'start_date' => today()->subWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(8)->toDateString(),
            'capacity' => 10,
            'status' => CourseSeriesStatus::Open,
        ]);

        $lesson = Lesson::factory()->for($series, 'series')->create([
            'lesson_date' => $date ?? today()->addWeek()->toDateString(),
            'start_time' => $time,
            'end_time' => '19:00:00',
        ]);

        $enrollment = CourseEnrollment::factory()->for($series, 'series')->create([
            'client_id' => User::factory()->customer()->create(['email_verified_at' => now()])->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        return [$enrollment, $lesson];
    }
}
