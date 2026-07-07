<?php

namespace App\Filament\Support\Actions;

use App\Enums\ReservationStatus;
use App\Enums\ReviewRequestChannel;
use App\Models\OneTimeLessonBooking;
use App\Models\Reservation;
use App\Models\ReviewRequest;
use App\Models\User;
use App\Notifications\ReviewRequestNotification;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Manually asks a client to leave a review by e-mailing them a link to the
 * external questionnaire. Works on completed therapy reservations and one-time
 * lesson bookings; the reviewed entity is the reservation itself or the booking's
 * lesson. Automatic requests for courses/workshops are handled separately by the
 * reviews:send-requests command.
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
            ->modalSubmitActionLabel('Odeslat')
            ->visible(fn (Model $record): bool => self::isEligible($record))
            ->schema([
                TextInput::make('questionnaire_url')
                    ->label('Odkaz na dotazník')
                    ->url()
                    ->required()
                    ->default(fn (): ?string => Settings::get('reviews.questionnaire_url')),
                Textarea::make('message')
                    ->label('Vlastní zpráva (nepovinné)')
                    ->rows(3)
                    ->helperText('Nahradí výchozí úvodní text e-mailu.'),
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

                ReviewRequest::create([
                    'user_id' => $client->getKey(),
                    'reviewable_type' => $reviewable->getMorphClass(),
                    'reviewable_id' => $reviewable->getKey(),
                    'channel' => ReviewRequestChannel::Manual,
                    'questionnaire_url' => $data['questionnaire_url'],
                    'sent_at' => now(),
                ]);

                $client->notify(new ReviewRequestNotification(
                    $reviewable,
                    $data['questionnaire_url'],
                    filled($data['message'] ?? null) ? $data['message'] : null,
                ));

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
            $record instanceof OneTimeLessonBooking => $record->client,
            default => null,
        };
    }

    private static function resolveReviewable(Model $record): Model
    {
        return $record instanceof OneTimeLessonBooking
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
            $record instanceof OneTimeLessonBooking => $record->lesson?->lesson_date !== null
                && ! $record->lesson->lesson_date->isFuture(),
            default => false,
        };
    }
}
