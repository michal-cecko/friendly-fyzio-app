<?php

namespace App\Mason\Support;

use App\Support\InternalLinks;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Guava\IconPicker\Forms\Components\IconPicker;

/**
 * A reusable link/button picker: choose an internal destination (CMS page,
 * reservation/login route, or service category) from one grouped dropdown, or enter
 * a custom URL — optionally with a label, visual style, custom color, icon and target.
 * Stores {prefix}link_type ('internal'|'custom'), {prefix}link_ref (a reference string,
 * see App\Support\InternalLinks) and {prefix}url plus, when enabled, {prefix}text,
 * {prefix}style, {prefix}color, {prefix}icon, {prefix}target.
 * Resolve the URL on the frontend with App\Support\LinkResolver::fromConfig($config, $prefix).
 */
class LinkPickerField
{
    public static function make(
        string $prefix = '',
        ?string $label = null,
        bool $withText = false,
        bool $withIcon = false,
        bool $withStyle = false,
        bool $withColor = false,
        bool $withTarget = false,
    ): Fieldset {
        $fields = [];

        if ($withText) {
            $fields[] = TextInput::make("{$prefix}text")
                ->label('Text')
                ->required($withStyle);
        }

        if ($withStyle) {
            $fields[] = Select::make("{$prefix}style")
                ->label('Styl')
                ->options(ButtonsField::styles())
                ->default('primary')
                ->live();
        }

        if ($withColor) {
            $fields[] = ColorPicker::make("{$prefix}color")
                ->label('Vlastní barva (nepovinné)');
        }

        if ($withIcon) {
            $fields[] = IconPicker::make("{$prefix}icon")
                ->label('Ikona (nepovinné)')
                ->sets(['lucide'])
                ->searchable();
        }

        $fields[] = Select::make("{$prefix}link_type")
            ->label('Typ odkazu')
            ->options(['internal' => 'Interní stránka', 'custom' => 'Vlastní URL'])
            ->default('internal')
            ->live()
            // Back-compat: the legacy 'page' type is just an internal link.
            ->formatStateUsing(fn (?string $state): string => $state === 'page' ? 'internal' : ($state ?? 'internal'));

        $fields[] = Select::make("{$prefix}link_ref")
            ->label('Cíl')
            ->options(fn (): array => InternalLinks::options())
            ->searchable()
            ->visible(fn (Get $get): bool => ($get("{$prefix}link_type") ?? 'internal') !== 'custom')
            // Back-compat: pre-select from a legacy page_id when no ref is stored yet.
            ->afterStateHydrated(function (Select $component, ?string $state, Get $get) use ($prefix): void {
                if (blank($state) && filled($get("{$prefix}page_id"))) {
                    $component->state('page:'.$get("{$prefix}page_id"));
                }
            });

        $fields[] = TextInput::make("{$prefix}url")
            ->label('URL')
            ->placeholder('https://… nebo /slug')
            ->visible(fn (Get $get): bool => ($get("{$prefix}link_type") ?? 'internal') === 'custom');

        if ($withTarget) {
            $fields[] = Select::make("{$prefix}target")
                ->label('Otevřít v')
                ->options(['_self' => 'Stejném okně', '_blank' => 'Novém okně'])
                ->default('_self');
        }

        return Fieldset::make($label ?? 'Odkaz')
            ->schema($fields)
            ->columns(1);
    }
}
