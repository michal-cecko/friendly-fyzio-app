<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Guava\IconPicker\Forms\Components\IconPicker;
use Illuminate\Contracts\Support\Htmlable;

class QuoteBannerBrick extends Brick
{
    public static function getId(): string
    {
        return 'quote-banner';
    }

    public static function getLabel(): string
    {
        return 'Citace (pruh)';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedChatBubbleBottomCenterText;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.quote-banner', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                Fields::richText('text', 'Text', required: true),
                IconPicker::make('icon')
                    ->label('Ikona (volitelná)')
                    ->sets(['lucide'])
                    ->searchable(),
            ]);
    }
}
