<?php

namespace App\Filament\Support\Schemas;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A slug field that is derived from another field (usually the name) and frozen
 * once the record exists: adjustable while creating, read-only afterwards, so a
 * record's public address cannot silently change under links already shared.
 */
class DerivedSlug
{
    /**
     * @param  string  $helperText  Shown while creating, when the slug can still be adjusted.
     */
    public static function field(string $helperText): TextInput
    {
        return TextInput::make('slug')
            ->label('Slug')
            ->required()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            // Disabled rather than read-only: a read-only field still takes its
            // value from the request, so the address would remain reachable to
            // a crafted submission. Disabled fields keep the stored value, and
            // dehydrating them keeps that value in the save payload.
            ->disabled(fn (string $operation): bool => $operation !== 'create')
            ->dehydrated()
            ->helperText(fn (string $operation): string => $operation === 'create'
                ? $helperText
                : 'Po vytvoření už slug nelze měnit, aby zůstaly funkční dříve sdílené odkazy.');
    }

    /**
     * An `afterStateUpdated()` handler for the field the slug is derived from.
     * It only fills the slug while creating — renaming an existing record must
     * leave its address alone.
     *
     * @param  class-string<Model>  $model
     */
    public static function syncFrom(string $model, string $fallback = 'kategorie'): Closure
    {
        return function (Set $set, ?string $state, string $operation) use ($model, $fallback): void {
            if ($operation !== 'create') {
                return;
            }

            $set('slug', static::from($state, $model, $fallback));
        };
    }

    /**
     * The slug a name resolves to, suffixed until it is free. Collisions are
     * resolved up front so creating two similarly named records does not stall
     * on a validation error.
     *
     * @param  class-string<Model>  $model
     */
    public static function from(?string $name, string $model, string $fallback = 'kategorie'): string
    {
        $base = Str::slug($name ?? '') ?: $fallback;
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
