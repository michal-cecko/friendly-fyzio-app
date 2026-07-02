<?php

namespace App\Mason\Support;

use Filament\Forms\Components\Repeater;

/**
 * A reusable, reorderable repeater of buttons. Each button is a full button/link
 * definition (label, visual style, custom color, icon, and an internal page or
 * custom URL) built from the shared LinkPickerField. Resolve a button's URL on
 * the frontend with App\Support\LinkResolver::fromConfig($button) and render it
 * via the bricks/partials/button.blade.php partial.
 */
class ButtonsField
{
    public static function make(string $name = 'buttons', string $label = 'Tlačítka'): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->schema([
                LinkPickerField::make('', 'Odkaz', withText: true, withStyle: true, withColor: true, withIcon: true),
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
            'text' => 'Textový odkaz (inline)',
            'ghost' => 'Tlačítko bez pozadí',
            'white' => 'Bílé (na tmavém pozadí)',
        ];
    }
}
