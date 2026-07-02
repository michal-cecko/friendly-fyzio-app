<?php

namespace App\Mason\Bricks;

use App\Mason\Support\ButtonsField;
use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Guava\IconPicker\Forms\Components\IconPicker;
use Illuminate\Contracts\Support\Htmlable;

class CalloutBrick extends Brick
{
    public static function getId(): string
    {
        return 'callout';
    }

    public static function getLabel(): string
    {
        return 'Doporučení';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedLightBulb;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.callout', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                IconPicker::make('icon')
                    ->label('Ikona')
                    ->sets(['lucide'])
                    ->searchable(),
                Fields::richText('title', 'Nadpis', required: true),
                Fields::richText('body', 'Text'),
                Fields::richText('note', 'Zvýrazněná poznámka'),
                ButtonsField::make(),
            ]);
    }
}
