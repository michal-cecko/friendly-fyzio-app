<?php

namespace App\Livewire;

use App\Enums\ExamType;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Support\Reservations\CreateReservationFromWizard;
use App\Support\Reservations\DeactivatedClientException;
use App\Support\Reservations\ReservationBookingData;
use App\Support\Reservations\ReservationSlots;
use App\Support\Reservations\Slot;
use App\Support\Reservations\SlotTakenException;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public, full-page reservation wizard.
 *
 * Selections are query-string bound so any state is deep-linkable. The step order
 * is computed, never stored: arriving with a category/service deep-link (e.g.
 * ?sluzba=) puts the wizard in category-first order; otherwise it leads with the
 * therapist. The auth interstitials live in this component too (see the `gate`
 * property) but are wired in a later step.
 */
class ReservationWizard extends Component
{
    #[Url(as: 'kategorie')]
    public ?string $categorySlug = null;

    #[Url(as: 'sluzba')]
    public ?string $serviceSlug = null;

    /** Physiotherapy substep: 'vstupni' (new patient) | 'kontrolni' (existing). */
    #[Url(as: 'typ')]
    public ?string $examType = null;

    /** null/'' = none chosen, 'any' = browse all therapists, otherwise a therapist UUID. */
    #[Url(as: 'terapeut')]
    public ?string $therapistId = null;

    #[Url(as: 'datum')]
    public ?string $date = null;

    #[Url(as: 'cas')]
    public ?string $startTime = null;

    #[Url(as: 'krok')]
    public int $stepIndex = 0;

    /** Therapist that actually owns the chosen slot (resolved at the time step). */
    public ?string $bookingTherapistId = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    public string $phoneConfirm = '';

    public string $note = '';

    public bool $agreeCancellation = false;

    public bool $newsletter = false;

    /**
     * Whether the wizard runs category-first. Decided once, from how the user
     * arrived (a category/service deep-link), so selecting a category mid-flow
     * never reorders the steps.
     */
    public bool $categoryFirst = false;

    /** Terminal state: the created reservation id, or a submit error message. */
    public ?string $confirmationId = null;

    public ?string $submitError = null;

    /** Auth interstitial: null | 'login' | 'lapsed' | 'email_exists'. */
    public ?string $gate = null;

    public string $loginEmail = '';

    public string $loginPassword = '';

    /** Recency window (months) shown in the "lapsed client" message. */
    public ?int $lapsedMonths = null;

    public function mount(): void
    {
        // Deep-link by service alone (?sluzba=): derive its category (and physio exam
        // type) so the service arrives fully prefilled and the wizard skips past it.
        if (filled($this->serviceSlug) && blank($this->categorySlug)) {
            $service = Service::query()->with('category')->where('slug', $this->serviceSlug)->first();

            if ($service !== null) {
                $this->categorySlug = $service->category?->slug;

                if ($this->examType === null && $service->exam_type !== null) {
                    $this->examType = $service->exam_type->value;
                }
            }
        }

        $this->categoryFirst = filled($this->categorySlug) || filled($this->serviceSlug);

        if (($user = auth()->user()) !== null) {
            $this->prefillContactFromUser($user);
        }

        if ($this->stepIndex === 0) {
            $this->stepIndex = $this->firstIncompleteStepIndex();
        }

        $this->preselectSingleTherapist();
    }

    // --- Step model -----------------------------------------------------------

    /**
     * @return array<int, string>
     */
    public function stepOrder(): array
    {
        return $this->categoryFirst
            ? ['category', 'service', 'therapist', 'date', 'time', 'contact']
            : ['therapist', 'category', 'service', 'date', 'time', 'contact'];
    }

    public function currentStep(): string
    {
        return $this->stepOrder()[$this->stepIndex] ?? 'contact';
    }

