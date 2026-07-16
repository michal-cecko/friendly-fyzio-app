<?php

namespace App\Support\Reservations;

use App\Enums\ConfirmationSource;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Jobs\SubscribeToNewsletterJob;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ClientAccountCreatedNotification;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use App\Support\RichText;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a validated wizard submission into a persisted reservation.
 *
 * Booking is serialised per therapist + date with a short cache lock, and the
 * database enforces a partial unique index as the ultimate guard against
 * double-booking. The slot is re-resolved inside the transaction so a booking that
 * became unofferable since selection is rejected with a {@see SlotTakenException}.
 * Confirmation e-mails go out only once the transaction has committed.
 */
class CreateReservationFromWizard
{
    public function __construct(protected ReservationSlots $slots) {}

    public function __invoke(ReservationBookingData $data): Reservation
    {
        $lock = Cache::lock("reservation:{$data->therapistId}:{$data->date}", 10);

        /** @var array{0: Reservation, 1: User, 2: bool} $result */
        $result = $lock->block(5, fn (): array => DB::transaction(fn (): array => $this->persist($data)));

        [$reservation, $client, $isNewAccount] = $result;

        // After commit and outside the lock: notify the client and therapist. A booking
        // auto-confirmed above gets the "automatically confirmed" e-mail; otherwise the
        // "booking received" acknowledgement (the confirmation request follows later).
        $client->notify(new ReservationTemplateNotification(
            $reservation,
            $reservation->status === ReservationStatus::Confirmed
                ? EmailTemplateKey::ReservationAutoConfirmed
                : EmailTemplateKey::ReservationCreated,
        ));
        $reservation->therapist?->user?->notify(new TherapistReservationTemplateNotification(
            $reservation,
            EmailTemplateKey::TherapistReservationCreated,
            ['odkaz_potvrdit' => ReservationResource::getUrl('view', ['record' => $reservation])],
        ));

        if ($isNewAccount) {
            $client->notify(new ClientAccountCreatedNotification);
        }

        if ($data->newsletter) {
            SubscribeToNewsletterJob::dispatch($client->email, $client->name);
        }

        return $reservation;
    }

    /**
     * @return array{0: Reservation, 1: User, 2: bool}
     */
    protected function persist(ReservationBookingData $data): array
    {
        $slot = $this->slots->resolveSlot($data->service, Carbon::parse($data->date), $data->startTime, $data->therapistId);

        if ($slot === null) {
            throw new SlotTakenException;
        }

        [$client, $isNewAccount] = $this->resolveClient($data);

        try {
            $reservation = Reservation::create([
                'client_id' => $client->id,
                'service_id' => $data->service->id,
                'therapist_id' => $slot->therapistId,
                'room_id' => $slot->roomId,
                'reservation_date' => $slot->date,
                'start_time' => $slot->start().':00',
                'end_time' => $slot->end().':00',
                'status' => ReservationStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'is_control_therapy' => false,
                'notes' => RichText::fromPlainText($data->note),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new SlotTakenException(previous: $exception);
        }

        // A booking already inside the confirmation window is auto-confirmed: asking the
        // customer to confirm what they just booked would be pointless.
        if ($reservation->withinConfirmationWindow()) {
            $reservation->update([
                'status' => ReservationStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by' => ConfirmationSource::Automatic,
                'confirmed_by_id' => null,
            ]);
        }

        return [$reservation, $client, $isNewAccount];
    }

    /**
     * Use the authenticated user, an existing account matching the email, or create
     * a fresh customer account with a random (unusable) password.
     *
     * @return array{0: User, 1: bool} the client and whether the account was created
     */
    protected function resolveClient(ReservationBookingData $data): array
    {
        if ($data->client !== null) {
            if ($data->client->isDeactivated()) {
                throw new DeactivatedClientException;
            }

            return [$data->client, false];
        }

        $existing = User::query()->where('email', $data->email)->first();

        if ($existing !== null) {
            if ($existing->isDeactivated()) {
                throw new DeactivatedClientException;
            }

            if ($data->newsletter && $existing->newsletter_opted_in_at === null) {
                $existing->update(['newsletter_opted_in_at' => now()]);
            }

            return [$existing, false];
        }

        $user = User::create([
            'name' => $data->fullName(),
            'email' => $data->email,
            'phone' => $data->phone,
            'role' => UserRole::Customer,
            'password' => Str::random(40),
            'newsletter_opted_in_at' => $data->newsletter ? now() : null,
        ]);

        $user->clientProfile()->firstOrCreate([]);

        return [$user, true];
    }
}
