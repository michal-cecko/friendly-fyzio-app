<?php

namespace App\Filament\Support\Actions;

use App\Enums\ReservationStatus;
use App\Enums\ReviewRequestChannel;
use App\Filament\Support\Schemas\CopyRecipientsFields;
use App\Models\LessonBooking;
use App\Models\Reservation;
use App\Models\ReviewRequest;
use App\Models\User;
use App\Notifications\ReviewRequestNotification;
use App\Support\Emails\CopyRecipients;
use App\Support\Emails\SentEmailReceipt;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Manually asks a client to leave a review by e-mailing them a magic link to the
 * on-site review form. Works on completed therapy reservations and one-off event
 * bookings; the reviewed entity is the reservation itself or the booking's event.
 * Automatic requests for courses/events are handled by the reviews:send-requests
 * command.
 */
class SendReviewRequestAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendReviewRequest';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Požádat o recenzi')
            ->icon(Heroicon::OutlinedStar)
            ->color('gray')
            ->modalHeading('Požádat o recenzi')
            ->modalIcon(Heroicon::OutlinedStar)
            ->modalDescription('Klientovi odešleme e-mail s odkazem na formulář pro recenzi.')
            ->modalSubmitActionLabel('Odeslat')
            ->visible(fn (Model $record): bool => self::isEligible($record))
            ->schema([
                Textarea::make('message')
                    ->label('Vlastní zpráva (nepovinné)')
                    ->rows(3)
                    ->helperText('Nahradí výchozí úvodní text e-mailu.'),
                ...CopyRecipientsFields::make(),
            ])
            ->action(function (Model $record, array $data): void {
                $client = self::resolveClient($record);

                if (! $client || blank($client->email)) {
                    Notification::make()
                        ->title('Klient nemá e-mailovou adresu.')
                        ->danger()
                        ->send();

                    return;
                }

                $reviewable = self::resolveReviewable($record);

                $request = ReviewRequest::create([
                    'user_id' => $client->getKey(),
                    'reviewable_type' => $reviewable->getMorphClass(),
                    'reviewable_id' => $reviewable->getKey(),
                    'channel' => ReviewRequestChannel::Manual,
                    'sent_at' => now(),
                ]);

                $client->notify(new ReviewRequestNotification(
                    $request,
                    filled($data['message'] ?? null) ? $data['message'] : null,
                    CopyRecipients::fromFormData($data),
                ));

                SentEmailReceipt::forCurrentUser('Žádost o recenzi');

                Notification::make()
                    ->title('Žádost o recenzi byla odeslána.')
                    ->success()
                    ->send();
            });
    }

    private static function resolveClient(Model $record): ?User
    {
        return match (true) {
            $record instanceof Reservation => $record->client,
            $record instanceof LessonBooking => $record->client,
            default => null,
        };
    }

    private static function resolveReviewable(Model $record): Model
    {
        return $record instanceof LessonBooking
            ? $record->lesson
            : $record;
    }

    private static function isEligible(Model $record): bool
    {
        $client = self::resolveClient($record);

        if (! $client || blank($client->email)) {
            return false;
        }

        return match (true) {
            $record instanceof Reservation => $record->status === ReservationStatus::Confirmed
                && $record->reservation_date !== null
                && ! $record->reservation_date->isFuture(),
            $record instanceof LessonBooking => $record->lesson?->lesson_date !== null
                && ! $record->lesson->lesson_date->isFuture(),
            default => false,
        };
    }
}
