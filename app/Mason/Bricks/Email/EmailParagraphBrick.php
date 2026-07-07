<?php

namespace App\Mason\Bricks\Email;

use App\Mason\Support\EmailFields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A body paragraph of running copy. Supports {{ tokens }} for personalisation.
 */
class EmailParagraphBrick extends Brick
{
    public static function getId(): string
    {
        return 'email-paragraph';
    }

    public static function getLabel(): string
    {
        return 'Odstavec';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedBars3BottomLeft;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('emails.bricks.paragraph', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                EmailFields::richText('text', 'Text', required: true),
            ]);
    }
}
