<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Schemas;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\OneTimeLesson;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OneTimeLessonBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('lesson_id')
                    ->label('Lekce')
                    ->relationship('lesson', 'lesson_date')
                    ->getOptionLabelFromRecordUsing(fn (OneTimeLesson $record): ?string => trim(($record->course?->name ?? '').' — '.($record->lesson_date?->format('d.m.Y') ?? '')))
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
