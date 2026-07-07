<?php

namespace App\Mason\Support;

use Filament\Forms\Components\RichEditor;

/**
 * Shared form fields for the email bricks. Unlike the website Fields::richText(),
 * the toolbar is limited to inline formatting only — no "Accent" text colour, which
 * relies on site CSS that never loads inside an email client.
 */
class EmailFields
{
    public static function richText(string $name, string $label, bool $required = false): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->required($required)
            ->toolbarButtons([
                ['bold', 'italic', 'link'],
            ]);
    }
}
