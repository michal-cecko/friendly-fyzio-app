<?php

namespace App\Filament\Support\Actions;

use App\Contracts\Payable;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Support\Payments\PaymentNotifier;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Bulk "mark as paid": settles each selected unpaid Payable for its own
 * outstanding balance (no shared amount field). A received payment settles the
 * payable automatically (PaymentObserver) and — for cash — issues the PPD; the
 * client confirmation e-mail is controlled by the toggle.
 */
class MarkSignupsPaidBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'markSignupsPaid';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Označit jako zaplacené')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->modalHeading('Označit vybrané jako zaplacené')
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->modalDescription('Ke každému vybranému záznamu zaznamená přijatou platbu na zbývající částku. Platba v hotovosti automaticky vystaví příjmový pokladní doklad.')
            ->modalSubmitActionLabel('Označit jako zaplacené')
            ->schema([
                Select::make('method')
                    ->label('Způsob platby')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash->value)
                    ->required()
                    ->native(false),
                Toggle::make('notify_client')
                    ->label('Poslat potvrzení klientům')
                    ->default(false),
            ])
            ->action(function (Collection $records, array $data): void {
                $notify = (bool) ($data['notify_client'] ?? false);
                $paid = 0;

                $records->each(function (Model $record) use ($data, $notify, &$paid): void {
                    if (! $record instanceof Payable || $record->hasPaidStatus()) {
                        return;
                    }

                    $outstanding = max(0, $record->paymentAmountDue() - (int) $record->payments()
                        ->where('status', PaymentStatus::Paid->value)
                        ->sum('amount'));

                    if ($outstanding <= 0) {
                        return;
                    }

                    $payment = $record->payments()->create([
                        'client_id' => $record->getAttribute('client_id'),
                        'amount' => $outstanding,
                        'method' => $data['method'],
                        'status' => PaymentStatus::Paid,
                        'paid_at' => now(),
                    ]);

                    PaymentNotifier::paymentReceived($payment, $notify);
                    $paid++;
                });

                Notification::make()
                    ->title("Označeno jako zaplacené: {$paid}")
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
