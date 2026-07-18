<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use App\Support\ActivityLog\LogActivity;
use App\Support\Payments\PaymentEmailTokens;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Marks a past visit as a no-show: the reservation is cancelled with a fixed
 * reason and a fee payment (settings-driven percentage, default 100 %) is raised
 * with a QR request e-mail. Health excuses run through the existing doctor-note
 * flow linked from the e-mail.
 */
class MarkNoShowAction extends Action
{
    public const CANCELLATION_REASON = 'Nedostavení se na termín';

    public static function getDefaultName(): ?string
    {
        return 'markNoShow';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Označit nedostavení')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->modalHeading('Označit nedostavení na termín')
            ->modalIcon(Heroicon::OutlinedUserMinus)
            ->modalDescription(fn (Reservation $record): string => sprintf(
                'Rezervace bude stornována a klientovi bude účtován poplatek %s Kč (%d %% z ceny služby).',
                number_format($this->noShowFee($record), 0, ',', ' '),
                Settings::noShowFeePercent(),
            ))
            ->modalSubmitActionLabel('Označit nedostavení')
            ->visible(fn (Reservation $record): bool => $record->status !== ReservationStatus::Cancelled
                && $record->startsAt()->isPast())
            ->schema([
                Toggle::make('notify_client')
                    ->label('Poslat e-mail klientovi')
                    ->default(true),
            ])
            ->action(function (Reservation $record, array $data): void {
                $record->update([
                    'status' => ReservationStatus::Cancelled,
                    'cancellation_reason' => self::CANCELLATION_REASON,
                ]);

                $fee = $this->noShowFee($record);

                if ($fee <= 0) {
                    LogActivity::record('reservation_no_show', $record, 'Nedostavení na termín', [
                        'fee' => '0 Kč',
                        'notified_client' => false,
                    ]);

                    Notification::make()
                        ->title('Rezervace byla označena jako nedostavení.')
                        ->success()
                        ->send();

                    return;
                }

                $payment = $record->payments()->create([
                    'client_id' => $record->client_id,
                    'amount' => $fee,
                    'method' => PaymentMethod::Qr,
                    'status' => PaymentStatus::Unpaid,
                    'due_at' => today()->addDays(Settings::paymentDueDays()),
                ]);

                $notifyClient = ($data['notify_client'] ?? false) && filled($record->client?->email);

                if ($notifyClient) {
                    $record->client?->notify(new ReservationTemplateNotification(
                        $record,
                        EmailTemplateKey::ReservationNoShow,
                        PaymentEmailTokens::for($payment),
                    ));
                }

                LogActivity::record('reservation_no_show', $record, 'Nedostavení na termín', [
                    'fee' => $fee.' Kč',
                    'notified_client' => $notifyClient,
                ]);

                Notification::make()
                    ->title('Nedostavení bylo zaznamenáno a poplatek vystaven.')
                    ->success()
                    ->send();
            });
    }

    private function noShowFee(Reservation $record): int
    {
        return (int) round(($record->service?->price ?? 0) * Settings::noShowFeePercent() / 100);
    }
}
