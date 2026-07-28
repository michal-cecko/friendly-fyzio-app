<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\EmailTemplateKey;
use App\Enums\ExamType;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Livewire\ReservationWizard;
use App\Models\Reservation;
use App\Models\ReservationDayWaitlistEntry;
use App\Models\Room;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationWizardTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    private ServiceCategory $category;

    private Service $service;

    private StaffProfile $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = Carbon::today()->addWeeks(8)->startOfWeek(Carbon::MONDAY);
        $room = Room::factory()->create();

        $this->category = ServiceCategory::factory()->create(['type' => ServiceType::Massage, 'published_at' => now()]);
        $this->service = Service::factory()->create([
            'category_id' => $this->category->id,
            'duration_minutes' => 60,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);

        $this->therapist = StaffProfile::factory()->create(['published_at' => now()]);
        $this->service->therapists()->attach($this->therapist);

        TherapistWorkBlock::factory()->create([
            'therapist_id' => $this->therapist->id,
            'room_id' => $room->id,
            'work_date' => $this->date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_wizard_page_renders(): void
    {
        $this->get(route('reservation.wizard'))
            ->assertOk()
            ->assertSee('Rezervace');
    }

    public function test_stepper_hides_inactive_labels_on_mobile(): void
    {
        // On mobile the six-step indicator only fits when non-active steps collapse
        // to their numbered circle; their text label is hidden until the sm breakpoint.
        Livewire::test(ReservationWizard::class)
            ->assertSeeHtml('hidden sm:inline');
    }

    public function test_advancing_a_step_dispatches_scroll_to_top(): void
    {
        // Step content height varies, so navigating fires a browser event the view
        // listens for to scroll back to the top on mobile.
        StaffProfile::factory()->create(['published_at' => now()]);

        Livewire::test(ReservationWizard::class)
            ->call('selectCategory', $this->category->slug)
            ->call('next')
            ->assertDispatched('wizard-step-changed')
            ->call('back')
            ->assertDispatched('wizard-step-changed');
    }

    public function test_happy_path_creates_a_reservation_and_account(): void
    {
        Notification::fake();

        // A second therapist on the same service keeps the therapist step a real
        // choice (a lone candidate is auto-selected), so this exercises the full
        // manual flow.
        $this->service->therapists()->attach(StaffProfile::factory()->create(['published_at' => now()]));

        Livewire::test(ReservationWizard::class)
            ->assertSet('stepIndex', 0)
            ->call('selectCategory', $this->category->slug)
            ->call('next')
            ->call('selectService', $this->service->slug)
            ->call('next')
            ->call('selectTherapist', $this->therapist->slug)
            ->call('next')
            ->call('selectDate', $this->date->toDateString())
            ->call('next')
            ->call('selectTime', '08:00')
            ->call('next')
            ->set('firstName', 'Jana')
            ->set('lastName', 'Nováková')
            ->set('email', 'jana@example.com')
            ->set('phone', '+420604793255')
            ->set('agreeCancellation', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('confirmationId', fn ($value): bool => $value !== null);

        $this->assertDatabaseHas(Reservation::class, [
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'start_time' => '08:00:00',
            'status' => 'pending',
        ]);

        $client = User::where('email', 'jana@example.com')->sole();
        $this->assertTrue($client->isCustomer());
        Notification::assertSentTo(
            $client,
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationCreated,
        );
    }

    public function test_category_deep_link_starts_in_category_first_order(): void
    {
        $component = Livewire::withQueryParams(['kategorie' => $this->category->slug])
            ->test(ReservationWizard::class);

        $this->assertSame('category', $component->instance()->stepOrder()[0]);
        $this->assertSame('service', $component->instance()->currentStep());
    }

    public function test_service_deep_link_preselects_single_therapist_but_keeps_step(): void
    {
        // The service has exactly one therapist: it's preselected, but the wizard still
        // stops on the therapist step so the user sees who they'll be booked with.
        $component = Livewire::withQueryParams(['sluzba' => $this->service->slug])
            ->test(ReservationWizard::class);
        $instance = $component->instance();

        $this->assertSame($this->category->slug, $instance->categorySlug);
        $this->assertSame($this->therapist->slug, $instance->therapistSlug);
        $this->assertSame('therapist', $instance->currentStep());
    }

    public function test_therapist_step_shown_when_multiple_qualify(): void
    {
        $other = StaffProfile::factory()->create(['published_at' => now()]);
        $this->service->therapists()->attach($other);

        $component = Livewire::withQueryParams(['sluzba' => $this->service->slug])
            ->test(ReservationWizard::class);
        $instance = $component->instance();

        $this->assertNull($instance->therapistSlug);
        $this->assertSame('therapist', $instance->currentStep());
    }

    public function test_unpublished_therapist_is_offered_in_the_wizard(): void
    {
        // Publishing gates the public team page and profile detail only — an
        // unpublished profile must still be bookable through the wizard.
        $unpublished = StaffProfile::factory()->create(['published_at' => null]);
        $this->service->therapists()->attach($unpublished);

        $component = Livewire::withQueryParams(['sluzba' => $this->service->slug])
            ->test(ReservationWizard::class);

        $this->assertTrue(
            $component->instance()->therapists->pluck('id')->contains($unpublished->id),
        );
    }

    public function test_lecturer_with_a_linked_service_is_not_offered(): void
    {
        // A lecturer (or the assistant) can have a service link but does not do
        // 1:1 therapy — only holders of the Therapist capability are bookable.
        $lecturerProfile = StaffProfile::factory()->create(['published_at' => now()]);
        $lecturerProfile->user->syncCapabilities([Capability::Lecturer]);
        $this->service->therapists()->attach($lecturerProfile);

        $component = Livewire::withQueryParams(['sluzba' => $this->service->slug])
            ->test(ReservationWizard::class);

        $offered = $component->instance()->therapists->pluck('id');
        $this->assertFalse($offered->contains($lecturerProfile->id));
        $this->assertTrue($offered->contains($this->therapist->id));
    }

    public function test_service_without_a_therapist_is_not_offered(): void
    {
        // A service nobody performs is a dead end (its calendar is always empty), so
        // it must not appear in the service step.
        $orphan = Service::factory()->create([
            'category_id' => $this->category->id,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);

        $ids = Livewire::withQueryParams(['kategorie' => $this->category->slug])
            ->test(ReservationWizard::class)
            ->instance()->services->pluck('id');

        $this->assertTrue($ids->contains($this->service->id));
        $this->assertFalse($ids->contains($orphan->id));
    }

    public function test_category_without_a_bookable_service_is_hidden(): void
    {
        // A published category whose services are all unorderable leads nowhere.
        $emptyCategory = ServiceCategory::factory()->create(['type' => ServiceType::Massage, 'published_at' => now()]);
        Service::factory()->create([
            'category_id' => $emptyCategory->id,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);

        $ids = Livewire::test(ReservationWizard::class)->instance()->categories->pluck('id');

        $this->assertTrue($ids->contains($this->category->id));
        $this->assertFalse($ids->contains($emptyCategory->id));
    }

    public function test_therapist_without_a_bookable_service_is_not_offered(): void
    {
        // The mirror of the service rule: a therapist who performs nothing bookable
        // is a dead end in the picker.
        $orphan = StaffProfile::factory()->create(['published_at' => now()]);

        $ids = Livewire::test(ReservationWizard::class)->instance()->therapists->pluck('id');

        $this->assertTrue($ids->contains($this->therapist->id));
        $this->assertFalse($ids->contains($orphan->id));
    }

    public function test_gated_kontrolni_selection_does_not_leave_vstupni_active(): void
    {
        // A guest picks Vstupní (committed), then Kontrolní — which opens the login
        // gate WITHOUT committing the exam type. Only Kontrolní may render selected;
        // the previously-committed Vstupní must not stay active too.
        $wizard = Livewire::test(ReservationWizard::class)
            ->call('selectExamType', ExamType::Vstupni->value)
            ->assertSet('examType', ExamType::Vstupni->value)
            ->call('selectExamType', ExamType::Kontrolni->value)
            ->assertSet('gate', 'login');

        $instance = $wizard->instance();
        $this->assertTrue($instance->isExamTypeSelected(ExamType::Kontrolni));
        $this->assertFalse($instance->isExamTypeSelected(ExamType::Vstupni));
    }

    public function test_therapist_deep_link_prefills_and_starts_therapist_first(): void
    {
        StaffProfile::factory()->create(['published_at' => now()]);

        $component = Livewire::withQueryParams(['terapeut' => $this->therapist->slug])
            ->test(ReservationWizard::class);
        $instance = $component->instance();

        $this->assertSame('therapist', $instance->stepOrder()[0]);
        $this->assertSame($this->therapist->slug, $instance->therapistSlug);
        $this->assertSame('category', $instance->currentStep());
    }

    public function test_default_starts_in_category_first_order(): void
    {
        $component = Livewire::test(ReservationWizard::class);
        $instance = $component->instance();

        $this->assertSame('category', $instance->stepOrder()[0]);
        $this->assertSame('category', $instance->currentStep());
    }

    public function test_therapist_deep_link_with_a_service_stays_category_first(): void
    {
        // Enough is already answered that leading with the therapist makes no sense —
        // the deep-linked therapist just arrives preselected on their own step.
        $component = Livewire::withQueryParams([
            'terapeut' => $this->therapist->slug,
            'sluzba' => $this->service->slug,
        ])->test(ReservationWizard::class);
        $instance = $component->instance();

        $this->assertSame('category', $instance->stepOrder()[0]);
        $this->assertSame($this->therapist->slug, $instance->therapistSlug);
    }

    public function test_last_minute_deep_link_keeps_therapist_first(): void
    {
        // The Last-minute brick links a therapist plus a concrete slot, no service.
        $component = Livewire::withQueryParams([
            'terapeut' => $this->therapist->slug,
            'datum' => $this->date->toDateString(),
            'cas' => '08:00',
        ])->test(ReservationWizard::class);

        $this->assertSame('therapist', $component->instance()->stepOrder()[0]);
    }

    public function test_changing_the_service_clears_the_chosen_therapist(): void
    {
        // The therapist is picked after the service now, so a different service must
        // not keep a therapist who may not even offer it.
        $other = Service::factory()->create([
            'category_id' => $this->category->id,
            'duration_minutes' => 30,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);
        $second = StaffProfile::factory()->create(['published_at' => now()]);
        $this->service->therapists()->attach($second);
        $other->therapists()->attach($second);

        $component = Livewire::test(ReservationWizard::class)
            ->call('selectCategory', $this->category->slug)
            ->call('next')
            ->call('selectService', $this->service->slug)
            ->call('next')
            ->call('selectTherapist', $this->therapist->slug)
            ->call('goToStep', 1)
            ->call('selectService', $other->slug)
            ->assertSet('therapistSlug', null);

        // Only one therapist offers the new service, so the step preselects them.
        $component->call('next');
        $this->assertSame($second->slug, $component->instance()->therapistSlug);
    }

    public function test_hidden_services_are_not_listed(): void
    {
        $hidden = Service::factory()->create([
            'category_id' => $this->category->id,
            'visibility' => ServiceVisibility::Hidden,
            'published_at' => now(),
        ]);
        $hidden->therapists()->attach($this->therapist);

        $services = Livewire::test(ReservationWizard::class)
            ->set('therapistSlug', $this->therapist->slug)
            ->set('categorySlug', $this->category->slug)
            ->instance()
            ->services();

        $this->assertTrue($services->contains('id', $this->service->id));
        $this->assertFalse($services->contains('id', $hidden->id));
    }

    public function test_submit_requires_consent(): void
    {
        Livewire::test(ReservationWizard::class)
            ->set('categorySlug', $this->category->slug)
            ->set('serviceSlug', $this->service->slug)
            ->set('therapistSlug', $this->therapist->slug)
            ->set('date', $this->date->toDateString())
            ->set('startTime', '08:00')
            ->set('firstName', 'Jana')
            ->set('lastName', 'Nováková')
            ->set('email', 'jana@example.com')
            ->set('phone', '+420604793255')
            ->set('agreeCancellation', false)
            ->call('submit')
            ->assertHasErrors(['agreeCancellation']);

        $this->assertSame(0, Reservation::count());
    }

    /**
     * A physiotherapy category with a public "Vstupní" service and a clients-only
     * "Kontrolní" service (12-month recency window), both offered by the therapist.
     *
     * @return ServiceCategory the physiotherapy category
     */
    private function physioCategory(): ServiceCategory
    {
        $category = ServiceCategory::factory()->create(['type' => ServiceType::Physiotherapy, 'published_at' => now()]);

        $vstupni = Service::factory()->create([
            'category_id' => $category->id,
            'name' => 'Vstupní vyšetření aparátu',
            'slug' => 'vstupni-vysetreni-aparatu',
            'exam_type' => ExamType::Vstupni,
            'duration_minutes' => 90,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);

        $kontrolni = Service::factory()->create([
            'category_id' => $category->id,
            'name' => 'Kontrolní terapie aparátu',
            'slug' => 'kontrolni-terapie-aparatu',
            'exam_type' => ExamType::Kontrolni,
            'duration_minutes' => 60,
            'visibility' => ServiceVisibility::Clients,
            'existing_client_months' => 12,
            'published_at' => now(),
        ]);

        $vstupni->therapists()->attach($this->therapist);
        $kontrolni->therapists()->attach($this->therapist);

        return $category;
    }

    public function test_physio_substep_filters_services_by_exam_type(): void
    {
        $physio = $this->physioCategory();

        Livewire::test(ReservationWizard::class)
            ->call('selectCategory', $physio->slug)
            ->call('next')
            ->assertSee('Typ vyšetření')
            ->call('selectExamType', ExamType::Vstupni->value)
            ->assertSet('examType', ExamType::Vstupni->value)
            ->assertSee('Vstupní vyšetření aparátu')
            ->assertDontSee('Kontrolní terapie aparátu');
    }

    public function test_kontrolni_exam_type_requires_login_for_guests(): void
    {
        $physio = $this->physioCategory();

        Livewire::test(ReservationWizard::class)
            ->call('selectCategory', $physio->slug)
            ->call('next')
            ->call('selectExamType', ExamType::Kontrolni->value)
            ->assertSet('gate', 'login')
            ->assertSet('examType', null);

        $this->assertSame(0, Reservation::count());
    }

    public function test_inline_login_unlocks_kontrolni_for_existing_client(): void
    {
        $physio = $this->physioCategory();
        $user = User::factory()->customer()->create(['email' => 'klient@example.com', 'password' => Hash::make('tajneheslo')]);
        Reservation::factory()->create([
            'client_id' => $user->id,
            'reservation_date' => now()->subMonths(3)->toDateString(),
            'status' => ReservationStatus::Confirmed,
        ]);

        Livewire::test(ReservationWizard::class)
            ->call('selectCategory', $physio->slug)
            ->call('next')
            ->call('selectExamType', ExamType::Kontrolni->value)
            ->assertSet('gate', 'login')
            ->set('loginEmail', 'klient@example.com')
            ->set('loginPassword', 'tajneheslo')
            ->call('logIn')
            ->assertSet('gate', null)
            ->assertSet('examType', ExamType::Kontrolni->value);

        $this->assertAuthenticatedAs($user);
    }

    public function test_lapsed_client_is_steered_to_vstupni(): void
    {
        $physio = $this->physioCategory();
        $user = User::factory()->customer()->create();
        Reservation::factory()->create([
            'client_id' => $user->id,
            'reservation_date' => now()->subMonths(18)->toDateString(),
            'status' => ReservationStatus::Confirmed,
        ]);

        Livewire::actingAs($user)->test(ReservationWizard::class)
            ->call('selectCategory', $physio->slug)
            ->call('next')
            ->call('selectExamType', ExamType::Kontrolni->value)
            ->assertSet('gate', 'lapsed')
            ->assertSet('lapsedMonths', 12)
            ->call('selectExamType', ExamType::Vstupni->value)
            ->assertSet('gate', null)
            ->assertSet('examType', ExamType::Vstupni->value);
    }

    public function test_guest_with_existing_email_books_without_logging_in(): void
    {
        Notification::fake();
        $existing = User::factory()->customer()->create(['email' => 'dup@example.com']);

        Livewire::test(ReservationWizard::class)
            ->set('categorySlug', $this->category->slug)
            ->set('serviceSlug', $this->service->slug)
            ->set('therapistSlug', $this->therapist->slug)
            ->set('date', $this->date->toDateString())
            ->set('startTime', '08:00')
            ->set('firstName', 'Jana')
            ->set('lastName', 'Nováková')
            ->set('email', 'dup@example.com')
            ->set('phone', '+420604793255')
            ->set('agreeCancellation', true)
            ->call('submit')
            ->assertSet('gate', null)
            ->assertSet('confirmationId', fn ($value): bool => $value !== null);

        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'dup@example.com')->count());
        $this->assertSame($existing->id, Reservation::sole()->client_id);
    }

    public function test_existing_email_shows_inline_login_hint_and_login_prefills_without_booking(): void
    {
        $existing = User::factory()->customer()->create(['email' => 'dup@example.com', 'password' => Hash::make('tajneheslo')]);

        $component = Livewire::test(ReservationWizard::class)
            ->set('email', 'neznamy@example.com')
            ->assertSet('emailKnown', false)
            ->set('email', 'dup@example.com')
            ->assertSet('emailKnown', true)
            ->call('showLogin')
            ->assertSet('gate', 'email_exists')
            ->assertSet('loginEmail', 'dup@example.com')
            ->set('loginPassword', 'tajneheslo')
            ->call('logIn')
            ->assertSet('gate', null)
            ->assertSet('emailKnown', false)
            // The typed address gives way to the account's — the field is locked from here on.
            ->assertSet('email', 'dup@example.com')
            ->assertSet('confirmationId', null);

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(0, Reservation::count());

        Notification::fake();

        $component
            ->set('categorySlug', $this->category->slug)
            ->set('serviceSlug', $this->service->slug)
            ->set('therapistSlug', $this->therapist->slug)
            ->set('date', $this->date->toDateString())
            ->set('startTime', '08:00')
            ->set('firstName', 'Jana')
            ->set('lastName', 'Nováková')
            ->set('phone', '+420604793255')
            ->set('agreeCancellation', true)
            ->call('submit')
            ->assertSet('confirmationId', fn ($value): bool => $value !== null);

        $this->assertSame($existing->id, Reservation::sole()->client_id);
    }

    public function test_logged_in_client_email_is_taken_from_the_account_and_cannot_be_overridden(): void
    {
        Notification::fake();

        $client = User::factory()->customer()->create(['email' => 'klient@example.com']);

        Livewire::actingAs($client)
            ->test(ReservationWizard::class)
            ->assertSet('email', 'klient@example.com')
            ->call('selectCategory', $this->category->slug)
            ->call('next')
            ->call('selectService', $this->service->slug)
            ->call('next')
            // The service has a single therapist, so the step arrives preselected.
            ->call('next')
            ->call('selectDate', $this->date->toDateString())
            ->call('next')
            ->call('selectTime', '08:00')
            ->call('next')
            // A tampered payload must not smuggle a foreign address past the locked field.
            ->set('email', 'jiny@example.com')
            ->set('phone', '+420604793255')
            ->set('agreeCancellation', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('email', 'klient@example.com')
            ->assertSet('confirmationId', fn ($value): bool => $value !== null);

        $this->assertSame($client->id, Reservation::sole()->client_id);
        $this->assertSame(0, User::where('email', 'jiny@example.com')->count());
    }

    public function test_email_field_is_read_only_for_logged_in_clients(): void
    {
        Livewire::actingAs(User::factory()->customer()->create())
            ->test(ReservationWizard::class)
            ->set('stepIndex', $this->contactStepIndex())
            ->assertSeeHtml('readonly')
            ->assertSee('E-mail je převzat z vašeho účtu');
    }

    public function test_email_field_stays_editable_for_guests(): void
    {
        Livewire::test(ReservationWizard::class)
            ->set('stepIndex', $this->contactStepIndex())
            ->assertDontSeeHtml('readonly')
            ->assertDontSee('E-mail je převzat z vašeho účtu');
    }

    private function contactStepIndex(): int
    {
        return (int) array_search('contact', (new ReservationWizard)->stepOrder(), true);
    }

    public function test_continue_without_login_closes_the_optional_panel_and_keeps_the_email(): void
    {
        User::factory()->customer()->create(['email' => 'dup@example.com']);

        Livewire::test(ReservationWizard::class)
            ->set('email', 'dup@example.com')
            ->call('showLogin')
            ->assertSet('gate', 'email_exists')
            ->call('continueWithoutLogin')
            ->assertSet('gate', null)
            ->assertSet('email', 'dup@example.com')
            ->assertSet('emailKnown', true);
    }

    public function test_calendar_flags_a_fully_booked_future_day_as_full(): void
    {
        // The therapist is busy the whole day, so no slot remains on a future date.
        Reservation::factory()->create([
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => Room::factory()->create()->id,
            'reservation_date' => $this->date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => ReservationStatus::Confirmed,
        ]);

        $months = Livewire::test(ReservationWizard::class)
            ->set('categorySlug', $this->category->slug)
            ->set('serviceSlug', $this->service->slug)
            ->set('therapistSlug', $this->therapist->slug)
            ->instance()
            ->calendarMonths();

        $cell = $this->findCell($months, $this->date->toDateString());

        $this->assertNotNull($cell);
        $this->assertSame('full', $cell['queue']);
        $this->assertFalse($cell['available']);
    }

    public function test_today_cell_is_available_when_a_slot_is_free(): void
    {
        Carbon::setTestNow($this->date->copy()->startOfDay());

        $months = Livewire::test(ReservationWizard::class)
            ->set('categorySlug', $this->category->slug)
            ->set('serviceSlug', $this->service->slug)
            ->set('therapistSlug', $this->therapist->slug)
            ->instance()
            ->calendarMonths();

        $cell = $this->findCell($months, Carbon::today()->toDateString());

        $this->assertNotNull($cell);
        $this->assertTrue($cell['today']);
        $this->assertTrue($cell['available']);
    }

    /**
     * Locate a single day cell in the calendar grid by date.
     *
     * @param  array<int, array{weeks: array<int, array<int, ?array{date: string}>>}>  $months
     * @return array{date: string, day: int, available: bool, today: bool, queue: ?string}|null
     */
    private function findCell(array $months, string $date): ?array
    {
        foreach ($months as $month) {
            foreach ($month['weeks'] as $week) {
                foreach ($week as $cell) {
                    if ($cell !== null && $cell['date'] === $date) {
                        return $cell;
                    }
                }
            }
        }

        return null;
    }

    /**
     * A fresh, fully-booked one-hour day for this test's service + therapist,
     * returned as a Y-m-d string (distinct from the setUp's open day).
     */
    private function fullyBookedDay(): string
    {
        $date = $this->date->copy()->addDay();
        $room = Room::factory()->create();

        TherapistWorkBlock::factory()->create([
            'therapist_id' => $this->therapist->id,
            'room_id' => $room->id,
            'work_date' => $date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);

        Reservation::factory()->confirmed()->create([
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $room->id,
            'client_id' => User::factory()->customer(),
            'reservation_date' => $date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);

        return $date->toDateString();
    }

    public function test_a_full_day_lets_a_guest_join_the_day_waitlist(): void
    {
        Notification::fake();
        $date = $this->fullyBookedDay();

        Livewire::test(ReservationWizard::class, [
            'serviceSlug' => $this->service->slug,
            'therapistSlug' => $this->therapist->slug,
        ])
            ->call('openWaitlist', $date)
            ->set('waitlistName', 'Jana Nováková')
            ->set('waitlistEmail', 'jana@example.cz')
            ->set('waitlistPhone', '+420604793255')
            ->call('joinDayWaitlist')
            ->assertHasNoErrors();

        $this->assertTrue(
            ReservationDayWaitlistEntry::query()
                ->whereDate('reservation_date', $date)
                ->where('therapist_id', $this->therapist->id)
                ->whereNull('client_id')
                ->where('email', 'jana@example.cz')
                ->exists(),
        );
    }

    public function test_a_joined_day_switches_from_full_to_the_waitlist_cell_state(): void
    {
        Notification::fake();
        $date = $this->fullyBookedDay();

        $component = Livewire::test(ReservationWizard::class, [
            'serviceSlug' => $this->service->slug,
            'therapistSlug' => $this->therapist->slug,
        ]);

        $this->assertSame('full', $this->findCell($component->instance()->calendarMonths(), $date)['queue']);

        $component->call('openWaitlist', $date)
            ->set('waitlistName', 'Jana')
            ->set('waitlistEmail', 'jana@example.cz')
            ->call('joinDayWaitlist');

        $this->assertSame('waitlist', $this->findCell($component->instance()->calendarMonths(), $date)['queue']);
    }
}
