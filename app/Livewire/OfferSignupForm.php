<?php

namespace App\Livewire;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use App\Models\WaitlistEntry;
use App\Models\Workshop;
use App\Support\Enrollments\AlreadySignedUpException;
use App\Support\Enrollments\EnrollmentData;
use App\Support\Enrollments\EnrollmentEmailContext;
use App\Support\Enrollments\JoinWaitlist;
use App\Support\Enrollments\OfferClosedException;
use App\Support\Enrollments\SignUpForOffer;
use App\Support\Reservations\DeactivatedClientException;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

/**
 * The registration section of a course / one-time lesson / workshop detail
 * page — one component, every state from the designs:
 *
 * - open        → registration form + order summary (mid-series pro-rating)
 * - full        → waitlist sign-up + queue info (+ joined confirmation)
 * - enrolled    → "you're in" summary for the logged-in client
 * - done        → post-submit confirmation ("e-mail s platebními údaji")
 * - closed      → muted informational note (past / not open)
 *
 * A pre-sale link ($presale) keeps the form open on an Inactive series.
 */
class OfferSignupForm extends Component
{
    /** How long the "already on this waitlist" cookie survives (~120 days). */
    private const WAITLIST_COOKIE_MINUTES = 60 * 24 * 120;

    public string $offerType;

    public string $offerId;

    public bool $presale = false;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $note = '';

    public bool $terms = false;

    public string $waitlistName = '';

    public string $waitlistEmail = '';

    public string $waitlistPhone = '';

    /** null | 'signup' | 'waitlist' */
    public ?string $completed = null;

    public ?string $waitlistEntryId = null;

    public ?string $errorMessage = null;

    public function mount(string $offerType, string $offerId, bool $presale = false): void
    {
        $this->offerType = $offerType;
        $this->offerId = $offerId;
        $this->presale = $presale;

        if (($user = auth()->user()) !== null) {
            $this->name = (string) $user->name;
            $this->email = (string) $user->email;
            $this->phone = (string) ($user->phone ?? '');
            $this->waitlistName = $this->name;
            $this->waitlistEmail = $this->email;
            $this->waitlistPhone = $this->phone;
        }

        $this->restoreWaitlistFromCookie();
    }