    protected function firstIncompleteStepIndex(): int
    {
        foreach ($this->stepOrder() as $index => $step) {
            if (! $this->stepComplete($step)) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * When the user is on the therapist step and exactly one therapist qualifies
     * for their selection, preselect that therapist — there's nothing else to pick.
     * The step is still shown (not skipped) so the user sees who they'll be booked
     * with and confirms by continuing.
     */
    protected function preselectSingleTherapist(): void
    {
        if ($this->currentStep() !== 'therapist' || $this->isAnyTherapist() || filled($this->therapistId)) {
            return;
        }

        $therapists = $this->therapists();

        if ($therapists->count() === 1) {
            $this->therapistId = (string) $therapists->first()->getKey();
        }
    }

    protected function stepComplete(string $step): bool
    {
        return match ($step) {
            'therapist' => filled($this->therapistId),
            'category' => $this->category !== null,
            'service' => $this->service !== null,
            'date' => filled($this->date),
            'time' => filled($this->startTime),
            default => false,
        };
    }

    // --- Resolved selections --------------------------------------------------

    #[Computed]
    public function category(): ?ServiceCategory
    {
        return filled($this->categorySlug)
            ? ServiceCategory::query()->where('slug', $this->categorySlug)->first()
            : null;
    }

    #[Computed]
    public function service(): ?Service
    {
        return filled($this->serviceSlug)
            ? Service::query()->where('slug', $this->serviceSlug)->first()
            : null;
    }

    #[Computed]
    public function therapist(): ?TherapistProfile
    {
        return $this->resolvedTherapistId() !== null
            ? TherapistProfile::query()->with('user')->find($this->resolvedTherapistId())
            : null;
    }

    public function isAnyTherapist(): bool
    {
        return $this->therapistId === 'any';
    }

    /**
     * The concrete therapist to compute availability with: null when none is chosen
     * or "any" is selected (browse all), otherwise the selected UUID.
     */
    public function resolvedTherapistId(): ?string
    {
        return blank($this->therapistId) || $this->therapistId === 'any' ? null : $this->therapistId;
    }

    // --- Option lists ---------------------------------------------------------

    /**
     * @return Collection<int, TherapistProfile>
     */
    #[Computed]
    public function therapists()
    {
        return TherapistProfile::query()
            ->with('user')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->when($this->service, fn ($query) => $query->whereHas('services', fn ($q) => $q->whereKey($this->service->id)))
            ->get()
            ->sortBy(fn (TherapistProfile $therapist): string => $therapist->user?->name ?? '')
            ->values();
    }

    /**
     * @return Collection<int, ServiceCategory>
     */
    #[Computed]
    public function categories()
    {
        return ServiceCategory::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->when($this->therapist, fn ($query) => $query->whereHas(
                'services',
                fn ($q) => $q->whereHas('therapists', fn ($t) => $t->whereKey($this->resolvedTherapistId()))
            ))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services()
    {
        if (! $this->category) {
            return collect();
        }

        return $this->category->services()
            ->where('visibility', '!=', ServiceVisibility::Hidden)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->when($this->isPhysioCategory(), fn ($query) => $query->where('exam_type', $this->examType))
            ->when($this->therapist, fn ($query) => $query->whereHas('therapists', fn ($t) => $t->whereKey($this->resolvedTherapistId())))
            ->orderBy('duration_minutes')
            ->get();
    }

    public function isPhysioCategory(): bool
    {
        return $this->category?->type === ServiceType::Physiotherapy;
    }

    /**
     * Exam types ("Typ vyšetření") that have bookable services in this category.
     *
     * @return array<int, ExamType>
     */
    #[Computed]
    public function examTypes(): array
    {
        if (! $this->isPhysioCategory()) {
            return [];
        }

        $present = $this->category->services()
            ->where('visibility', '!=', ServiceVisibility::Hidden)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('exam_type')
            ->when($this->therapist, fn ($query) => $query->whereHas('therapists', fn ($t) => $t->whereKey($this->resolvedTherapistId())))
            ->pluck('exam_type');

        return array_values(array_filter(
            [ExamType::Vstupni, ExamType::Kontrolni],
            fn (ExamType $type): bool => $present->contains($type),
        ));
    }

    /**
     * Recency window (months) a returning client must fall inside to book a
     * "kontrolní" service — the widest window across the category's such services.
     */
    protected function kontrolniRecencyMonths(): int
    {
        return (int) ($this->category?->services()
            ->where('exam_type', ExamType::Kontrolni->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->max('existing_client_months') ?: Settings::existingClientMonths());
    }

    // --- Availability ---------------------------------------------------------

    /**
     * Day classification for the whole booking window, resolved once per request:
     * 'available' (>= 1 bookable slot) and 'full' (works that day but booked out —
     * a "pořadník"/waitlist candidate).
     *
     * @return array{available: array<int, string>, full: array<int, string>}
     */
    #[Computed]
    public function calendarDays(): array
    {
        if (! $this->service || blank($this->therapistId)) {
            return ['available' => [], 'full' => []];
        }

        return app(ReservationSlots::class)->dayAvailability(
            $this->service,
            Carbon::today(),
            Carbon::today()->addDays(Settings::bookingWindowDays()),
            $this->resolvedTherapistId(),
        );
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function availableDays(): array
    {
        return $this->calendarDays['available'];
    }

    /**
     * @return array<int, Slot>
     */
    #[Computed]
    public function availableTimes(): array
    {
        if (! $this->service || blank($this->date) || blank($this->therapistId)) {
            return [];
        }

        return app(ReservationSlots::class)->availableTimes($this->service, Carbon::parse($this->date), $this->resolvedTherapistId());
    }

    // --- Calendar -------------------------------------------------------------

    protected function calendarRange(): array
    {
        return [Carbon::today()->startOfMonth(), Carbon::today()->addDays(Settings::bookingWindowDays())->startOfMonth()];
    }

    /**
     * Every month in the booking window as a Monday-first grid, rendered once so the
     * client can switch months without a server round-trip.
     *
     * @return array<int, array{label: string, weeks: array<int, array<int, ?array{date: string, day: int, available: bool, today: bool, queue: ?string}>>}>
     */
    public function calendarMonths(): array
    {
        [$first, $last] = $this->calendarRange();
        $availability = $this->calendarDays;
        $available = array_flip($availability['available']);
        $full = array_flip($availability['full']);
        $today = Carbon::today();
        $months = [];

        for ($month = $first->copy(); $month->lte($last); $month->addMonth()) {
            $cursor = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

            $weeks = [];
            while ($cursor->lte($end)) {
                $week = [];
                for ($day = 0; $day < 7; $day++) {
                    if ($cursor->month === $month->month) {
                        $ds = $cursor->toDateString();
                        $week[] = [
                            'date' => $ds,
                            'day' => $cursor->day,
                            'available' => isset($available[$ds]),
                            'today' => $cursor->isSameDay($today),
                            // 'full' = works that day but fully booked ("pořadník");
                            // 'waitlist' (per-user, already queued) awaits its backend.
                            'queue' => isset($full[$ds]) ? 'full' : null,
                        ];
                    } else {
                        $week[] = null;
                    }
                    $cursor->addDay();
                }
                $weeks[] = $week;
            }

            $months[] = ['label' => $month->copy()->locale('cs')->isoFormat('MMMM YYYY'), 'weeks' => $weeks];
        }

        return $months;
    }

    /**
     * Index of the month the calendar should open on — the one holding the earliest
     * available day.
     */
    public function initialCalendarIndex(): int
    {
        $days = $this->availableDays;

        if ($days === []) {
            return 0;
        }

        [$first] = $this->calendarRange();

        return (int) $first->diffInMonths(Carbon::parse($days[0])->startOfMonth());
    }

    // --- Summary --------------------------------------------------------------

    /**
     * @return array<string, ?string>
     */
    #[Computed]
    public function summary(): array
    {
        $therapist = $this->isAnyTherapist()
            ? ($this->bookingTherapistId
                ? TherapistProfile::query()->with('user')->find($this->bookingTherapistId)?->user?->name
                : 'Nezáleží')
            : $this->therapist?->user?->name;

        return [
            'category' => $this->category?->name,
            'service' => $this->service?->name,
            'therapist' => $therapist,
            'date' => filled($this->date) ? Carbon::parse($this->date)->locale('cs')->isoFormat('D. M. YYYY') : null,
            'time' => filled($this->startTime) ? $this->startTime : null,
        ];
    }

    /**
     * The summary's "Potvrdit rezervaci" button is live only on the final step.
     */
    public function canConfirm(): bool
    {
        return $this->currentStep() === 'contact';
    }

    // --- Selection ------------------------------------------------------------

    public function selectTherapist(string $therapistId): void
    {
        $this->therapistId = $therapistId;
        $this->resetDownstream('therapist');
    }

    public function selectAnyTherapist(): void
    {
        $this->therapistId = 'any';
        $this->resetDownstream('therapist');
    }

    // Lifecycle hooks: deferred `wire:model` selections commit on the next action,
    // so downstream state is reset / resolved here (the select* methods above do the
    // same for the programmatic test API, which doesn't trigger these hooks).
    public function updatedTherapistId(): void
    {
        $this->resetDownstream('therapist');
    }

    public function updatedCategorySlug(): void
    {
        $this->resetDownstream('category');
    }

    public function updatedServiceSlug(): void
    {
        $this->resetDownstream('service');
    }

    public function updatedDate(): void
    {
        $this->startTime = null;
        $this->bookingTherapistId = null;
    }

    public function updatedStartTime(): void
    {
        $this->resolveBookingTherapist();
    }

    public function selectCategory(string $slug): void
    {
        $this->categorySlug = $slug;
        $this->resetDownstream('category');
    }

    public function selectService(string $slug): void
    {
        $this->serviceSlug = $slug;
        $this->resetDownstream('service');
    }

    /**
     * Choose the physiotherapy exam type. "Kontrolní" is reserved for existing,
     * logged-in patients; everyone else is steered to "Vstupní".
     */
    public function selectExamType(string $type): void
    {
        if ($type === ExamType::Vstupni->value) {
            $this->setExamType($type);

            return;
        }

        $user = auth()->user();

        if ($user === null) {
            $this->gate = 'login';
            $this->loginEmail = $this->email;

            return;
        }

        $months = $this->kontrolniRecencyMonths();

        if (! $this->isExistingClient($user, $months)) {
            $this->lapsedMonths = $months;
            $this->gate = 'lapsed';

            return;
        }

        $this->setExamType($type);
    }

    protected function setExamType(string $type): void
    {
        $this->examType = $type;
        $this->serviceSlug = null;
        $this->gate = null;
        $this->resetErrorBag('step');
    }

    public function selectDate(string $date): void
    {
        $this->date = $date;
        $this->startTime = null;
        $this->bookingTherapistId = null;
    }

    public function selectTime(string $time): void
    {
        $this->startTime = $time;
        $this->resolveBookingTherapist();
    }

    /**
     * Remember which therapist owns the chosen slot (matters in "any" mode, where
     * a given time may belong to one of several therapists).
     */
    protected function resolveBookingTherapist(): void
    {
        foreach ($this->availableTimes as $slot) {
            if ($slot->start() === $this->startTime) {
                $this->bookingTherapistId = $slot->therapistId;

                return;
            }
        }
    }

    /**
     * Clear selections that depend on the one that just changed.
     */
    protected function resetDownstream(string $changed): void
    {
        if ($changed === 'category') {
            $this->serviceSlug = null;
            $this->examType = null;
            $this->lapsedMonths = null;
            $this->gate = null;
        }

        if (in_array($changed, ['therapist', 'category', 'service'], true)) {
            $this->date = null;
            $this->startTime = null;
            $this->bookingTherapistId = null;
        }

        $this->submitError = null;
    }

    // --- Navigation -----------------------------------------------------------

    public function goToStep(int $index): void
    {
        if ($index >= 0 && $index < $this->stepIndex) {
            $this->stepIndex = $index;
        }
    }

    public function back(): void
    {
        $this->stepIndex = max(0, $this->stepIndex - 1);
        $this->preselectSingleTherapist();
        $this->submitError = null;
    }

    public function next(): void
    {
        $step = $this->currentStep();

        if ($step === 'contact') {
            $this->submit();

            return;
        }

        if (! $this->stepComplete($step)) {
            $this->addError('step', $this->stepPrompt($step));

            return;
        }

        $this->resetErrorBag('step');
        $this->advanceStep();
    }

    protected function advanceStep(): void
    {
        $this->stepIndex = min($this->stepIndex + 1, count($this->stepOrder()) - 1);
        $this->preselectSingleTherapist();
    }

    protected function stepPrompt(string $step): string
    {
        return match ($step) {
            'therapist' => 'Vyberte prosím terapeuta.',
            'category' => 'Vyberte prosím kategorii.',
            'service' => 'Vyberte prosím službu.',
            'date' => 'Vyberte prosím datum.',
            'time' => 'Vyberte prosím čas.',
            default => 'Vyplňte prosím tento krok.',
        };
    }

    // --- Submit ---------------------------------------------------------------

    public function submit(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'phoneConfirm' => ['required', 'same:phone'],
            'agreeCancellation' => ['accepted'],
        ], [
            'phoneConfirm.same' => 'Telefonní čísla se neshodují.',
            'agreeCancellation.accepted' => 'Pro dokončení je nutné souhlasit se storno podmínkami.',
        ], [
            'firstName' => 'jméno',
            'lastName' => 'příjmení',
            'email' => 'e-mail',
            'phone' => 'telefon',
            'phoneConfirm' => 'telefon pro kontrolu',
        ]);

        // A guest using an email that already has an account is asked to log in
        // rather than silently creating a duplicate.
        if (auth()->guest() && User::query()->where('email', $this->email)->exists()) {
            $this->gate = 'email_exists';
            $this->loginEmail = $this->email;

            return;
        }

        $therapistId = $this->bookingTherapistId ?? $this->resolvedTherapistId();

        if (! $this->service || blank($therapistId) || blank($this->date) || blank($this->startTime)) {
            $this->submitError = 'Rezervace není kompletní. Začněte prosím znovu.';

            return;
        }

        try {
            $reservation = app(CreateReservationFromWizard::class)(new ReservationBookingData(
                service: $this->service,
                therapistId: $therapistId,
                date: $this->date,
                startTime: $this->startTime,
                firstName: $this->firstName,
                lastName: $this->lastName,
                email: $this->email,
                phone: $this->phone,
                note: $this->note ?: null,
                newsletter: $this->newsletter,
                client: auth()->user(),
            ));

            $this->confirmationId = $reservation->id;
            $this->submitError = null;
        } catch (DeactivatedClientException $exception) {
            $this->submitError = $exception->getMessage();
        } catch (SlotTakenException $exception) {
            $this->submitError = $exception->getMessage();
            $this->startTime = null;
            $this->stepIndex = array_search('time', $this->stepOrder(), true);
        }
    }

    // --- Auth gates -----------------------------------------------------------

    /**
     * An existing client has a non-cancelled reservation within the recency window;
     * a client with no (recent enough) history counts as new.
     */
    protected function isExistingClient(User $user, int $months): bool
    {
        $latest = $user->reservations()
            ->where('status', '!=', ReservationStatus::Cancelled->value)
            ->max('reservation_date');

        return $latest !== null && Carbon::parse($latest)->gte(now()->subMonths($months));
    }

    protected function prefillContactFromUser(User $user): void
    {
        [$first, $last] = array_pad(explode(' ', trim((string) $user->name), 2), 2, '');

        $this->firstName = $this->firstName ?: $first;
        $this->lastName = $this->lastName ?: $last;
        $this->email = $this->email ?: $user->email;
        $this->phone = $this->phone ?: ((string) $user->phone);
    }

    /**
     * Inline login used by the login and email-exists interstitials. Keeps all
     * wizard state because the component never navigates away.
     */
    public function logIn(): void
    {
        $this->validate([
            'loginEmail' => ['required', 'email'],
            'loginPassword' => ['required'],
        ], [], ['loginEmail' => 'e-mail', 'loginPassword' => 'heslo']);

        if (! Auth::attempt(['email' => $this->loginEmail, 'password' => $this->loginPassword])) {
            $this->addError('loginEmail', 'Nesprávný e-mail nebo heslo.');

            return;
        }

        $this->loginPassword = '';
        $wasGate = $this->gate;
        $this->gate = null;
        $user = auth()->user();
        $this->prefillContactFromUser($user);

        if ($wasGate === 'email_exists') {
            $this->submit();

            return;
        }

        // Logged in from the "Kontrolní" prompt — confirm they qualify as existing,
        // otherwise steer them to "Vstupní".
        $months = $this->kontrolniRecencyMonths();

        if (! $this->isExistingClient($user, $months)) {
            $this->lapsedMonths = $months;
            $this->gate = 'lapsed';

            return;
        }

        $this->setExamType(ExamType::Kontrolni->value);
    }

    public function useDifferentEmail(): void
    {
        $this->gate = null;
        $this->email = '';
        $this->loginEmail = '';
        $this->loginPassword = '';
    }

    public function startOver(): void
    {
        $this->reset();
        $this->stepIndex = 0;
    }

    public function render(): View
    {
        return view('livewire.reservation-wizard');
    }
}
