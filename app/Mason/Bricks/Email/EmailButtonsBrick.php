<?php

namespace App\Mason\Bricks\Email;

use App\Mason\Support\ButtonsField;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A centered row of call-to-action buttons (primary + outline in the design),
 * rendered as email-safe inline-styled anchors.
 */
class EmailButtonsBrick extends Brick
{
    public static function getId(): string
    {
        return 'email-buttons';
    }

    public static function getLabel(): string
    {
        return 'Tlačítka';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedCursorArrowRays;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('emails.bricks.buttons', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ButtonsField::make(),
            ]);
    }
}
