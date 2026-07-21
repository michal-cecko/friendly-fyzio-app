<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Models\EventCategory;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Data-driven archive of movement courses: category pills, availability
 * toggle, text search and pagination — all URL-bound (handled by the
 * CourseArchive Livewire component). One-off events live on their own category
 * pages; a configurable encouragement section under the grid cross-sells
 * course-derived events. Content comes live from the Kurzy cluster; this brick
 * only configures the section shell.
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
                Toggle::make('cross_sell')
                    ->label('Sekce „vyzkoušejte jednorázovou lekci“')
                    ->default(true)
                    ->live()
                    ->helperText('Pod mřížkou kurzů nabídne nejbližší jednorázové akce navázané na kurzy.'),
                TextInput::make('cross_sell_title')
                    ->label('Titulek sekce')
                    ->default('Chcete si to nejdřív vyzkoušet?')
                    ->visible(fn (Get $get): bool => (bool) ($get('cross_sell') ?? true)),
                Textarea::make('cross_sell_text')
                    ->label('Text sekce')
                    ->rows(2)
                    ->default('Přijďte na jednorázovou lekci bez závazku celého kurzu.')
                    ->visible(fn (Get $get): bool => (bool) ($get('cross_sell') ?? true)),
                Select::make('cross_sell_category')
                    ->label('Cílová kategorie akcí')
                    ->options(fn (): array => EventCategory::query()
                        ->orderBy('display_order')
                        ->pluck('name', 'slug')
                        ->all())
                    ->default('jednorazove-lekce')
                    ->visible(fn (Get $get): bool => (bool) ($get('cross_sell') ?? true))
                    ->helperText('Kam vede tlačítko „Všechny termíny“.'),
            ]);
    }
}
