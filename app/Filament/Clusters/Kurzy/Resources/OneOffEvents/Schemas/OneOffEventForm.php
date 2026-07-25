<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Schemas;

use App\Enums\OfferVisibility;
use App\Enums\WaitlistPromotionMode;
use App\Filament\Support\Schemas\DerivedSlug;
use App\Filament\Support\Schemas\NotifyParticipantsToggle;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\OneOffEvent;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
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
                    ->afterStateUpdated(DerivedSlug::syncFrom(OneOffEvent::class, 'akce')),
                DerivedSlug::field('Adresa akce na webu. Doplní se sama z názvu.'),
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
                Radio::make('waitlist_promotion_mode')
                    ->label('Když se uvolní místo')
                    ->options(WaitlistPromotionMode::class)
                    ->descriptions(WaitlistPromotionMode::descriptions())
                    ->default(WaitlistPromotionMode::AutomaticAdd)
                    ->required()
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
                    ->helperText('Soukromá akce se ve veřejném archivu nezobrazuje — vidí ji jen přihlášení zákazníci a lze na ni pozvat přes přihlašovací odkaz. Ten (i pozvánky) je proto dostupný jen u soukromé akce; u veřejné se tlačítko nezobrazuje.'),
                NotifyParticipantsToggle::make(),
            ]);
    }
}