    public function submit(SignUpForOffer $action): void
    {
        $this->errorMessage = null;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:2000'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Pro dokončení přihlášky je potřeba souhlasit s obchodními podmínkami.',
        ], [
            'name' => 'jméno a příjmení',
            'email' => 'e-mail',
            'phone' => 'telefon',
            'note' => 'poznámka',
        ]);

        $offer = $this->offer();
        $data = new EnrollmentData(
            name: trim($this->name),
            email: trim($this->email),
            phone: trim($this->phone) ?: null,
            note: trim($this->note) ?: null,
            client: auth()->user(),
        );

        try {
            match (true) {
                $offer instanceof CourseSeries => $action->forSeries($offer, $data, $this->presale),
                $offer instanceof OneTimeLesson => $action->forLesson($offer, $data),
                $offer instanceof Workshop => $action->forWorkshop($offer, $data),
            };

            $this->completed = 'signup';
        } catch (OfferClosedException) {
            $this->errorMessage = 'Kapacita se právě naplnila nebo bylo přihlašování mezitím uzavřeno. Níže se můžete přidat na čekací listinu.';
        } catch (AlreadySignedUpException) {
            $this->errorMessage = 'S tímto e-mailem už aktivní přihlášku evidujeme. Pokud si nejste jistí, ozvěte se nám.';
        } catch (DeactivatedClientException) {
            $this->errorMessage = 'Váš účet je momentálně deaktivovaný. Kontaktujte nás prosím a přihlášení vyřídíme spolu.';
        }
    }

    public function joinWaitlist(): void
    {
        $this->errorMessage = null;

        $this->validate([
            'waitlistName' => ['required', 'string', 'max:255'],
            'waitlistEmail' => ['required', 'email', 'max:255'],
            'waitlistPhone' => ['nullable', 'string', 'max:30'],
        ], [], [
            'waitlistName' => 'jméno a příjmení',
            'waitlistEmail' => 'e-mail',
            'waitlistPhone' => 'telefon',
        ]);

        $entry = JoinWaitlist::handle(
            $this->offer(),
            trim($this->waitlistName),
            trim($this->waitlistEmail),
            trim($this->waitlistPhone) ?: null,
        );

        $this->waitlistEntryId = (string) $entry->getKey();
        $this->waitlistEmail = trim($this->waitlistEmail);
        $this->completed = 'waitlist';

        Cookie::queue($this->cookieName(), $this->waitlistEntryId, self::WAITLIST_COOKIE_MINUTES);
    }

    public function leaveWaitlist(): void
    {
        WaitlistEntry::query()
            ->whereKey($this->waitlistEntryId)
            ->where('waitlistable_type', $this->offerMorphClass())
            ->where('waitlistable_id', $this->offerId)
            ->whereNull('notified_at')
            ->delete();

        Cookie::queue(Cookie::forget($this->cookieName()));

        $this->waitlistEntryId = null;
        $this->completed = null;
    }

    /**
     * Restore the "you're already on the waitlist" state for a returning
     * visitor from their cookie. A stale cookie (entry promoted or removed in
     * the meantime) is silently dropped so the sign-up form shows again.
     */
    protected function restoreWaitlistFromCookie(): void
    {
        $entryId = request()->cookie($this->cookieName());

        if (! is_string($entryId) || $entryId === '') {
            return;
        }

        $entry = WaitlistEntry::query()
            ->whereKey($entryId)
            ->where('waitlistable_type', $this->offerMorphClass())
            ->where('waitlistable_id', $this->offerId)
            ->whereNull('notified_at')
            ->first();

        if ($entry === null) {
            Cookie::queue(Cookie::forget($this->cookieName()));

            return;
        }

        $this->waitlistEntryId = (string) $entry->getKey();
        $this->waitlistEmail = (string) $entry->email;
        $this->completed = 'waitlist';
    }

    protected function cookieName(): string
    {
        return "waitlist_{$this->offerType}_{$this->offerId}";
    }

    protected function offerMorphClass(): string
    {
        return (match ($this->offerType) {
            'series' => new CourseSeries,
            'lesson' => new OneTimeLesson,
            'workshop' => new Workshop,
        })->getMorphClass();
    }

    public function render(): View
    {
        $offer = $this->offer();
        $state = $this->presale && $offer instanceof CourseSeries
            ? $offer->offerStateForPresale()
            : $offer->offerState();

        return view('livewire.offer-signup-form', [
            'offer' => $offer,
            'state' => $state,
            'isEnrolled' => $this->isEnrolled($offer),
            'summaryRows' => $this->summaryRows($offer),
            'price' => $offer instanceof CourseSeries ? $offer->currentPrice() : (int) $offer->price,
            'fullPrice' => (int) $offer->price,
            'midSeries' => $offer instanceof CourseSeries && $offer->hasStarted() && ! $offer->hasEnded(),
            'holdHours' => Settings::enrollmentHoldHours(),
            'waitlistCount' => $offer->waitlistEntries()->whereNull('notified_at')->count(),
            'offerTitle' => $this->offerTitle($offer),
        ]);
    }

    protected function offer(): CourseSeries|OneTimeLesson|Workshop
    {
        return match ($this->offerType) {
            'series' => CourseSeries::query()->with('course')->findOrFail($this->offerId),
            'lesson' => OneTimeLesson::query()->with(['course', 'room'])->findOrFail($this->offerId),
            'workshop' => Workshop::query()->with('room')->findOrFail($this->offerId),
        };
    }

    protected function isEnrolled(CourseSeries|OneTimeLesson|Workshop $offer): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return match (true) {
            $offer instanceof CourseSeries => $offer->enrollments()
                ->where('client_id', $user->id)
                ->where('status', CourseEnrollmentStatus::Active)
                ->exists(),
            default => ($offer instanceof OneTimeLesson ? $offer->bookings() : $offer->registrations())
                ->where('client_id', $user->id)
                ->whereIn('status', BookingStatus::occupying())
                ->exists(),
        };
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected function summaryRows(CourseSeries|OneTimeLesson|Workshop $offer): array
    {
        return match (true) {
            $offer instanceof CourseSeries => array_filter([
                ['Kurz', (string) ($offer->course?->name ?? $offer->name)],
                ['Běh', $offer->name],
                ['Období', EnrollmentEmailContext::seriesPeriod($offer)],
                ['Nejbližší lekce', EnrollmentEmailContext::nextLessonLabel($offer)],
            ]),
            $offer instanceof OneTimeLesson => [
                ['Lekce', (string) ($offer->course?->name ?? '')],
                ['Termín', EnrollmentEmailContext::dateTimeLabel($offer->startsAt())],
                ['Místo', EnrollmentEmailContext::place($offer->room)],
            ],
            $offer instanceof Workshop => [
                ['Workshop', $offer->name],
                ['Termín', EnrollmentEmailContext::dateTimeLabel($offer->startsAt())],
                ['Místo', EnrollmentEmailContext::place($offer->room)],
            ],
        };
    }

    protected function offerTitle(CourseSeries|OneTimeLesson|Workshop $offer): string
    {
        return match (true) {
            $offer instanceof CourseSeries => (string) ($offer->course?->name ?? $offer->name),
            $offer instanceof OneTimeLesson => (string) ($offer->course?->name ?? 'Lekce'),
            $offer instanceof Workshop => $offer->name,
        };
    }
}
