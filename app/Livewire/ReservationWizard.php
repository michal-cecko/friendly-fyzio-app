<?php

namespace App\Livewire;

use App\Enums\ExamType;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Models\ReservationDayWaitlistEntry;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Reservations\CreateReservationFromWizard;
use App\Support\Reservations\DeactivatedClientException;
use App\Support\Reservations\JoinReservationDayWaitlist;
use App\Support\Reservations\ReservationBookingData;
use App\Support\Reservations\ReservationSlots;
use App\Support\Reservations\Slot;
use App\Support\Reservations\SlotCalendar;
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
 * is computed, never stored: the wizard normally asks category → service →
 * therapist, and only leads with the therapist when a deep-link names one and
 * nothing else (e.g. ?terapeut=). The auth interstitials live in this component
 * too (see the `gate` property) but are wired in a later step.
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

    /** null/'' = none chosen, 'any' = browse all therapists, otherwise a therapist slug. */
    #[Url(as: 'terapeut')]
    public ?string $therapistSlug = null;

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

    public string $note = '';

    public bool $agreeCancellation = false;

    public bool $newsletter = false;

    /**
     * Whether the wizard leads with the therapist instead of the category. Decided
     * once, from how the user arrived (a therapist-only deep-link), so selecting a
     * therapist mid-flow never reorders the steps.
     */
    public bool $therapistFirst = false;

    /** Terminal state: the created reservation id, or a submit error message. */
    public ?string $confirmationId = null;

    public ?string $submitError = null;

    /** Auth interstitial: null | 'login' | 'lapsed' | 'email_exists'. */
    public ?string $gate = null;

    /** The contact-step email belongs to an existing account (guest only) — shows the inline login hint. */
    public bool $emailKnown = false;

    public string $loginEmail = '';

    public string $loginPassword = '';

    /** Recency window (months) shown in the "lapsed client" message. */
    public ?int $lapsedMonths = null;

    /** Day-waitlist ("pořadník") modal: the full day being joined, or null when closed. */
    public ?string $waitlistModalDate = null;

    public string $waitlistName = '';

    public string $waitlistEmail = '';

    public string $waitlistPhone = '';

    /**
     * Full days the visitor joined the pořadník for during this session — merged
     * with the logged-in client's stored entries so those cells show as joined.
     *
     * @var array<int, string>
     */
    public array $sessionWaitlistedDates = [];

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

        // Therapist-first is the exception: a therapist deep-link with nothing else
        // already answered leads with that person. Any category/service in the URL
        // (including one derived from ?sluzba= above) means the normal order applies.
        $this->therapistFirst = filled($this->therapistSlug)
            && blank($this->categorySlug)
            && blank($this->serviceSlug);

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
        return $this->therapistFirst
            ? ['therapist', 'category', 'service', 'date', 'time', 'contact']
            : ['category', 'service', 'therapist', 'date', 'time', 'contact'];
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
        if ($this->currentStep() !== 'therapist' || $this->isAnyTherapist() || filled($this->therapistSlug)) {
            return;
        }

        $therapists = $this->therapists();

        if ($therapists->count() === 1) {
            $this->therapistSlug = $therapists->first()->slug;
        }
    }

    protected function stepComplete(string $step): bool
    {
        return match ($step) {
            'therapist' => filled($this->therapistSlug),
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
    public function therapist(): ?StaffProfile
    {
        return blank($this->therapistSlug) || $this->isAnyTherapist()
            ? null
            : StaffProfile::query()->with('user')->where('slug', $this->therapistSlug)->first();
    }

    public function isAnyTherapist(): bool
    {
        return $this->therapistSlug === 'any';
    }

    /**
     * The concrete therapist to compute availability with: null when none is chosen
     * or "any" is selected (browse all), otherwise the selected therapist's UUID
     * (resolved from the URL slug via the memoized therapist() computed).
     */
    public function resolvedTherapistId(): ?string
    {
        return $this->therapist()?->getKey();
    }

    // --- Option lists ---------------------------------------------------------

    /*
     * Each option list is narrowed only by selections the user made *earlier* in
     * the current order. Narrowing by a later one would shrink the list the moment
     * someone steps back to change their mind — the category step would show only
     * what the therapist they picked two steps on happens to do.
     */

    /**
     * The chosen therapist, but only where they precede the category and service —
     * i.e. in therapist-first order.
     */
    protected function upstreamTherapistId(): ?string
    {
        return $this->therapistFirst ? $this->resolvedTherapistId() : null;
    }

    /**
     * The chosen service, but only where it precedes the therapist — i.e. in the
     * normal, category-first order.
     */
    protected function upstreamService(): ?Service
    {
        return $this->therapistFirst ? null : $this->service();
    }

    /**
     * @return Collection<int, StaffProfile>
     */
    #[Computed]
    public function therapists()
    {
        // Deliberately not filtered by the therapist's own published_at: publishing
        // only controls the public team page and profile detail, not who can be
        // booked. Bookability (Therapist capability + an active account + at least
        // one bookable service) lives in one place, StaffProfile::scopeBookable —
        // so lecturers and the assistant never appear here.
        return StaffProfile::query()
            ->with('user')
            ->bookable()
            ->when($this->upstreamService(), fn ($query, Service $service) => $query->whereHas('services', fn ($q) => $q->whereKey($service->getKey())))
            ->get()
            ->sortBy(fn (StaffProfile $therapist): string => $therapist->user?->name ?? '')
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
            ->whereHas('services', fn ($q) => $q->bookable()
                ->when($this->upstreamTherapistId(), fn ($sq, string $id) => $sq->whereHas('therapists', fn ($t) => $t->whereKey($id))))
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
            ->bookable()
            ->when($this->isPhysioCategory(), fn ($query) => $query->where('exam_type', $this->examType))
            ->when($this->upstreamTherapistId(), fn ($query, string $id) => $query->whereHas('therapists', fn ($t) => $t->whereKey($id)))
            ->orderBy('duration_minutes')
            ->get();
    }

    public function isPhysioCategory(): bool
    {
        return $this->category?->type === ServiceType::Physiotherapy;
    }

    /**
     * Whether an exam-type card renders as selected. A "kontrolní" click that opens
     * the login/lapsed gate deliberately does NOT commit $examType (that happens
     * once the gate clears), so during the gate the selection must follow the click
     * — Kontrolní only — not the still-committed previous "vstupní" choice, which
     * would otherwise leave both cards active.
     */
    public function isExamTypeSelected(ExamType $type): bool
    {
        if (in_array($this->gate, ['login', 'lapsed'], true)) {
            return $type === ExamType::Kontrolni;
        }

        return $this->examType === $type->value;
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
            ->bookable()
            ->whereNotNull('exam_type')
            ->when($this->upstreamTherapistId(), fn ($query, string $id) => $query->whereHas('therapists', fn ($t) => $t->whereKey($id)))
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
            ->bookable()
            ->where('exam_type', ExamType::Kontrolni->value)
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
        if (! $this->service || blank($this->therapistSlug)) {
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
     * Full days (Y-m-d) the current visitor already joined the pořadník for, in the
     * current therapist scope — this session's joins plus, for a logged-in client,
     * their stored pending entries. Drives the "waitlist" calendar cell state.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function waitlistedDates(): array
    {
        $dates = $this->sessionWaitlistedDates;

        if (($user = auth()->user()) !== null) {
            $scope = $this->resolvedTherapistId();

            $stored = ReservationDayWaitlistEntry::query()
                ->whereNull('notified_at')
                ->where('client_id', $user->getKey())
                ->where(fn ($query) => $scope === null
                    ? $query->whereNull('therapist_id')
                    : $query->where('therapist_id', $scope))
                ->pluck('reservation_date')
                ->map(fn ($date): string => Carbon::parse($date)->toDateString())
                ->all();

            $dates = array_merge($dates, $stored);
        }

        return array_values(array_unique($dates));
    }

    /**
     * @return array<int, Slot>
     */
    #[Computed]
    public function availableTimes(): array
    {
        if (! $this->service || blank($this->date) || blank($this->therapistSlug)) {
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

        return SlotCalendar::months($first, $last, $this->calendarDays, $this->waitlistedDates);
    }

    // --- Day waitlist ("pořadník") --------------------------------------------

    /**
     * Open the pořadník modal for a full day, prefilling identity from the logged-in
     * client when there is one (guests fill it in themselves).
     */
    public function openWaitlist(string $date): void
    {
        if (! in_array($date, $this->calendarDays['full'], true)) {
            return;
        }

        $this->resetErrorBag();
        $this->waitlistModalDate = $date;

        if (($user = auth()->user()) !== null) {
            $this->waitlistName = $this->waitlistName ?: (string) $user->name;
            $this->waitlistEmail = $this->waitlistEmail ?: (string) $user->email;
            $this->waitlistPhone = $this->waitlistPhone ?: (string) $user->phone;
        }
    }

    public function closeWaitlist(): void
    {
        $this->reset('waitlistModalDate', 'waitlistName', 'waitlistEmail', 'waitlistPhone');
        $this->resetErrorBag();
    }

    /**
     * Join the pořadník for the modal's day, scoped to the currently browsed
     * therapist (or "any"). Guests welcome; the browsed service rides along only to
     * prefill the eventual booking link.
     */
    public function joinDayWaitlist(): void
    {
        $date = $this->waitlistModalDate;

        if ($date === null || ! in_array($date, $this->calendarDays['full'], true)) {
            $this->closeWaitlist();

            return;
        }

        $this->validate([
            'waitlistName' => ['required', 'string', 'max:255'],
            'waitlistEmail' => ['required', 'email'],
            'waitlistPhone' => ['nullable', 'string', 'max:30'],
        ], [], [
            'waitlistName' => 'jméno',
            'waitlistEmail' => 'e-mail',
            'waitlistPhone' => 'telefon',
        ]);

        app(JoinReservationDayWaitlist::class)->handle(
            $this->resolvedTherapistId(),
            $date,
            trim($this->waitlistName),
            trim($this->waitlistEmail),
            filled($this->waitlistPhone) ? trim($this->waitlistPhone) : null,
            $this->service,
        );

        $this->sessionWaitlistedDates[] = $date;
        unset($this->waitlistedDates);
        $this->closeWaitlist();

        session()->flash('waitlist_joined', 'Zapsali jsme vás do pořadníku. Jakmile se místo uvolní, dáme vám e-mailem vědět.');
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
                ? StaffProfile::query()->with('user')->find($this->bookingTherapistId)?->user?->full_name
                : 'Nezáleží')
            : $this->therapist?->user?->full_name;

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

    public function selectTherapist(string $slug): void
    {
        $this->therapistSlug = $slug;
        $this->resetDownstream('therapist');
    }

    public function selectAnyTherapist(): void
    {
        $this->therapistSlug = 'any';
        $this->resetDownstream('therapist');
    }

    // Lifecycle hooks: deferred `wire:model` selections commit on the next action,
    // so downstream state is reset / resolved here (the select* methods above do the
    // same for the programmatic test API, which doesn't trigger these hooks).
    public function updatedTherapistSlug(): void
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
        // The therapist and the category/service invalidate each other, in whichever
        // direction the current order runs: a new service may not be offered by the
        // chosen therapist, and a new therapist may not offer the chosen service.
        $therapistInvalidatesOffering = $this->therapistFirst && $changed === 'therapist';

        if ($therapistInvalidatesOffering) {
            $this->categorySlug = null;
        }

        if (! $this->therapistFirst && in_array($changed, ['category', 'service'], true)) {
            $this->therapistSlug = null;
        }

        if ($changed === 'category' || $therapistInvalidatesOffering) {
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
            $this->scrollToTop();
        }
    }

    public function back(): void
    {
        $this->stepIndex = max(0, $this->stepIndex - 1);
        $this->preselectSingleTherapist();
        $this->submitError = null;
        $this->scrollToTop();
    }

    /**
     * Step content height varies a lot between steps, so on mobile the viewport can
     * be left scrolled past the new step. Signal the view to scroll back to the top.
     */
    protected function scrollToTop(): void
    {
        $this->dispatch('wizard-step-changed');
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
        $this->scrollToTop();
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
        // The field is read-only for logged-in clients; pin it server-side too so a
        // tampered payload cannot slip a foreign address past the locked input.
        if (($authenticated = auth()->user()) !== null) {
            $this->email = $authenticated->email;
        }

        $this->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'agreeCancellation' => ['accepted'],
        ], [
            'agreeCancellation.accepted' => 'Pro dokončení je nutné souhlasit se storno podmínkami.',
        ], [
            'firstName' => 'jméno',
            'lastName' => 'příjmení',
            'email' => 'e-mail',
            'phone' => 'telefon',
        ]);

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
            $this->scrollToTop();
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

    /**
     * Name and phone keep anything already typed, but the e-mail always comes from
     * the account: the booking is attached to the logged-in user regardless of what
     * the field says, so the contact step locks it and must show the real address.
     */
    protected function prefillContactFromUser(User $user): void
    {
        [$first, $last] = array_pad(explode(' ', trim((string) $user->name), 2), 2, '');

        $this->firstName = $this->firstName ?: $first;
        $this->lastName = $this->lastName ?: $last;
        $this->email = $user->email;
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
        $this->emailKnown = false;
        $user = auth()->user();
        $this->prefillContactFromUser($user);

        // Logging in from the optional "email exists" hint just links the session
        // to the account; the visitor finishes the form and submits themselves.
        if ($wasGate === 'email_exists') {
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

    /**
     * The contact-step email is checked on blur; a match only shows the inline
     * login hint — booking as a guest is always allowed and the reservation is
     * attached to the existing account by e-mail.
     */
    public function updatedEmail(): void
    {
        $this->emailKnown = auth()->guest()
            && filled($this->email)
            && User::query()->where('email', $this->email)->exists();
    }

    /** Open the optional login panel from the inline hint, with the e-mail prefilled. */
    public function showLogin(): void
    {
        $this->loginEmail = $this->email;
        $this->gate = 'email_exists';
    }

    public function continueWithoutLogin(): void
    {
        $this->gate = null;
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
