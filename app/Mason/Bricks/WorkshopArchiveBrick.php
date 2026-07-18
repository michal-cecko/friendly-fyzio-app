<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Data-driven archive of workshops: upcoming published workshops with live
 * capacity and date on every card, optional URL-bound text search and a muted
 * "already happened" tail (handled by the WorkshopArchive Livewire component).
 */
class WorkshopArchiveBrick extends Brick
{
    public static function getId(): string
    {
        return 'workshop-archive';
    }

    public static function getLabel(): string
    {
        return 'Archiv workshopů';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedSparkles;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.workshop-archive', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Toggle::make('show_search')
                    ->label('Vyhledávací pole')
                    ->default(true),
                Toggle::make('show_past')
                    ->label('Zobrazit proběhlé workshopy')
                    ->default(true)
                    ->helperText('Nedávno proběhlé workshopy se zobrazí ztlumeně jako informace.'),
            ]);
    }
}
