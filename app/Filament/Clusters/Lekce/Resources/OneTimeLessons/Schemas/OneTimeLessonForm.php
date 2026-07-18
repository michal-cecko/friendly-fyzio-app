<?php

namespace App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Schemas;

use App\Enums\OfferVisibility;
use App\Enums\UserRole;
use App\Filament\Support\Schemas\NotifyParticipantsToggle;
use App\Filament\Support\Schemas\PresenceBanner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OneTimeLessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                PresenceBanner::make(),
                Select::make('course_id')
                    ->label('Kurz')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('instructor_id')
                    ->label('Lektor')
                    ->relationship('instructor', 'name', fn (Builder $query): Builder => $query->whereIn('role', [UserRole::Admin, UserRole::Therapist]))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('room_id')
                    ->label('Místnost')
                    ->relationship('room', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                TextInput::make('invoice_title')
                    ->label('Název pro fakturaci')
                    ->maxLength(255)
                    ->helperText('Použije se na fakturách a v e-mailech místo názvu kurzu.'),
                DatePicker::make('lesson_date')
                    ->label('Datum')
                    ->native(false)
                    ->required(),
                TimePicker::make('start_time')
                    ->label('Od')
                    ->native(false)
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->label('Do')
                    ->native(false)
                    ->seconds(false)
                    ->required(),
                TextInput::make('capacity')
                    ->label('Kapacita')
                    ->integer()
                    ->minValue(1)
                    ->required(),
                Toggle::make('auto_promote_waitlist')
                    ->label('Automaticky přidávat z čekací listiny')
                    ->helperText('Když se uvolní místo, systém sám osloví dalšího v pořadí. Vypněte, chcete-li přidávat z čekací listiny ručně.')
                    ->default(true)
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Cena')
                    ->integer()
                    ->minValue(0)
                    ->suffix('Kč')
                    ->required(),
                DateTimePicker::make('published_at')
                    ->label('Publikováno')
                    ->native(false)
                    ->helperText('Nepublikovaná lekce se na webu nezobrazuje a nelze ji rezervovat.'),
                ToggleButtons::make('visibility')
                    ->label('Viditelnost')
                    ->options(OfferVisibility::class)
                    ->default(OfferVisibility::Public)
                    ->inline()
                    ->required()
                    ->helperText('Soukromá lekce se ve veřejném archivu nezobrazuje — vidí ji jen přihlášení zákazníci a lze na ni pozvat přes přihlašovací odkaz.'),
                NotifyParticipantsToggle::make(),
            ]);
    }
}
