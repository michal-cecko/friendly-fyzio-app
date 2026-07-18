<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\Actions;

use App\Enums\CreditTransactionType;
use App\Models\User;
use App\Support\Credits\CreditLedger;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Adds or deducts client credit — e.g. converting a gift voucher into credit,
 * or writing off a used balance. Everything goes through the CreditLedger, so
 * the balance and the client-zone history stay in sync.
 */
class AdjustCreditAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'adjustCredit';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Upravit kredit')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('gray')
            ->modalHeading('Upravit kredit klienta')
            ->modalDescription(fn (User $record): string => 'Aktuální zůstatek: '.number_format(CreditLedger::balanceFor($record), 0, ',', ' ').' Kč')
            ->schema([
                ToggleButtons::make('direction')
                    ->label('Akce')
                    ->options([
                        'add' => 'Připsat',
                        'deduct' => 'Odečíst',
                    ])
                    ->colors(['add' => 'success', 'deduct' => 'danger'])
                    ->default('add')
                    ->inline()
                    ->live()
                    ->required(),
                TextInput::make('amount')
                    ->label('Částka')
                    ->integer()
                    ->minValue(1)
                    ->suffix('Kč')
                    ->required(),
                TextInput::make('description')
                    ->label('Popis')
                    ->placeholder('Např. dárkový poukaz č. 123')
                    ->maxLength(255),
                DatePicker::make('expires_at')
                    ->label('Platnost do')
                    ->native(false)
                    ->helperText('Po tomto datu nevyčerpaný kredit propadne.')
                    ->visible(fn (Get $get): bool => $get('direction') === 'add'),
            ])
            ->action(function (array $data, User $record): void {
                $isTopUp = $data['direction'] === 'add';
                $amount = (int) $data['amount'];

                try {
                    CreditLedger::record(
                        $record,
                        $isTopUp ? $amount : -$amount,
                        $isTopUp ? CreditTransactionType::TopUp : CreditTransactionType::Deduction,
                        $data['description'] ?? null,
                        $isTopUp && filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null,
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('Kredit se nepodařilo upravit')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Kredit upraven')
                    ->body('Nový zůstatek: '.number_format(CreditLedger::balanceFor($record), 0, ',', ' ').' Kč')
                    ->success()
                    ->send();
            });
    }
}
