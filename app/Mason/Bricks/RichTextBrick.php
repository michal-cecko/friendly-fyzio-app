<?php

namespace App\Mason\Bricks;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\RichEditor\Plugins\MediaPlugin;

class RichTextBrick extends Brick
{
    public static function getId(): string
    {
        return 'rich-text';
    }

    public static function getLabel(): string
    {
        return 'Text';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedDocumentText;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.rich-text', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                RichEditor::make('content')
                    ->label('Obsah')
                    ->plugins([MediaPlugin::make()])
                    ->required(),
            ]);
    }
}
