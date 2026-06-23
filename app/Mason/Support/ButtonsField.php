<?php

namespace App\Mason\Support;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Guava\IconPicker\Forms\Components\IconPicker;

/**
 * A reusable, reorderable repeater of buttons. Each button has a visual style,
 * an optional icon, a label, and a link (internal page or custom URL) via the
 * shared LinkPickerField. Resolve a button's URL on the frontend with
 * App\Support\LinkResolver::fromConfig($button).
 */
class ButtonsField
{
    public static function make(string $name = 'buttons', string $label = 'Tlačítka'): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->schema([
                TextInput::make('text')
                    ->label('Text')
                    ->required(),
                Select::make('style')
                    ->label('Styl')
                    ->options(self::styles())
                    ->default('primary')
                    ->required(),
                IconPicker::make('icon')
                    ->label('Ikona (nepovinné)')
                    ->sets(['lucide'])
                    ->searchable(),
                LinkPickerField::make('', 'Odkaz'),
            ])
            ->reorderable()
            ->cloneable()
            ->collapsible()
            ->collapsed()
            ->defaultItems(0)
            ->itemLabel(fn (array $state): ?string => $state['text'] ?? 'Tlačítko');
    }

    /**
     * @return array<string, string>
     */
    public static function styles(): array
    {
        return [
            'primary' => 'Primární (růžové)',
            'secondary' => 'Sekundární (tmavé)',
            'outline' => 'Obrys',
            'ghost' => 'Text (terciární)',
            'white' => 'Bílé (na tmavém pozadí)',
        ];
    }
}
