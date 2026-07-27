<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonBookings\Schemas;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\Lesson;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class LessonBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                PresenceBanner::make(),
                Select::make('lesson_id')
                    ->label('Akce')
                    ->relationship('lesson', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Lesson $record): ?string => trim(($record->name ?? '').' — '.($record->lesson_date?->format('d.m.Y') ?? '')))
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
