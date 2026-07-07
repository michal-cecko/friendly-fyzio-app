<?php

namespace App\Mason\Bricks\Email;

use App\Mason\Support\EmailFields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Guava\IconPicker\Forms\Components\IconPicker;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A coloured notice box with an optional icon: the pending / confirmed / reminder /
 * "reply goes to therapist" banners from the design. The variant picks the palette.
 */
class EmailCalloutBrick extends Brick
{
    public static function getId(): string
    {
        return 'email-callout';
    }

    public static function getLabel(): string
    {
        return 'Upozornění';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedInformationCircle;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('emails.bricks.callout', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                Select::make('variant')
                    ->label('Barva')
                    ->options([
                        'info' => 'Růžová (informace)',
                        'success' => 'Zelená (potvrzení)',
                        'neutral' => 'Neutrální (poznámka)',
                    ])
                    ->default('info')
                    ->required(),
                IconPicker::make('icon')
                    ->label('Ikona')
                    ->sets(['lucide'])
                    ->searchable(),
                EmailFields::richText('text', 'Text', required: true),
            ]);
    }
}
