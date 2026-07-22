<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Schemas;

use App\Enums\OfferVisibility;
use App\Filament\Support\Schemas\NotifyParticipantsToggle;
use App\Filament\Support\Schemas\PresenceBanner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class OneOffEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                PresenceBanner::make(),
                Select::make('event_category_id')
                    ->label('Kategorie')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('course_id')
                    ->label('Kurz')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('Volitelná vazba na kurz — akce převezme obrázek a popis kurzu, pokud nemá vlastní, a zobrazí se na stránce kurzu jako ochutnávka.'),
                TextInput::make('name')
                    ->label('Název')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('invoice_title')
                    ->label('Název pro fakturaci')
                    ->maxLength(255)
                    ->helperText('Použije se na fakturách a v e-mailech místo běžného názvu.')
                    ->columnSpanFull(),
                Select::make('instructor_id')
                    ->label('Lektor')
                    ->relationship('instructor', 'name', fn (Builder $query): Builder => $query->lecturers())
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
                Textarea::make('description')
                    ->label('Popis')
                    ->rows(4)
                    ->columnSpanFull(),
                MediaPicker::make('featured_image')
                    ->label('Fotka')
                    ->acceptedFileTypes(['image/*'])
                    ->helperText('Zobrazuje se na kartě v archivu akcí a v hlavičce detailu.')
                    ->columnSpanFull(),
                DatePicker::make('event_date')
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
                    ->native(false),
                ToggleButtons::make('visibility')
                    ->label('Viditelnost')
                    ->options(OfferVisibility::class)
                    ->default(OfferVisibility::Public)
                    ->inline()
                    ->required()
                    ->helperText('Soukromá akce se ve veřejném archivu nezobrazuje — vidí ji jen přihlášení zákazníci a lze na ni pozvat přes přihlašovací odkaz.'),
                NotifyParticipantsToggle::make(),
            ]);
    }
}
