<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A minimal page-header band: a centered title and subtitle on the light accent
 * background. Meant to sit at the very top of a content page (Ceník, Kontakt, …).
 */
class PageIntroBrick extends Brick
{
    public static function getId(): string
    {
        return 'page-intro';
    }

    public static function getLabel(): string
    {
        return 'Úvod stránky';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedWindow;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.page-intro', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema(Fields::heading(titleRequired: true));
    }
}
