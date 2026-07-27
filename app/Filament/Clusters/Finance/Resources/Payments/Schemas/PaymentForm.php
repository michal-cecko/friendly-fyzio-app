<?php

namespace App\Filament\Clusters\Finance\Resources\Payments\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Support\Settings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Correcting a payment after the fact — a mistyped amount, cash that turned out
 * to be a transfer, a date entered wrong. Payments are never created through
 * this form ({@see PaymentResource::canCreate()}); they are raised by
 * "Zaznamenat platbu" and by the system.
 *
 * Number and variable symbol are deliberately absent: both are allocated once
 * and are what bank transfers and issued documents are matched on.
 */
class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('amount')
                    ->label('Částka')
                    ->integer()
                    ->required()
                    ->minValue(1)
                    ->suffix('Kč')
                    ->helperText('Změna částky přepočítá, zda je záznam uhrazený.')
                    ->columnSpan(1),
                Select::make('method')
                    ->label('Způsob platby')
                    ->options(PaymentMethod::class)
                    ->required()
                    ->native(false)
                    ->columnSpan(1),
                Select::make('status')
                    ->label('Stav')
                    ->options(PaymentStatus::class)
                    ->required()
                    ->native(false)
                    ->live()
                    // Keep the two date fields consistent with the state being
                    // set, so switching to Zaplaceno never leaves the payment
                    // paid with no date on it.
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                        if ($state === PaymentStatus::Paid->value) {
                            if (blank($get('paid_at'))) {
                                $set('paid_at', now());
                            }

                            return;
                        }

                        $set('paid_at', null);

                        if (blank($get('due_at'))) {
                            $set('due_at', today()->addDays(Settings::paymentDueDays()));
                        }
                    })
                    ->columnSpan(1),
                DateTimePicker::make('paid_at')
                    ->label('Zaplaceno')
                    ->native(false)
                    ->seconds(false)
                    ->required(fn (Get $get): bool => $get('status') === PaymentStatus::Paid->value)
                    ->visible(fn (Get $get): bool => $get('status') === PaymentStatus::Paid->value)
                    ->columnSpan(1),
                DatePicker::make('due_at')
                    ->label('Splatnost')
                    ->native(false)
                    ->visible(fn (Get $get): bool => in_array($get('status'), PaymentStatus::openValues(), true))
                    ->columnSpan(1),
            ]);
    }
}
