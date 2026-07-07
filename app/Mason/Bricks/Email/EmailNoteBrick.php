<?php

namespace App\Mason\Bricks\Email;

use App\Mason\Support\EmailFields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Small centered fine-print under the main content (storno terms, disclaimers).
 */
class EmailNoteBrick extends Brick
{
    public static function getId(): string
    {
        return 'email-note';
    }

    public static function getLabel(): string
    {
        return 'Poznámka (drobné písmo)';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedChatBubbleBottomCenterText;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('emails.bricks.note', ['config' => $config])->render();
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
