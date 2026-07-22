<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Schemas;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\OneOffEvent;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OneOffEventBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                PresenceBanner::make(),
                Select::make('one_off_event_id')
                    ->label('Akce')
                    ->relationship('event', 'name')
                    ->getOptionLabelFromRecordUsing(fn (OneOffEvent $record): ?string => trim(($record->name ?? '').' — '.($record->event_date?->format('d.m.Y') ?? '')))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('client_id')
                    ->label('Klient')
                    ->relationship('client', 'name', fn (Builder $query): Builder => $query->customers())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                ToggleButtons::make('status')
                    ->label('Stav')
                    ->options(BookingStatus::class)
                    ->default(BookingStatus::Confirmed)
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
