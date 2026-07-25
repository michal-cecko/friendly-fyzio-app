<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Notifications\ReservationStornoPaymentNotification;
use App\Support\ActivityLog\LogActivity;
use App\Support\Emails\SentEmailReceipt;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Resolves a late storno for which the client promised a doctor's note. One modal,
 * two outcomes: the note arrived → waive the fee and close the reservation
 * ("Vybaveno"); the note never came → charge the storno fee (raises an unpaid QR
 * payment + the storno-payment e-mail, exactly like the client "pay" path). The
 * charged reservation settles automatically once that fee is paid.
 */
class ResolveDoctorNoteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'resolveDoctorNote';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Vyřešit storno (lékařské potvrzení)')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('warning')
            ->modalHeading('Vyřešit storno – potvrzení od lékaře')
            ->modalIcon(Heroicon::OutlinedDocumentCheck)
            ->modalSubmitActionLabel('Vyřešit')
            ->visible(fn (Reservation $record): bool => $record->doctor_note_requested_at !== null
                && $record->doctor_note_resolved_at === null)
            ->schema([
                Radio::make('outcome')
                    ->label('Jak bylo storno vyřešeno?')
                    ->required()
                    ->live()
                    ->default('received')
                    ->options([
                        'received' => 'Potvrzení doručeno – poplatek prominout',
                        'charge' => 'Potvrzení nedoručeno – doúčtovat storno poplatek',
                    ]),
                TextInput::make('amount')
                    ->label('Částka')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->suffix('Kč')
                    ->default(fn (Reservation $record): int => $record->stornoFee())
                    ->visible(fn (Get $get): bool => $get('outcome') === 'charge'),
                DatePicker::make('due_at')
                    ->label('Splatnost')
                    ->native(false)
                    ->required()
                    ->default(fn (): Carbon => today()->addDays(Settings::paymentDueDays()))
                    ->visible(fn (Get $get): bool => $get('outcome') === 'charge'),
                Toggle::make('notify_client')
                    ->label('Poslat e-mail klientovi')
                    ->default(true)
                    ->visible(fn (Get $get): bool => $get('outcome') === 'charge'),
            ])
            ->action(function (Reservation $record, array $data): void {
                if (($data['outcome'] ?? null) === 'charge') {
                    self::charge($record, $data);

                    return;
                }

                self::waive($record);
            });
    }

    /**
     * Note received: waive the fee and close the reservation. The status stays
     * Cancelled — only the settled marker is set, so the outcome remains visible.
     */
    private static function waive(Reservation $record): void
    {
        $record->update([
            'doctor_note_resolved_at' => now(),
            'settled_at' => now(),
        ]);

        LogActivity::record('reservation_completed', $record, 'Rezervace vybavena', [
            'source' => 'Storno – potvrzení od lékaře doručeno (poplatek prominut)',
        ]);

        Notification::make()
            ->title('Potvrzení přijato, storno poplatek prominut.')
            ->success()
            ->send();
    }

    /**
     * Note never arrived: charge the storno fee (unpaid QR payment + e-mail). The
     * reservation settles automatically once the fee is paid (PaymentObserver).
     *
     * @param  array<string, mixed>  $data
     */
    private static function charge(Reservation $record, array $data): void
    {
        $record->update(['doctor_note_resolved_at' => now()]);

        /** @var Payment $payment */
        $payment = $record->payments()->create([
            'client_id' => $record->client_id,
            'amount' => (int) $data['amount'],
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => $data['due_at'],
        ]);

        $notifyClient = ($data['notify_client'] ?? false) && filled($record->client?->email);

        if ($notifyClient) {
            $record->client?->notify(new ReservationStornoPaymentNotification($record, $payment));

            SentEmailReceipt::forCurrentUser('Storno poplatek');
        }

        LogActivity::record('reservation_storno_charged', $record, 'Storno poplatek vyžádán', [
            'source' => 'Storno – potvrzení od lékaře nedoručeno',
            'fee' => $payment->amount.' Kč',
            'notified_client' => $notifyClient,
        ]);

        Notification::make()
            ->title('Storno poplatek byl vystaven.')
            ->success()
            ->send();
    }
}
