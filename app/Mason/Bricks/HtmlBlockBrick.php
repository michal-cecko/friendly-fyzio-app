<?php

namespace App\Mason\Bricks;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class HtmlBlockBrick extends Brick
{
    public static function getId(): string
    {
        return 'html-block';
    }

    public static function getLabel(): string
    {
        return 'HTML kód';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedCodeBracket;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.html-block', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                CodeEditor::make('html')
                    ->label('HTML kód')
                    ->language(Language::Html)
                    ->helperText('Vlastní HTML — např. vložený iframe třetí strany. Vkládejte pouze kód, kterému důvěřujete.')
                    ->required(),
                Toggle::make('contained')
                    ->label('Zobrazit v obsahovém kontejneru')
                    ->helperText('Vypněte pro obsah přes celou šířku stránky.')
                    ->default(true),
            ]);
    }
}
