<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Schemas;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\WaitlistPromotionMode;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\Course;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class CourseSeriesForm
{
    /**
     * Both tabs use a 12-column container grid so several fields share a row.
     * Container (`@`) breakpoints rather than viewport ones, because the form
     * also renders inside the narrow série relation-manager modal, where a
     * viewport-based `lg` would pack fields into an unusably tight row.
     *
     * The `@container` itself has to be declared on {@see Tabs} — a {@see Tab}
     * renders no component wrapper of its own, so `gridContainer()` on a tab
     * emits no `fi-grid-ctn` element and every `@`-breakpoint span silently
     * falls back to the one-field-per-row default.
     */
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
                        Tab::make('Základní údaje')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->columns(['default' => 1, '@xl' => 12])
                            ->schema([
                                Select::make('course_id')
                                    ->label('Kurz')
                                    ->relationship('course', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->hidden(fn ($livewire): bool => $livewire instanceof RelationManager)
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                TextInput::make('name')
                                    ->label('Název')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                Select::make('instructor_id')
                                    ->label('Lektor')
                                    ->relationship('instructor', 'name', fn (Builder $query): Builder => $query->lecturers())
                                    // The lecturers() scope also filters the label lookup, so a série
                                    // assigned to a non-lecturer would show the raw UUID. Resolve the
                                    // selected value's label unscoped so the name always renders.
                                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder(fn (Get $get, $livewire): string => self::courseInstructorName($get, $livewire) ?? 'Lektor kurzu')
                                    ->helperText('Prázdné = sérii vede lektor kurzu. Vyplněním sérii převezme někdo jiný — a uvidí ji ve svém přehledu.')
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                TextInput::make('invoice_title')
                                    ->label('Název pro fakturaci')
                                    ->maxLength(255)
                                    ->helperText('Použije se na fakturách a v e-mailech místo názvu kurzu. Když zůstane prázdné, použije se název kurzu — název série se na fakturu doplní vždy.')
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                DatePicker::make('start_date')
                                    ->label('Začátek')
                                    ->native(false)
                                    ->required()
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                DatePicker::make('end_date')
                                    ->label('Konec')
                                    ->native(false)
                                    ->required()
                                    ->afterOrEqual('start_date')
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                                TextInput::make('price')
                                    ->label('Cena')
                                    ->integer()
                                    ->minValue(0)
                                    ->suffix('Kč')
                                    ->required()
                                    ->columnSpan(['default' => 1, '@xl' => 6, '@3xl' => 4]),
                            ]),
                        Tab::make('Přihlašování')
                            ->icon(Heroicon::OutlinedUserPlus)
                            ->columns(['default' => 1, '@xl' => 12])
                            ->schema([
                                TextInput::make('capacity')
                                    ->label('Kapacita')
                                    ->integer()
                                    ->minValue(1)
                                    ->required()
                                    ->helperText('Kolik lidí se do série vejde. Po naplnění web přepne na čekací listinu sám.')
                                    ->columnSpan(['default' => 1, '@xl' => 4, '@4xl' => 3]),
                                Radio::make('visibility')
                                    ->label('Viditelnost')
                                    ->options(CourseSeriesVisibility::class)
                                    ->descriptions(CourseSeriesVisibility::descriptions())
                                    ->default(CourseSeriesVisibility::Public)
                                    ->required()
                                    ->columnSpan(['default' => 1, '@xl' => 8, '@4xl' => 9])
                                    ->columns(['default' => 1, '@2xl' => 2]),
                                Radio::make('status')
                                    ->label('Stav')
                                    ->options(CourseSeriesStatus::class)
                                    ->descriptions(CourseSeriesStatus::descriptions())
                                    ->default(CourseSeriesStatus::Open)
                                    ->required()
                                    ->columns(['default' => 1, '@2xl' => 3])
                                    ->columnSpanFull(),
                                Radio::make('waitlist_promotion_mode')
                                    ->label('Když se uvolní místo')
                                    ->options(WaitlistPromotionMode::class)
                                    ->descriptions(WaitlistPromotionMode::descriptions())
                                    ->default(WaitlistPromotionMode::AutomaticAdd)
                                    ->required()
                                    ->columns(['default' => 1, '@2xl' => 3])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    /**
     * The instructor a série falls back to when it names none of its own. Read
     * from the owner record when the form is opened from a course's Série tab,
     * where the kurz picker is hidden and the field therefore stays empty.
     */
    private static function courseInstructorName(Get $get, mixed $livewire = null): ?string
    {
        $course = $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Course
            ? $livewire->getOwnerRecord()
            : Course::find($get('course_id'));

        return $course?->instructor?->name;
    }
}
