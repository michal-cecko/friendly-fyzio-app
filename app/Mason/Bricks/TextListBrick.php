<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Support\Mentions\StaffMentions;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Guava\IconPicker\Forms\Components\IconPicker;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Two-column section: prose (eyebrow, title, body, optional icon) beside a
 * highlighted list card. Used for the "Kontraindikace" blocks and the gentle
 * napářka conditions block on the massage/relaxation detail pages.
 */
class TextListBrick extends Brick
{
    public static function getId(): string
    {
        return 'text-list';
    }

    public static function getLabel(): string
    {
        return 'Text se seznamem';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedListBullet;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.text-list', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                TextInput::make('eyebrow')
                    ->label('Nadtitulek'),
                Fields::richText('title', 'Nadpis', required: true),
                RichEditor::make('body')
                    ->label('Text')
                    ->mentions([StaffMentions::editorProvider()])
                    ->toolbarButtons([
                        ['bold', 'italic', 'link', 'bulletList', 'orderedList', 'textColor'],
                    ]),
                IconPicker::make('icon')
                    ->label('Ikona u textu')
                    ->sets(['lucide'])
                    ->searchable(),
                Select::make('background')
                    ->label('Pozadí')
                    ->options(['white' => 'Bílé', 'alt' => 'Světle růžové'])
                    ->default('white'),
                Select::make('card_position')
                    ->label('Pozice karty')
                    ->options(['left' => 'Vlevo', 'right' => 'Vpravo'])
                    ->default('right'),
                Select::make('card_style')
                    ->label('Styl karty')
                    ->options(['warning' => 'Upozornění (žlutá)', 'soft' => 'Jemná (růžová)'])
                    ->default('warning'),
                IconPicker::make('card_icon')
                    ->label('Ikona karty')
                    ->sets(['lucide'])
                    ->searchable(),
                TextInput::make('card_title')
                    ->label('Nadpis karty'),
                Fields::richText('card_note', 'Popis karty'),
                Repeater::make('items')
                    ->label('Položky seznamu')
                    ->schema([
                        TextInput::make('text')
                            ->label('Text')
                            ->required(),
                    ])
                    ->defaultItems(3)
                    ->reorderable()
                    ->itemLabel(fn (array $state): ?string => $state['text'] ?? 'Položka'),
            ]);
    }
}
