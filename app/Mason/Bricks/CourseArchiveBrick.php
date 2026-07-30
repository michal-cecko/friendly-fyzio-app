<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Models\EventCategory;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Data-driven archive of movement courses: category pills, availability
 * toggle, text search and pagination — all URL-bound (handled by the
 * CourseArchive Livewire component). With the type switch on, the same archive
 * also lists one-off events from the configured event categories. Content
 * comes live from the Kurzy cluster; this brick only configures the shell.
 */
class CourseArchiveBrick extends Brick
{
    public static function getId(): string
    {
        return 'course-archive';
    }

    public static function getLabel(): string
    {
        return 'Archiv kurzů';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedAcademicCap;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.course-archive', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Toggle::make('show_filters')
                    ->label('Filtr kategorií a dostupnosti')
                    ->default(true),
                Toggle::make('show_search')
                    ->label('Vyhledávací pole')
                    ->default(true),
                Toggle::make('show_type_switch')
                    ->label('Přepínač kurzy / jednorázové akce')
                    ->default(false)
                    ->live()
                    ->helperText('Nad filtry přidá dvě karty přepínající stejný archiv mezi kurzy a jednorázovými akcemi.'),
                TextInput::make('courses_label')
                    ->label('Název karty kurzů')
                    ->default('Pohybové kurzy')
                    ->visible(fn (Get $get): bool => (bool) $get('show_type_switch')),
                TextInput::make('courses_subtitle')
                    ->label('Popisek karty kurzů')
                    ->default('Pravidelné semestrální série lekcí')
                    ->visible(fn (Get $get): bool => (bool) $get('show_type_switch')),
                TextInput::make('events_label')
                    ->label('Název karty akcí')
                    ->default('Jednorázové lekce')
                    ->visible(fn (Get $get): bool => (bool) $get('show_type_switch')),
                TextInput::make('events_subtitle')
                    ->label('Popisek karty akcí')
                    ->default('Jednotlivé lekce bez závazku')
                    ->visible(fn (Get $get): bool => (bool) $get('show_type_switch')),
                Select::make('event_categories')
                    ->label('Kategorie jednorázových akcí')
                    ->multiple()
                    ->options(fn (): array => EventCategory::query()
                        ->orderBy('display_order')
                        ->pluck('name', 'slug')
                        ->all())
                    ->visible(fn (Get $get): bool => (bool) $get('show_type_switch'))
                    ->helperText('Prázdné = všechny kategorie s přepínačem. Jedna vybraná = archiv je na ni napevno a přepínač kategorií se nezobrazí.'),
            ]);
    }
}
