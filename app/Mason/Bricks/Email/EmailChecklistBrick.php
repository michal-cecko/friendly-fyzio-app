<?php

namespace App\Mason\Bricks\Email;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The pink "Příprava na návštěvu" box: a title over a bulleted list of short lines.
 */
class EmailChecklistBrick extends Brick
{
    public static function getId(): string
    {
        return 'email-checklist';
    }

    public static function getLabel(): string
    {
        return 'Seznam bodů';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedListBullet;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('emails.bricks.checklist', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                TextInput::make('title')
                    ->label('Nadpis'),
                Repeater::make('items')
                    ->label('Body')
                    ->simple(
                        TextInput::make('text')
                            ->label('Text')
                            ->required(),
                    )
                    ->defaultItems(1)
                    ->reorderable(),
            ]);
    }
}
