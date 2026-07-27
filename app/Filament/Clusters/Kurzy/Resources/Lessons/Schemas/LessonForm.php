<?php

namespace App\Filament\Clusters\Kurzy\Resources\Lessons\Schemas;

use App\Enums\OfferVisibility;
use App\Enums\WaitlistPromotionMode;
use App\Filament\Support\Schemas\DerivedSlug;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Lessons\ReleaseFreeSpots;
use App\Support\Settings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

/**
 * One form for both shapes of a lesson. The first tab is the session itself —
 * when, where, who teaches it — and applies to everything. The second tab is
 * what makes it sellable on its own: leave it empty on a lesson of a série and it
 * stays a plain schedule entry; fill it in and the same record becomes the
 * lekce people can buy.
 *
 * A standalone lesson has no série to borrow from, so its capacity, price,
 * category and name are required.
 *
 * Both tabs use a 12-column container grid so several fields share a row. The
 * sale tab splits that row further: the photo keeps a third of the width on the
 * left and the rest of the fields pair up two per row in a {@see Group} beside
 * it. Container (`@`) breakpoints rather than viewport ones, because the form
 * also renders inside the narrow série relation-manager modal. The `@container`
 * itself has to be declared on {@see Tabs} — a {@see Tab} renders no component
 * wrapper of its own, so `gridContainer()` on a tab emits no `fi-grid-ctn`
 * element and every `@`-breakpoint span silently falls back to one per row.
 */
