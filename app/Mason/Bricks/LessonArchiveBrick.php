<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Models\EventCategory;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Data-driven archive of one-off events (workshopy, jednorázové lekce, …):
 * upcoming published events with live capacity and date on every card,
 * URL-bound availability toggle and text search, and a muted "already
 * happened" tail (handled by the LessonArchive Livewire component).
 * A configured category pins the archive to that category — the pattern the
 * category landing pages use; without it visitors switch categories via pills.
 */
class LessonArchiveBrick extends Brick
{
    public static function getId(): string
    {
        return 'event-archive';
    }

    public static function getLabel(): string
    {
        return 'Archiv lekcí';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedSparkles;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.lesson-archive', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Select::make('category')
                    ->label('Kategorie')
                    ->options(fn (): array => EventCategory::query()
                        ->orderBy('display_order')
                        ->pluck('name', 'slug')
                        ->all())
                    ->placeholder('Všechny kategorie (s přepínačem)')
                    ->helperText('S vybranou kategorií archiv zobrazuje jen její akce — takto je vložený na stránkách kategorií.'),
                Toggle::make('show_search')
                    ->label('Vyhledávací pole')
                    ->default(true),
                Toggle::make('show_past')
                    ->label('Zobrazit proběhlé akce')
                    ->default(true)
                    ->helperText('Nedávno proběhlé akce se zobrazí ztlumeně jako informace.'),
            ]);
    }
}
