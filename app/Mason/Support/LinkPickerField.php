<?php

namespace App\Mason\Support;

use App\Models\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;

/**
 * A reusable link picker: choose an internal Page or enter a custom URL.
 * Stores {prefix}link_type, {prefix}page_id, {prefix}url (+ optional {prefix}text).
 * Resolve on the frontend with App\Support\LinkResolver::fromConfig($config, $prefix).
 */
class LinkPickerField
{
    public static function make(string $prefix = '', ?string $label = null, bool $withText = false): Fieldset
    {
        $fields = [];

        if ($withText) {
            $fields[] = TextInput::make("{$prefix}text")
                ->label('Text odkazu');
        }

        $fields[] = Select::make("{$prefix}link_type")
            ->label('Typ odkazu')
            ->options(['page' => 'Stránka', 'custom' => 'Vlastní URL'])
            ->default('custom')
            ->live();

        $fields[] = Select::make("{$prefix}page_id")
            ->label('Stránka')
            ->options(fn (): array => Page::query()->orderBy('title')->pluck('title', 'id')->all())
            ->searchable()
            ->visible(fn (Get $get): bool => $get("{$prefix}link_type") === 'page');

        $fields[] = TextInput::make("{$prefix}url")
            ->label('URL')
            ->placeholder('https://…')
            ->visible(fn (Get $get): bool => ($get("{$prefix}link_type") ?? 'custom') === 'custom');

        return Fieldset::make($label ?? 'Odkaz')
            ->schema($fields)
            ->columns(1);
    }
}
