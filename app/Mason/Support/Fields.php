<?php

namespace App\Mason\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Shared field groups reused across section bricks.
 */
class Fields
{
    /**
     * Eyebrow + title + subtitle heading fields.
     *
     * @return array<int, mixed>
     */
    public static function heading(bool $titleRequired = false): array
    {
        return [
            TextInput::make('eyebrow')
                ->label('Nadtitulek'),
            TextInput::make('title')
                ->label('Nadpis')
                ->required($titleRequired),
            Textarea::make('subtitle')
                ->label('Podnadpis')
                ->rows(2),
        ];
    }
}
