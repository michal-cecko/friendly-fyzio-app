<?php

namespace Tests\Feature;

use App\Console\Commands\CoursesImport;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CoursesImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // The staff who teach must already exist; the import grants them Lecturer.
        User::factory()->admin()->therapist()->create([
            'name' => 'Lucie Fickerová',
            'email' => 'lucie.fickerova@friendlyfyzio.cz',
        ]);
        User::factory()->therapist()->create([
            'name' => 'Ema Murčová',
            'email' => 'ema.murcova@friendlyfyzio.cz',
        ]);
        User::factory()->therapist()->create([
            'name' => 'Denisa Nováková',
            'email' => 'denisa.novakova@friendlyfyzio.cz',
        ]);

        // A client-lecturer already exists as a customer, uniquely named.
        User::factory()->customer()->create([
            'name' => 'Anna Fančovičová',
            'email' => 'anna.client@example.com',
        ]);

        // A roster client already on file, matched by e-mail (not recreated).
        User::factory()->customer()->create([
            'name' => 'Existing Client',
            'email' => 'existing.client@example.com',
        ]);
    }

    protected function runImport(bool $dryRun = false): void
    {
        $this->artisan('courses:import', array_filter([
            'path' => 'tests/Fixtures/googlesheets',
            '--dry-run' => $dryRun,
        ]))->assertSuccessful();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->runImport(dryRun: true);

        $this->assertSame(0, Course::query()->count());
        $this->assertSame(0, CourseSeries::query()->count());
        $this->assertSame(0, CourseEnrollment::query()->count());
        $this->assertSame(0, Lesson::query()->count());
    }

    public function test_creates_categories_and_courses_with_lecturer_qualified_slugs(): void
    {
        $this->runImport();

        $this->assertDatabaseHas(CourseCategory::class, ['slug' => 'pro-tehotne', 'name' => 'Pro těhotné']);
        $this->assertDatabaseHas(CourseCategory::class, ['slug' => 'ostatni', 'name' => 'Ostatní']);

        // A course name taught by two lecturers is split into two courses, each
        // with a lecturer-qualified slug so the unique slug never collides.
        $this->assertDatabaseHas(Course::class, ['slug' => 'kondicni-cviceni-pro-tehotne-novakova']);
        $this->assertDatabaseHas(Course::class, ['slug' => 'kondicni-cviceni-pro-tehotne-fickerova']);

        // A single-lecturer course keeps the bare slug.
        $this->assertDatabaseHas(Course::class, ['slug' => 'principy-pohybu-naucne-pohybove']);
    }

    public function test_historical_courses_and_workshops_stay_unpublished(): void
    {
        $this->runImport();

        // The whole term has ended, so nothing may surface on the public
        // archive — neither the grid nor the "Připravujeme" tail.
        $this->assertSame(0, Course::query()->published()->count());
        $this->assertSame(0, Lesson::query()->published()->count());
        $this->assertGreaterThan(0, Course::query()->count());
        $this->assertGreaterThan(0, Lesson::query()->count());
    }

    public function test_series_are_historical_and_inactive_with_generated_lessons(): void
    {
        $this->runImport();

        $principy = CourseSeries::query()
            ->whereHas('course', fn ($q) => $q->where('slug', 'principy-pohybu-naucne-pohybove'))
            ->firstOrFail();

        $this->assertSame(CourseSeriesStatus::Inactive, $principy->status);
        $this->assertSame(1500, $principy->price);
        $this->assertSame(7, $principy->capacity);
        $this->assertSame('2025-09-30', $principy->start_date->toDateString());
        $this->assertSame(5, $principy->lessons()->count());
    }

    public function test_skip_week_is_omitted_but_lesson_count_is_preserved(): void
    {
        $this->runImport();

        $series = CourseSeries::query()
            ->whereHas('course', fn ($q) => $q->where('slug', 'sm-system-pro-tehotne'))
            ->firstOrFail();

        // 13 lessons, weekly from 4. 9., with 9. 10. skipped ("9. října nebude").
        $this->assertSame(13, $series->lessons()->count());
        $this->assertSame(0, $series->lessons()->whereDate('lesson_date', '2025-10-09')->count());
        $this->assertSame(1, $series->lessons()->whereDate('lesson_date', '2025-10-16')->count());
    }

    public function test_multi_track_course_generates_lessons_on_both_weekdays(): void
    {
        $this->runImport();

        $series = CourseSeries::query()
            ->whereHas('course', fn ($q) => $q->where('slug', 'mamimimi'))
            ->firstOrFail();

        $this->assertSame(10, $series->lessons()->count());

        $weekdays = $series->lessons()->get()->map(fn (Lesson $l): int => $l->lesson_date->dayOfWeek)->unique();
        $this->assertEqualsCanonicalizing([3, 4], $weekdays->all()); // Wednesday + Thursday
    }

    public function test_workshops_store_lower_price_tier_with_full_wording(): void
    {
        $this->runImport();

        $event = Lesson::query()->where('name', 'Pánevní dno – fyzická rovina')->firstOrFail();

        $this->assertSame(1000, $event->price); // lower tier
        $this->assertStringContainsString('1 800 Kč', (string) $event->description);
        $this->assertStringContainsString('za 1 blok / 2 bloky', (string) $event->description);
        $this->assertSame('2025-09-28', $event->lesson_date->toDateString());
        $this->assertTrue($event->isPast());
    }

    public function test_external_rental_lecturer_rows_are_skipped(): void
    {
        $this->runImport();

        // Lucie Amani only rents a room for her own courses: her rows must not
        // be imported, and she must never be given an account.
        $this->assertSame(0, Lesson::query()->where('name', 'Pánevní dno – emoční rovina')->count());
        $this->assertSame(0, User::query()->where('email', 'lucie.amani@friendlyfyzio.cz')->count());
        $this->assertSame(0, User::query()->where('name', 'Lucie Amani')->count());
    }

    public function test_multi_lecturer_workshop_uses_primary_and_notes_the_rest(): void
    {
        $this->runImport();

        $event = Lesson::query()->where('name', 'VBAC s jistotou a péčí')->firstOrFail();
        $anna = User::query()->where('name', 'Anna Fančovičová')->firstOrFail();

        $this->assertSame($anna->getKey(), $event->instructor_id);
        $this->assertStringContainsString('Lucie Fickerová', (string) $event->description);
        $this->assertStringContainsString('Anna Fančovičová', (string) $event->description);
    }

    public function test_existing_staff_gain_lecturer_and_new_lecturers_are_created_unpublished(): void
    {
        $this->runImport();

        $ema = User::query()->where('email', 'ema.murcova@friendlyfyzio.cz')->firstOrFail();
        $this->assertTrue($ema->isLecturer());
        $this->assertTrue($ema->isTherapist());

        // A brand-new lecturer: lecturer-only, with an unpublished (hidden) profile.
        $eliska = User::query()->where('name', 'Eliška Marunová')->firstOrFail();
        $this->assertTrue($eliska->isLecturer());
        $this->assertFalse($eliska->isCustomer());
        $this->assertSame('Mgr.', $eliska->title_before);
        $this->assertNotNull($eliska->staffProfile);
        $this->assertFalse($eliska->staffProfile->isPublished());
    }

    public function test_client_lecturer_keeps_customer_identity_and_gains_lecturer(): void
    {
        $this->runImport();

        $anna = User::query()->where('name', 'Anna Fančovičová')->firstOrFail();

        $this->assertTrue($anna->isLecturer());
        $this->assertTrue($anna->isCustomer());
        // Matched, not duplicated.
        $this->assertSame(1, User::query()->where('name', 'Anna Fančovičová')->count());
    }

    public function test_enrollments_match_by_email_and_create_placeholders(): void
    {
        $this->runImport();

        $principy = CourseSeries::query()
            ->whereHas('course', fn ($q) => $q->where('slug', 'principy-pohybu-naucne-pohybove'))
            ->firstOrFail();

        // Matched by e-mail — the existing account is reused, none created.
        $existing = User::query()->where('email', 'existing.client@example.com')->firstOrFail();
        $enrollment = CourseEnrollment::query()
            ->where('series_id', $principy->getKey())
            ->where('client_id', $existing->getKey())
            ->firstOrFail();

        $this->assertSame(CourseEnrollmentStatus::Active, $enrollment->status);
        $this->assertSame(PaymentStatus::Unpaid, $enrollment->payment_status);
        $this->assertNull($enrollment->paid_at);

        // An unknown holder with an e-mail becomes a tagged placeholder customer.
        $nova = User::query()->where('email', 'nova.kurzistka@example.com')->firstOrFail();
        $this->assertTrue($nova->isCustomer());
        $this->assertTrue($nova->tags->pluck('name')->contains(CoursesImport::IMPORT_TAG));
        $this->assertDatabaseHas(CourseEnrollment::class, [
            'series_id' => $principy->getKey(),
            'client_id' => $nova->getKey(),
        ]);
    }

    public function test_roster_only_course_is_built_and_unnamed_block_is_not_enrolled(): void
    {
        $this->runImport();

        // "Restart po císařském řezu" isn't in the catalogue — created from the roster.
        $restart = CourseSeries::query()
            ->whereHas('course', fn ($q) => $q->where('slug', 'restart-po-cisarskem-rezu'))
            ->firstOrFail();

        $this->assertSame(5, $restart->lessons()->count());
        $this->assertSame(2, $restart->enrollments()->count()); // Restart Person + trailing

        // The unnamed left-hand block carries no course, so nobody is enrolled.
        $this->assertSame(0, User::query()->where('name', 'Michaela Muroňová')->count());
        $this->assertSame(0, User::query()->where('name', 'Kateřina Zdráhalová')->count());
    }

    public function test_import_is_idempotent_and_sends_no_mail(): void
    {
        $this->runImport();
        $this->runImport();

        $this->assertSame(1, User::query()->where('email', 'nova.kurzistka@example.com')->count());
        $this->assertSame(4, CourseEnrollment::query()->count());
        $this->assertSame(1, Lesson::query()->where('name', 'VBAC s jistotou a péčí')->count());

        Notification::assertNothingSent();
    }
}
