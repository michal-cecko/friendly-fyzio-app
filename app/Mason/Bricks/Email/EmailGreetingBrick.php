<?php

namespace App\Mason\Bricks\Email;

use App\Mason\Support\EmailFields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The bold opening line of an email body, e.g. "Děkujeme za vaši rezervaci, {{ jmeno }},".
 */
class EmailGreetingBrick extends Brick
{
    public static function getId(): string
    {
        return 'email-greeting';
    }

    public static function getLabel(): string
    {
        return 'Oslovení';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedHandRaised;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('emails.bricks.greeting', ['config' => $config])->render();
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