class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                PresenceBanner::make(),
                Tabs::make()
                    ->columnSpanFull()
                    ->gridContainer()
                    ->tabs([
                        Tab::make('Termín')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->columns(['default' => 1, '@xl' => 12])
                            ->schema([
                                Select::make('series_id')
                                    ->label('Série')
                                    ->relationship('series', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->helperText('Prázdné = samostatná lekce nebo workshop.')
                                    ->hidden(fn ($livewire): bool => $livewire instanceof RelationManager)
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                Select::make('instructor_id')
                                    ->label('Lektor')
                                    ->relationship('instructor', 'name', fn (Builder $query): Builder => $query->lecturers())
                                    // The lecturers() scope also filters the label lookup, so a lesson
                                    // assigned to a non-lecturer would show the raw UUID. Resolve the
                                    // selected value's label unscoped so the name always renders.
                                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->helperText('Nabízejí se uživatelé s oprávněním lektor.')
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                Select::make('room_id')
                                    ->label('Místnost')
                                    ->relationship('room', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                DatePicker::make('lesson_date')
                                    ->label('Datum')
                                    ->native(false)
                                    ->required()
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                TimePicker::make('start_time')
                                    ->label('Od')
                                    ->native(false)
                                    ->seconds(false)
                                    ->required()
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                TimePicker::make('end_time')
                                    ->label('Do')
                                    ->native(false)
                                    ->seconds(false)
                                    ->required()
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                            ]),
                        Tab::make('Veřejný prodej')
                            ->icon(Heroicon::OutlinedShoppingCart)
                            ->columns(['default' => 1, '@xl' => 12])
                            ->schema([
                                // A tab carries no description of its own, so the guidance that used
                                // to sit under the section heading leads the panel instead.
                                Text::make(fn (Get $get, $livewire): HtmlString => self::standalone($get, $livewire)
                                    ? self::standaloneIntro()
                                    : self::seriesIntro())
                                    ->color('gray')
                                    ->columnSpanFull(),
                                // The one silent dead end: without a drop-in price on the course,
                                // ReleaseFreeSpots skips the lesson entirely and nothing here helps.
                                Text::make(fn (Get $get, $livewire): HtmlString => new HtmlString(
                                    'Kurz <strong>'.e((string) self::offerCourse($get, $livewire)?->name).'</strong> nemá vyplněnou <strong>Cenu jednorázového vstupu</strong>, takže se volná místa na jeho lekcích nezveřejňují — ani když cenu vyplníte tady. Doplňte ji v nastavení kurzu.'
                                ))
                                    ->color('danger')
                                    ->icon(Heroicon::OutlinedExclamationTriangle)
                                    ->visible(fn (Get $get, $livewire): bool => ! self::standalone($get, $livewire)
                                        && self::offerCourse($get, $livewire)?->drop_in_price === null)
                                    ->columnSpanFull(),
                                MediaPicker::make('featured_image')
                                    ->label('Fotka')
                                    ->acceptedFileTypes(['image/*'])
                                    ->helperText(fn (Get $get, $livewire): string => self::standalone($get, $livewire)
                                        ? 'Zobrazuje se na kartě v archivu lekcí a v hlavičce detailu.'
                                        : 'Zobrazuje se na kartě v archivu a v hlavičce detailu. Prázdné = použije se fotka kurzu.')
                                    ->columnSpan(['default' => 1, '@xl' => 4]),
                                Group::make()
                                    ->columns(['default' => 1, '@xl' => 2])
                                    ->columnSpan(['default' => 1, '@xl' => 8])
                                    ->schema([
                                        Select::make('event_category_id')
                                            ->label('Kategorie')
                                            ->relationship('category', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->required(fn (Get $get, $livewire): bool => self::standalone($get, $livewire))
                                            ->helperText(fn (Get $get, $livewire): string => self::standalone($get, $livewire)
                                                ? 'Tvoří adresu na webu a řadí lekci do archivu.'
                                                : 'Prázdné = při zveřejnění se doplní kategorie pro jednorázové lekce z nastavení.')
                                            ->columnSpan(1),
                                        Select::make('course_id')
                                            ->label('Kurz')
                                            ->relationship('course', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->visible(fn (Get $get, $livewire): bool => self::standalone($get, $livewire))
                                            ->helperText('Volitelná vazba na kurz — lekce převezme obrázek a popis kurzu, pokud nemá vlastní, a zobrazí se na stránce kurzu jako ochutnávka.')
                                            ->columnSpan(1),
                                        TextInput::make('name')
                                            ->label('Název')
                                            ->maxLength(255)
                                            ->required(fn (Get $get, $livewire): bool => self::standalone($get, $livewire))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(DerivedSlug::syncFrom(Lesson::class, 'akce'))
                                            ->helperText(fn (Get $get, $livewire): ?string => self::standalone($get, $livewire)
                                                ? null
                                                : 'Prázdné = odvodí se z názvu kurzu („… – jednorázová lekce“).')
                                            ->columnSpan(1),
                                        DerivedSlug::field(
                                            'Adresa lekce na webu. Doplní se sama z názvu — u lekce ze série až ve chvíli, kdy se volné místo zveřejní.',
                                            required: fn (Get $get, $livewire): bool => self::standalone($get, $livewire),
                                        )
                                            ->columnSpan(1),
                                        TextInput::make('invoice_title')
                                            ->label('Název pro fakturaci')
                                            ->maxLength(255)
                                            ->helperText('Použije se na fakturách a v e-mailech místo běžného názvu.')
                                            ->columnSpan(1),
                                        TextInput::make('capacity')
                                            ->label('Kapacita')
                                            ->integer()
                                            ->minValue(1)
                                            ->required(fn (Get $get, $livewire): bool => self::standalone($get, $livewire))
                                            ->helperText(fn (Get $get, $livewire): ?string => self::standalone($get, $livewire)
                                                ? null
                                                : 'Prázdné = kapacita série.')
                                            ->columnSpan(1),
                                        TextInput::make('price')
                                            ->label('Cena')
                                            ->integer()
                                            ->minValue(0)
                                            ->suffix('Kč')
                                            ->required(fn (Get $get, $livewire): bool => self::standalone($get, $livewire))
                                            ->helperText(function (Get $get, $livewire): ?string {
                                                if (self::standalone($get, $livewire)) {
                                                    return null;
                                                }

                                                $dropIn = self::offerCourse($get, $livewire)?->drop_in_price;

                                                return $dropIn === null
                                                    ? 'Prázdné = cena jednorázového vstupu z kurzu.'
                                                    : 'Prázdné = cena jednorázového vstupu z kurzu ('.$dropIn.' Kč).';
                                            })
                                            ->columnSpan(1),
                                        DateTimePicker::make('published_at')
                                            ->label('Publikováno')
                                            ->native(false)
                                            ->helperText(fn (Get $get, $livewire): string => self::standalone($get, $livewire)
                                                ? 'Bez data (nebo s datem v budoucnu) se lekce na webu nezobrazí.'
                                                : 'Vyplní se samo, až se volné místo zveřejní. Ručně jen když chcete lekci vypsat hned.')
                                            ->columnSpan(1),
                                        Textarea::make('description')
                                            ->label('Popis')
                                            ->rows(3)
                                            ->helperText(fn (Get $get, $livewire): string => self::standalone($get, $livewire)
                                                ? 'Krátké představení lekce pro kartu a detail na webu.'
                                                : 'Prázdné = použije se popis kurzu. Nekopíruje se — úprava popisu v kurzu se propíše i sem.')
                                            ->columnSpanFull(),
                                    ]),
                                Radio::make('waitlist_promotion_mode')
                                    ->label('Když se uvolní místo')
                                    ->options(WaitlistPromotionMode::class)
                                    ->descriptions(WaitlistPromotionMode::descriptions())
                                    ->default(WaitlistPromotionMode::AutomaticAdd)
                                    ->helperText(fn (Get $get, $livewire): string => self::standalone($get, $livewire)
                                        ? 'Prázdné se chová jako „Rovnou přihlásit“.'
                                        : 'Platí pro čekací listinu této jedné lekce. Volná místa z kurzu se nabízejí čekací listině série, a to nezávisle na tomto nastavení.')
                                    ->columnSpan(['default' => 1, '@xl' => 6]),
                                ToggleButtons::make('visibility')
                                    ->label('Viditelnost')
                                    ->options(OfferVisibility::class)
                                    ->default(OfferVisibility::Public)
                                    ->inline()
                                    ->required()
                                    ->helperText('Soukromá lekce se ve veřejném archivu nezobrazuje — vidí ji jen přihlášení zákazníci a lze na ni pozvat přes přihlašovací odkaz. Ten (i pozvánky) je proto dostupný jen u soukromé lekce; u veřejné se tlačítko nezobrazuje.')
                                    ->columnSpan(['default' => 1, '@xl' => 6]),
                            ]),
                    ]),
            ]);
    }

    /**
     * A standalone lesson is published by hand or not at all — nothing fills it
     * in and nothing puts it on the web on its own.
     */
    private static function standaloneIntro(): HtmlString
    {
        return new HtmlString(
            '<strong>Samostatná lekce se prodává sama za sebe.</strong> Potřebuje kategorii, název, kapacitu i cenu.<br>'
            .'Na webu se objeví, až vyplníte <strong>Publikováno</strong> a necháte viditelnost „Veřejná“ — samo se nic nezveřejní.'
        );
    }

    /**
     * A lesson of a série is the opposite: {@see ReleaseFreeSpots}
     * fills in and publishes everything, so the panel explains what will happen
     * rather than asking for input. The numbers come from the same settings the
     * job reads, so the text cannot drift from the behaviour.
     */
    private static function seriesIntro(): HtmlString
    {
        return new HtmlString(
            '<strong>Tady nemusíte vyplňovat nic.</strong> Když se na lekci uvolní místo, systém ho nabídne sám:<br>'
            .'nejdřív dostane přednost čekací listina série — místo jí drží '.Settings::waitlistInviteHours().' h a z webu ho zatím nikdo neobsadí — '
            .'a teprve když se nikdo neozve, lekci zveřejní jako jednorázovou. Kategorii, název i adresu si doplní, popis a fotku bere z kurzu.<br>'
            .'Prodej se zavře '.Settings::dropInCutoffHours().' h před začátkem a když se místo mezitím zase zaplní, lekce z webu zmizí.<br>'
            .'Pole níž slouží jen k přepsání toho, co by se doplnilo automaticky.'
        );
    }

    /**
     * The course a lesson of a série belongs to — the record that decides,
     * through its drop-in price, whether free places are ever sold at all.
     */
    private static function offerCourse(Get $get, mixed $livewire = null): ?Course
    {
        $series = $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof CourseSeries
            ? $livewire->getOwnerRecord()
            : CourseSeries::find($get('series_id'));

        return $series?->course;
    }

    /**
     * A lesson with no série stands on its own, so everything that makes it
     * sellable has to be filled in by hand.
     *
     * The série picker is hidden when the form is opened from a série's own
     * Lekce tab — there is nothing to choose. Reading the owner record rather
     * than the blank field keeps those lessons from being treated as standalone
     * and demanding a name, slug, capacity and price they inherit.
     */
    private static function standalone(Get $get, mixed $livewire = null): bool
    {
        if ($livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof CourseSeries) {
            return false;
        }

        return blank($get('series_id'));
    }
}
