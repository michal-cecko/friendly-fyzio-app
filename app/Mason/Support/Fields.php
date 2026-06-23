<?php

namespace App\Mason\Support;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;

/**
 * Shared field groups reused across section bricks.
 */
class Fields
{
    /**
     * Eyebrow + title + subtitle heading fields. Title and subtitle are rich-text
     * editors (so words can be styled with the "Accent" color); the eyebrow stays
     * a plain kicker label.
     *
     * @return array<int, mixed>
     */
    public static function heading(bool $titleRequired = false): array
    {
        return [
            TextInput::make('eyebrow')
                ->label('Nadtitulek'),
            self::richText('title', 'Nadpis', $titleRequired),
            self::richText('subtitle', 'Podnadpis'),
        ];
    }

    /**
     * A compact rich-text editor for headings and short descriptions: inline
     * formatting plus the global "Accent" text color, without block-level tools.
     */
    public static function richText(string $name, string $label, bool $required = false): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->required($required)
            ->toolbarButtons([
                ['bold', 'italic', 'link', 'textColor'],
            ]);
    }
}
