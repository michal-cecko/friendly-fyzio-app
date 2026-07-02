<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\ExamType;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Enums\UserRole;
use App\Enums\WeekType;
use App\Livewire\ReservationWizard;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TherapistProfile;
use App\Models\TherapistWeeklySchedule;
use App\Models\User;
use App\Notifications\ReservationNotification;
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

    private TherapistProfile $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = Carbon::today()->addWeeks(8)->startOfWeek(Carbon::MONDAY);
        $room = Room::factory()->create();

        $this->category = ServiceCategory::factory()->create(['type' => ServiceType::Massage, 'published_at' => now()]);
        $this->service = Service::factory()->create([
            'category_id' => $this->category->id,
            'duration_minutes' => 60,
            'break_minutes' => 15,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);

        $this->therapist = TherapistProfile::factory()->create(['published_at' => now()]);
        $this->service->therapists()->attach($this->therapist);

        TherapistWeeklySchedule::factory()->create([
            'therapist_id' => $this->therapist->id,
            'room_id' => $room->id,
            'day_of_week' => DayOfWeek::fromCarbon($this->date),
            'week_type' => WeekType::All,
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

    public function test_happy_path_creates_a_reservation_and_account(): void
    {
        Notification::fake();

        Livewire::test(ReservationWizard::class)
            ->assertSet('stepIndex', 0)
            ->call('selectTherapist', $this->therapist->id)
            ->call('next')
            ->call('selectCategory', $this->category->slug)
            ->call('next')
            ->call('selectService', $this->service->slug)
            ->call('next')
            ->call('selectDate', $this->date->toDateString())
            ->call('next')
            ->call('selectTime', '08:00')
            ->call('next')
            ->set('firstName', 'Jana')
            ->set('lastName', 'Nováková')
            ->set('email', 'jana@example.com')
            ->set('phone', '+420604793255')
            ->set('phoneConfirm', '+420604793255')
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
        $this->assertSame(UserRole::Customer, $client->role);
        Notification::assertSentTo($client, ReservationNotification::class);
    }

    public function test_preset_starts_in_category_first_order(): void
    {
        $component = Livewire::test(ReservationWizard::class, ['preset' => 'masaz']);

        $this->assertSame('category', $component->instance()->currentStep());
    }

    public function test_default_starts_in_therapist_first_order(): void
    {
        $component = Livewire::test(ReservationWizard::class);

        $this->assertSame('therapist', $component->instance()->currentStep());
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
            ->set('therapistId', $this->therapist->id)
            ->set('categorySlug', $this->category->slug)
            ->instance()
            ->services();

        $this->assertTrue($services->contains('id', $this->service->id));
        $this->assertFalse($services->contains('id', $hidden->id));
    }

    public function test_submit_requires_consent_and_matching_phones(): void
    {
        Livewire::test(ReservationWizard::class)
            ->set('therapistId', $this->therapist->id)
            ->set('categorySlug', $this->category->slug)
            ->set('serviceSlug', $this->service->slug)
            ->set('date', $this->date->toDateString())
            ->set('startTime', '08:00')
            ->set('firstName', 'Jana')
            ->set('lastName', 'Nováková')
            ->set('email', 'jana@example.com')
            ->set('phone', '+420604793255')
            ->set('phoneConfirm', '+420000000000')
            ->set('agreeCancellation', false)
            ->call('submit')
            ->assertHasErrors(['phoneConfirm', 'agreeCancellation']);

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
            'break_minutes' => 15,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);

        $kontrolni = Service::factory()->create([
            'category_id' => $category->id,
            'name' => 'Kontrolní terapie aparátu',
            'slug' => 'kontrolni-terapie-aparatu',
            'exam_type' => ExamType::Kontrolni,
            'duration_minutes' => 60,
            'break_minutes' => 15,
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
            ->call('selectTherapist', $this->therapist->id)
            ->call('next')
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
            ->call('selectTherapist', $this->therapist->id)
            ->call('next')
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
            ->call('selectTherapist', $this->therapist->id)
            ->call('next')
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
            ->call('selectTherapist', $this->therapist->id)
            ->call('next')
            ->call('selectCategory', $physio->slug)
            ->call('next')
            ->call('selectExamType', ExamType::Kontrolni->value)
            ->assertSet('gate', 'lapsed')
            ->assertSet('lapsedMonths', 12)
            ->call('selectExamType', ExamType::Vstupni->value)
            ->assertSet('gate', null)
            ->assertSet('examType', ExamType::Vstupni->value);
    }

    public function test_email_exists_gate_logs_in_and_books_under_existing_account(): void
    {
        Notification::fake();
        $existing = User::factory()->customer()->create(['email' => 'dup@example.com', 'password' => Hash::make('tajneheslo')]);

        Livewire::test(ReservationWizard::class)
            ->set('therapistId', $this->therapist->id)
            ->set('categorySlug', $this->category->slug)
            ->set('serviceSlug', $this->service->slug)
            ->set('date', $this->date->toDateString())
            ->set('startTime', '08:00')
            ->set('firstName', 'Jana')
            ->set('lastName', 'Nováková')
            ->set('email', 'dup@example.com')
            ->set('phone', '+420604793255')
            ->set('phoneConfirm', '+420604793255')
            ->set('agreeCancellation', true)
            ->call('submit')
            ->assertSet('gate', 'email_exists')
            ->set('loginPassword', 'tajneheslo')
            ->call('logIn')
            ->assertSet('confirmationId', fn ($value): bool => $value !== null);

        $this->assertSame(1, User::where('email', 'dup@example.com')->count());
        $this->assertSame($existing->id, Reservation::sole()->client_id);
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
            ->set('therapistId', $this->therapist->id)
            ->set('categorySlug', $this->category->slug)
            ->set('serviceSlug', $this->service->slug)
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
            ->set('therapistId', $this->therapist->id)
            ->set('categorySlug', $this->category->slug)
            ->set('serviceSlug', $this->service->slug)
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
}
