<?php

namespace App\Support\Clients;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Support\Actions\DeactivateUserAction;
use App\Models\CourseEnrollment;
use App\Models\LessonBooking;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\TherapistReservationTemplateNotification;
use App\Support\ActivityLog\LogActivity;
use App\Support\Enrollments\CancelSignup;
use App\Support\Reservations\ClientReservationActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deactivating an account is not just a login lock — the person is not coming, so
 * everything they hold has to be released: upcoming reservations, course and
 * lesson sign-ups, waitlist spots. Otherwise a blacklisted client keeps blocking
 * calendar slots and course capacity nobody can use.
 *
 * Money already owed is **kept**: open payments tied to something being cancelled
 * are marked {@see PaymentStatus::Cancelled} (withdrawn, still on the books),
 * while debts for services already rendered are left untouched — deactivation
 * does not write off a debt.
 *
 * Shared by both entry points, so the client's own „nezaplatím" refusal and an
 * admin pressing „Deaktivovat účet" behave identically:
 * {@see ClientReservationActions} and
 * {@see DeactivateUserAction}.
 */
class DeactivateAccount
{
    public function __construct(private readonly CancelSignup $cancelSignup) {}

    /**
     * Counts of what a deactivation would release, for the confirmation prompts —
     * nobody should press the button without knowing what it takes down.
     *
     * @return array{reservations: int, enrollments: int, bookings: int, waitlist: int}
     */
    public function preview(User $client): array
    {
        return [
            'reservations' => $this->upcomingReservations($client)->count(),
            'enrollments' => $this->activeEnrollments($client)->count(),
            'bookings' => $this->activeBookings($client)->count(),
            'waitlist' => $client->waitlistEntries()->count(),
        ];
    }

    /**
     * A human summary of {@see preview()} ("2 rezervace, 1 přihlášku na kurz"),
     * or null when there is nothing to release.
     */
    public function previewSentence(User $client): ?string
    {
        $counts = $this->preview($client);

        $parts = array_filter([
            $counts['reservations'] > 0 ? $this->plural($counts['reservations'], 'rezervaci', 'rezervace', 'rezervací') : null,
            $counts['enrollments'] > 0 ? $this->plural($counts['enrollments'], 'přihlášku na kurz', 'přihlášky na kurz', 'přihlášek na kurz') : null,
            $counts['bookings'] > 0 ? $this->plural($counts['bookings'], 'přihlášku na lekci', 'přihlášky na lekci', 'přihlášek na lekci') : null,
            $counts['waitlist'] > 0 ? $this->plural($counts['waitlist'], 'místo v pořadníku', 'místa v pořadníku', 'míst v pořadníku') : null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Deactivate and release everything the client holds.
     *
     * @param  Reservation|null  $except  The reservation whose cancellation triggered
     *                                    this — already handled by the caller.
     * @return array{reservations: int, enrollments: int, bookings: int, waitlist: int}
     */
    public function __invoke(User $client, ?Reservation $except = null): array
    {
        $released = [
            'reservations' => $this->cancelUpcomingReservations($client, $except),
            'enrollments' => $this->cancelSignups($this->activeEnrollments($client)->get()),
            'bookings' => $this->cancelSignups($this->activeBookings($client)->get()),
            'waitlist' => $this->clearWaitlist($client),
        ];

        $this->closePendingDoctorNotes($client);

        $client->update([
            'deactivated_at' => now(),
            'reactivated_at' => null,
        ]);

        LogActivity::record('account_deactivated', $client, 'Účet deaktivován', [
            'reservations' => $released['reservations'],
            'enrollments' => $released['enrollments'],
            'bookings' => $released['bookings'],
            'waitlist' => $released['waitlist'],
        ]);

        return $released;
    }

    /**
     * Cancel every still-live reservation from today on. The client is not e-mailed
     * (they just triggered this, or an admin did it deliberately) but the therapist
     * is — the slot is theirs to fill again.
     */
    private function cancelUpcomingReservations(User $client, ?Reservation $except): int
    {
        $reservations = $this->upcomingReservations($client)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();

        foreach ($reservations as $reservation) {
            DB::transaction(function () use ($reservation): void {
                $reservation->payments()
                    ->whereIn('status', PaymentStatus::openValues())
                    ->update(['status' => PaymentStatus::Cancelled]);

                $reservation->update([
                    'status' => ReservationStatus::Cancelled,
                    'cancellation_reason' => 'Deaktivace účtu',
                ]);
            });

            $reservation->therapist?->user?->notify(new TherapistReservationTemplateNotification(
                $reservation,
                EmailTemplateKey::TherapistReservationCancelled,
                ['storno_reseni' => 'Účet klienta byl deaktivován', 'storno_castka' => ''],
            ));

            LogActivity::record('reservation_cancelled', $reservation, 'Rezervace zrušena', [
                'source' => 'Deaktivace účtu klienta',
                'notified_client' => false,
                'notified_therapist' => $reservation->therapist?->user !== null,
            ], $client);
        }

        return $reservations->count();
    }

    /**
     * Cancel course/lesson sign-ups through the shared action, so each freed spot is
     * offered to the waitlist by the sign-up observers. No client e-mail — they are
     * not coming back, and one message per course would be spam.
     *
     * @param  Collection<int, CourseEnrollment|LessonBooking>  $signups
     */
    private function cancelSignups($signups): int
    {
        foreach ($signups as $signup) {
            ($this->cancelSignup)($signup, notify: false);
        }

        return $signups->count();
    }

    /**
     * Give up every waitlist spot — a deactivated account could never take the place
     * up, so leaving the entries would stall the queue behind it.
     */
    private function clearWaitlist(User $client): int
    {
        $entries = $client->waitlistEntries()->get();

        $entries->each->delete();

        return $entries->count();
    }

    /**
     * Close any other promised doctor's note. It is not coming — the account is
     * blacklisted — so leaving it pending would sit in the staff work list forever.
     */
    private function closePendingDoctorNotes(User $client): void
    {
        $client->reservations()
            ->whereNotNull('doctor_note_requested_at')
            ->whereNull('doctor_note_resolved_at')
            ->update(['doctor_note_resolved_at' => now()]);
    }

    /**
     * @return Builder<Reservation>
     */
    private function upcomingReservations(User $client)
    {
        return $client->reservations()
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->whereDate('reservation_date', '>=', today());
    }

    /**
     * @return Builder<CourseEnrollment>
     */
    private function activeEnrollments(User $client)
    {
        return $client->courseEnrollments()
            ->whereIn('status', [CourseEnrollmentStatus::Active, CourseEnrollmentStatus::Waitlist]);
    }

    /**
     * @return Builder<LessonBooking>
     */
    private function activeBookings(User $client)
    {
        return $client->lessonBookings()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Pending, BookingStatus::Waitlist]);
    }

    /**
     * Czech count agreement: 1 / 2–4 / 5+.
     */
    private function plural(int $count, string $one, string $few, string $many): string
    {
        return $count.' '.match (true) {
            $count === 1 => $one,
            $count < 5 => $few,
            default => $many,
        };
    }
}
