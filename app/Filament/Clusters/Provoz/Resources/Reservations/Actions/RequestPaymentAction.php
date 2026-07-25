<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use App\Support\ActivityLog\LogActivity;
use App\Support\Emails\SentEmailReceipt;
use App\Support\Payments\PaymentEmailTokens;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Requests payment for an unpaid visit: raises an Unpaid QR payment with a due
 * date and e-mails the client the CMS "reservation_unpaid" notice with the
 * payment box (IBAN, VS, QR Platba, splatnost).
 */
class RequestPaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'requestPayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Vyžádat platbu')
            ->icon(Heroicon::OutlinedEnvelopeOpen)
            ->color('warning')
            ->modalHeading('Vyžádat platbu')
            ->modalIcon(Heroicon::OutlinedEnvelopeOpen)
            ->modalDescription('Vytvoří nezaplacenou QR platbu a klientovi odešle e-mail s platebními údaji.')
            ->modalSubmitActionLabel('Vyžádat')
            ->visible(fn (Reservation $record): bool => ! $record->hasPaidStatus()
                && ! $record->payments()->where('status', PaymentStatus::Unpaid->value)->exists()
                && filled($record->client?->email))
            ->schema([
                TextInput::make('amount')
                    ->label('Částka')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->suffix('Kč')
                    ->default(fn (Reservation $record): int => max(0, $record->paymentAmountDue()
                        - (int) $record->payments()->where('status', PaymentStatus::Paid->value)->sum('amount'))),
                DatePicker::make('due_at')
                    ->label('Splatnost')
                    ->native(false)
                    ->required()
                    ->default(fn (): Carbon => today()->addDays(Settings::paymentDueDays())),
            ])
            ->action(function (Reservation $record, array $data): void {
                $payment = $record->payments()->create([
                    'client_id' => $record->client_id,
                    'amount' => (int) $data['amount'],
                    'method' => PaymentMethod::Qr,
                    'status' => PaymentStatus::Unpaid,
                    'due_at' => $data['due_at'],
                ]);

                $record->client?->notify(new ReservationTemplateNotification(
                    $record,
                    EmailTemplateKey::ReservationUnpaid,
                    PaymentEmailTokens::for($payment),
                ));

                SentEmailReceipt::forCurrentUser('Žádost o platbu');

                LogActivity::record('payment_requested', $record, 'Platba vyžádána', [
                    'amount' => $payment->amount.' Kč',
                    'due_at' => $payment->due_at?->format('d.m.Y'),
                    'notified_client' => true,
                ]);

                Notification::make()
                    ->title('Žádost o platbu byla odeslána.')
                    ->success()
                    ->send();
            });
    }
}
