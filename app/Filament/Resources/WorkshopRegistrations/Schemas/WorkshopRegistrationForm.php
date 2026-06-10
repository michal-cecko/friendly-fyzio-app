<?php

namespace App\Filament\Resources\WorkshopRegistrations\Schemas;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class WorkshopRegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('workshop_id')
                    ->label('Workshop')
                    ->relationship('workshop', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('client_id')
                    ->label('Klient')
                    ->relationship('client', 'name', fn (Builder $query): Builder => $query->where('role', UserRole::Customer))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                ToggleButtons::make('status')
                    ->label('Stav')
                    ->options(['confirmed' => 'Potvrzeno', 'pending' => 'Čeká', 'cancelled' => 'Zrušeno'])
                    ->colors(['confirmed' => 'success', 'pending' => 'warning', 'cancelled' => 'danger'])
                    ->default('confirmed')
                    ->inline()
                    ->required(),
                ToggleButtons::make('payment_status')
                    ->label('Platba')
                    ->options(PaymentStatus::class)
                    ->default(PaymentStatus::Unpaid)
                    ->inline()
                    ->required(),
                DateTimePicker::make('paid_at')
                    ->label('Zaplaceno')
                    ->native(false),
            ]);
    }
}
