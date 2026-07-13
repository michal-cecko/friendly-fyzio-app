<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Schemas;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Support\Schemas\PresenceBanner;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CourseEnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                PresenceBanner::make(),
                Select::make('series_id')
                    ->label('Běh')
                    ->relationship('series', 'name')
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
                    ->options(CourseEnrollmentStatus::class)
                    ->default(CourseEnrollmentStatus::Active)
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
