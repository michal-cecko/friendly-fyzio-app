<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Livewire\CourseArchive;
use App\Livewire\OfferSignupForm;
use App\Livewire\WorkshopArchive;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PublicOfferPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function publishedCourse(array $attributes = []): Course
    {
        return Course::factory()->create(['published_at' => now(), ...$attributes]);
    }

    protected function openSeries(Course $course, array $attributes = []): CourseSeries
    {
        return CourseSeries::factory()->for($course)->create([
            'start_date' => today()->addWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(14)->toDateString(),
            'capacity' => 10,
            'price' => 2400,
            'status' => CourseSeriesStatus::Open,
            ...$attributes,
        ]);
    }

    public function test_published_course_detail_renders_with_signup_form(): void
    {
        $course = $this->publishedCourse(['name' => 'Hormonální jóga']);
        $this->openSeries($course);

        $this->get('/kurzy/'.$course->slug)
            ->assertOk()
            ->assertSee('Hormonální jóga')
            ->assertSee('Přihlásit se a zaplatit')
            ->assertSee('Shrnutí objednávky');
    }

    public function test_unpublished_course_is_hidden_from_guests_but_previewable_by_staff(): void
    {
        $course = Course::factory()->create(['published_at' => null]);

        $this->get('/kurzy/'.$course->slug)->assertNotFound();

        $this->actingAs(User::factory()->admin()->create());

        $this->get('/kurzy/'.$course->slug)->assertOk()->assertSee('Náhled');
    }

    public function test_presale_token_unlocks_unpublished_course_page(): void
    {
        $course = Course::factory()->create(['published_at' => null]);
        $series = $this->openSeries($course, ['status' => CourseSeriesStatus::Inactive]);
        $token = $series->ensurePresaleToken();

        $this->get('/kurzy/'.$course->slug.'?predprodej='.$token)
            ->assertOk()
            ->assertSee('Přihlásit se a zaplatit');

        $this->get('/kurzy/'.$course->slug.'?predprodej=chybny-token')->assertNotFound();
    }

    public function test_course_without_open_series_shows_interest_form(): void
    {
        $course = $this->publishedCourse();

        $this->get('/kurzy/'.$course->slug)
            ->assertOk()
            ->assertSee('Připravujeme pro vás nový kurz')
            ->assertSee('Chci vědět první');
    }

    public function test_workshop_detail_renders_and_unpublished_is_hidden(): void
    {
        $workshop = Workshop::factory()->create([
            'workshop_date' => today()->addWeeks(3)->toDateString(),
            'published_at' => now(),
        ]);

        $this->get('/workshopy/'.$workshop->slug)
            ->assertOk()
            ->assertSee($workshop->name)
            ->assertSee('Přihlásit se a zaplatit');

        $draft = Workshop::factory()->create(['published_at' => null]);

        $this->get('/workshopy/'.$draft->slug)->assertNotFound();
    }

    public function test_lesson_detail_renders_and_is_scoped_to_its_course(): void
    {
        $course = $this->publishedCourse();
        $lesson = OneTimeLesson::factory()->for($course)->create([
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'published_at' => now(),
        ]);

        $this->get('/kurzy/'.$course->slug.'/lekce/'.$lesson->getKey())
            ->assertOk()
            ->assertSee($course->name)
            ->assertSee('Rezervovat místo');

        $otherCourse = $this->publishedCourse();

        $this->get('/kurzy/'.$otherCourse->slug.'/lekce/'.$lesson->getKey())->assertNotFound();
    }

    public function test_course_archive_filters_by_type_category_search_and_availability(): void
    {
        $joga = CourseCategory::factory()->create(['name' => 'Jóga', 'slug' => 'joga', 'published_at' => now()]);
        $sm = CourseCategory::factory()->create(['name' => 'SM systém', 'slug' => 'sm-system', 'published_at' => now()]);

        $jogaCourse = $this->publishedCourse(['name' => 'Hormonální jóga', 'category_id' => $joga->id]);
        $this->openSeries($jogaCourse);

        $smCourse = $this->publishedCourse(['name' => 'SM cvičení', 'category_id' => $sm->id]);
        $fullSeries = $this->openSeries($smCourse, ['capacity' => 1]);
        $fullSeries->enrollments()->create([
            'client_id' => User::factory()->customer()->create()->id,
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => 'paid',
        ]);

        $draft = Course::factory()->create(['name' => 'Skrytý kurz', 'published_at' => null]);

        Livewire::test(CourseArchive::class)
            ->assertSee('Hormonální jóga')
            ->assertSee('SM cvičení')
            ->assertDontSee('Skrytý kurz')
            ->set('category', 'joga')
            ->assertSee('Hormonální jóga')
            ->assertDontSee('SM cvičení')
            ->set('category', null)
            ->set('search', 'hormonální')
            ->assertSee('Hormonální jóga')
            ->assertDontSee('SM cvičení')
            ->set('search', '')
            ->set('availableOnly', true)
            ->assertSee('Hormonální jóga')
            ->assertDontSee('SM cvičení');
    }

    public function test_course_archive_lesson_type_lists_upcoming_published_lessons(): void
    {
        $course = $this->publishedCourse(['name' => 'Jin jóga']);

        OneTimeLesson::factory()->for($course)->create([
            'lesson_date' => today()->addWeek()->toDateString(),
            'published_at' => now(),
        ]);
        OneTimeLesson::factory()->for($course)->create([
            'lesson_date' => today()->subWeek()->toDateString(),
            'published_at' => now(),
        ]);

        Livewire::test(CourseArchive::class)
            ->set('type', 'lekce')
            ->assertSee('Jin jóga')
            ->assertSee('Zobrazeno 1');
    }

    public function test_workshop_archive_searches_and_shows_past_muted(): void
    {
        Workshop::factory()->create([
            'name' => 'Workshop zdravých zad',
            'workshop_date' => today()->addWeeks(2)->toDateString(),
            'published_at' => now(),
        ]);
        Workshop::factory()->create([
            'name' => 'Proběhlý seminář',
            'workshop_date' => today()->subWeeks(2)->toDateString(),
            'published_at' => now(),
        ]);

        Livewire::test(WorkshopArchive::class)
            ->assertSee('Workshop zdravých zad')
            ->assertSee('Proběhlé workshopy')
            ->assertSee('Proběhlý seminář')
            ->set('search', 'zdravých')
            ->assertSee('Workshop zdravých zad')
            ->assertDontSee('Proběhlý seminář');
    }

    public function test_signup_form_component_submits_and_validates_terms(): void
    {
        Notification::fake();

        $series = $this->openSeries($this->publishedCourse());

        Livewire::test(OfferSignupForm::class, ['offerType' => 'series', 'offerId' => $series->getKey()])
            ->set('name', 'Jana Formulářová')
            ->set('email', 'jana.form@example.cz')
            ->set('phone', '+420604123456')
            ->call('submit')
            ->assertHasErrors(['terms'])
            ->set('terms', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('completed', 'signup');

        $this->assertTrue($series->enrollments()->whereHas('client', fn ($query) => $query->where('email', 'jana.form@example.cz'))->exists());
    }

    public function test_signup_form_waitlist_join_and_leave(): void
    {
        Notification::fake();

        $series = $this->openSeries($this->publishedCourse(), ['capacity' => 1]);
        $series->enrollments()->create([
            'client_id' => User::factory()->customer()->create()->id,
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => 'paid',
        ]);

        $component = Livewire::test(OfferSignupForm::class, ['offerType' => 'series', 'offerId' => $series->getKey()])
            ->assertSee('Přidat se na čekací listinu')
            ->set('waitlistName', 'Klára Čekající')
            ->set('waitlistEmail', 'klara.cekajici@example.cz')
            ->call('joinWaitlist')
            ->assertHasNoErrors()
            ->assertSet('completed', 'waitlist');

        $this->assertSame(1, $series->waitlistEntries()->count());

        $component->call('leaveWaitlist')->assertSet('completed', null);

        $this->assertSame(0, $series->waitlistEntries()->count());
    }

    public function test_signup_form_restores_waitlist_state_from_cookie_for_returning_visitor(): void
    {
        $series = $this->openSeries($this->publishedCourse(), ['capacity' => 1]);
        $series->enrollments()->create([
            'client_id' => User::factory()->customer()->create()->id,
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => 'paid',
        ]);

        $entry = $series->waitlistEntries()->create([
            'name' => 'Klára Čekající',
            'email' => 'klara.cekajici@example.cz',
        ]);

        Livewire::withCookie("waitlist_series_{$series->getKey()}", (string) $entry->getKey())
            ->test(OfferSignupForm::class, ['offerType' => 'series', 'offerId' => $series->getKey()])
            ->assertSet('completed', 'waitlist')
            ->assertSet('waitlistEntryId', (string) $entry->getKey())
            ->assertSet('waitlistEmail', 'klara.cekajici@example.cz');
    }

    public function test_signup_form_ignores_stale_waitlist_cookie(): void
    {
        $series = $this->openSeries($this->publishedCourse(), ['capacity' => 1]);
        $series->enrollments()->create([
            'client_id' => User::factory()->customer()->create()->id,
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => 'paid',
        ]);

        // Cookie points at an entry that no longer exists — the form must show again.
        Livewire::withCookie("waitlist_series_{$series->getKey()}", (string) Str::uuid())
            ->test(OfferSignupForm::class, ['offerType' => 'series', 'offerId' => $series->getKey()])
            ->assertSet('completed', null)
            ->assertSet('waitlistEntryId', null);
    }

    public function test_course_archive_lists_each_public_series_and_hides_private_ones(): void
    {
        $course = $this->publishedCourse(['name' => 'Kurz s více běhy']);
        $this->openSeries($course, ['name' => 'Podzimní běh']);
        $this->openSeries($course, [
            'name' => 'Jarní běh',
            'start_date' => today()->addWeeks(20)->toDateString(),
            'end_date' => today()->addWeeks(32)->toDateString(),
        ]);
        $this->openSeries($course, ['name' => 'Tajný běh', 'visibility' => CourseSeriesVisibility::Private]);

        Livewire::test(CourseArchive::class)
            ->assertSee('Podzimní běh')
            ->assertSee('Jarní běh')
            ->assertDontSee('Tajný běh')
            ->assertSee('Zobrazeno 2');
    }

    public function test_termin_param_preselects_the_requested_series_and_ignores_private_ones(): void
    {
        $course = $this->publishedCourse();
        $autumn = $this->openSeries($course, ['name' => 'Podzimní běh']);
        $spring = $this->openSeries($course, [
            'name' => 'Jarní běh',
            'start_date' => today()->addWeeks(20)->toDateString(),
            'end_date' => today()->addWeeks(32)->toDateString(),
        ]);
        $private = $this->openSeries($course, ['name' => 'Tajný běh', 'visibility' => CourseSeriesVisibility::Private]);

        // The archive card link opens its exact run…
        $this->get('/kurzy/'.$course->slug.'?termin='.$spring->getKey())
            ->assertOk()
            ->assertSee($spring->getKey());

        // …while a private id quietly falls back to the default run.
        $this->get('/kurzy/'.$course->slug.'?termin='.$private->getKey())
            ->assertOk()
            ->assertDontSee($private->getKey())
            ->assertSee($autumn->getKey());
    }

    public function test_private_series_is_hidden_publicly_but_opens_via_invite_link(): void
    {
        $course = $this->publishedCourse(['name' => 'Kurz jen pro zvané']);
        $private = $this->openSeries($course, ['name' => 'Tajný běh', 'visibility' => CourseSeriesVisibility::Private]);

        Livewire::test(CourseArchive::class)
            ->assertDontSee('Tajný běh')
            ->assertSee('Připravované kurzy')
            ->assertSee('Kurz jen pro zvané');

        $this->get('/kurzy/'.$course->slug)
            ->assertOk()
            ->assertSee('Chci vědět první');

        $this->get('/kurzy/'.$course->slug.'?predprodej='.$private->ensurePresaleToken())
            ->assertOk()
            ->assertSee('Přihlásit se a zaplatit');
    }

    public function test_preparing_tail_shows_only_on_the_unfiltered_first_page(): void
    {
        $open = $this->publishedCourse(['name' => 'Otevřený kurz']);
        $this->openSeries($open);

        $preparing = $this->publishedCourse(['name' => 'Chystaný kurz']);
        $this->openSeries($preparing, ['status' => CourseSeriesStatus::Inactive]);

        Livewire::test(CourseArchive::class)
            ->assertSee('Připravované kurzy')
            ->assertSee('Chystaný kurz')
            ->set('availableOnly', true)
            ->assertDontSee('Připravované kurzy')
            ->assertDontSee('Chystaný kurz');
    }

    public function test_course_archive_paginates_six_series_per_page(): void
    {
        foreach (range(1, 7) as $week) {
            $course = $this->publishedCourse(['name' => "Kurz číslo {$week}"]);
            $this->openSeries($course, [
                'start_date' => today()->addWeeks($week)->toDateString(),
                'end_date' => today()->addWeeks($week + 12)->toDateString(),
            ]);
        }

        Livewire::test(CourseArchive::class)
            ->assertSee('Kurz číslo 1')
            ->assertDontSee('Kurz číslo 7')
            ->call('gotoPage', 2, 'strana')
            ->assertSee('Kurz číslo 7')
            ->assertDontSee('Kurz číslo 1');
    }

    public function test_workshop_archive_available_only_hides_full_workshops(): void
    {
        Workshop::factory()->create([
            'name' => 'Volný workshop',
            'workshop_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 8,
            'published_at' => now(),
        ]);

        $full = Workshop::factory()->create([
            'name' => 'Plný workshop',
            'workshop_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 1,
            'published_at' => now(),
        ]);

        // A pending registration holds the spot, so it must count as taken.
        WorkshopRegistration::factory()->for($full)->create(['status' => BookingStatus::Pending]);

        Livewire::test(WorkshopArchive::class)
            ->assertSee('Volný workshop')
            ->assertSee('Plný workshop')
            ->set('availableOnly', true)
            ->assertSee('Volný workshop')
            ->assertDontSee('Plný workshop');
    }

    public function test_workshop_archive_paginates_six_per_page(): void
    {
        foreach (range(1, 7) as $week) {
            Workshop::factory()->create([
                'name' => "Workshop číslo {$week}",
                'workshop_date' => today()->addWeeks($week)->toDateString(),
                'published_at' => now(),
            ]);
        }

        Livewire::test(WorkshopArchive::class)
            ->assertSee('Workshop číslo 1')
            ->assertDontSee('Workshop číslo 7')
            ->call('gotoPage', 2, 'strana')
            ->assertSee('Workshop číslo 7')
            ->assertDontSee('Workshop číslo 1');
    }
}
