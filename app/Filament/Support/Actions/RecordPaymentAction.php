<?php

namespace App\Filament\Support\Actions;

use App\Contracts\Payable;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
use App\Support\Payments\PaymentNotifier;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * Records a payment on any Payable (reservation, course enrollment, workshop
 * registration, one-time lesson booking). A received payment settles the payable
 * automatically (PaymentObserver) and — for cash — issues the PPD; the client
 * confirmation e-mail is controlled by the toggle here.
 */
class RecordPaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'recordPayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Zaznamenat platbu')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->modalHeading('Zaznamenat platbu')
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->modalDescription('Zaznamená platbu k tomuto záznamu. Přijatá platba v hotovosti automaticky vystaví příjmový pokladní doklad.')
            ->modalSubmitActionLabel('Zaznamenat')
            ->visible(fn (Model $record): bool => $record instanceof Payable && ! $record->hasPaidStatus())
            ->schema([
                TextInput::make('amount')
                    ->label('Částka')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->suffix('Kč')
                    ->default(fn (Model $record): int => $record instanceof Payable
                        ? max(0, $record->paymentAmountDue() - (int) $record->payments()->where('status', PaymentStatus::Paid->value)->sum('amount'))
                        : 0),
                Select::make('method')
                    ->label('Způsob platby')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash->value)
                    ->required()
                    ->native(false),
                Toggle::make('received')
                    ->label('Platba již přijata')
                    ->default(true)
                    ->live()
                    // The toggle decides between "money is in hand" and "this is
                    // what the client owes" — two very different records, with
                    // nothing in the label to tell them apart.
                    ->helperText(fn (Get $get): string => (bool) $get('received')
                        ? 'Peníze už máte. Platba se rovnou započítá, a je-li v hotovosti, vystaví se k ní příjmový pokladní doklad.'
                        : 'Vypnuto = platbu jen předepisujete. Uloží se jako nezaplacená se splatností za '.Settings::paymentDueDays().' dní a záznam zůstane neuhrazený.'),
                Toggle::make('notify_client')
                    ->label('Poslat potvrzení klientovi')
                    ->default(true)
                    ->helperText('E-mail o přijaté platbě.')
                    ->visible(fn (Get $get): bool => (bool) $get('received')),
            ])
            ->action(function (Model $record, array $data, Component $livewire): void {
                if (! $record instanceof Payable) {
                    return;
                }

                $received = (bool) ($data['received'] ?? false);

                $payment = $record->payments()->create([
                    'client_id' => $record->getAttribute('client_id'),
                    'amount' => (int) $data['amount'],
                    'method' => $data['method'],
                    'status' => $received ? PaymentStatus::Paid : PaymentStatus::Unpaid,
                    'paid_at' => $received ? now() : null,
                    'due_at' => $received ? null : today()->addDays(Settings::paymentDueDays()),
                ]);

                if ($received) {
                    PaymentNotifier::paymentReceived($payment, (bool) ($data['notify_client'] ?? false));
                }

                // The Platby table is a Livewire component of its own and would
                // otherwise keep showing the payments it loaded with the page.
                $livewire->dispatch(PaymentsRelationManager::REFRESH_EVENT);

                Notification::make()
                    ->title('Platba byla zaznamenána.')
                    ->success()
                    ->send();
            });
    }
}
