<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class SectionHeadingBrick extends Brick
{
    public static function getId(): string
    {
        return 'section-heading';
    }

    public static function getLabel(): string
    {
        return 'Nadpis sekce';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedBars3BottomLeft;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.section-heading', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema(Fields::heading(titleRequired: true));
    }
}
