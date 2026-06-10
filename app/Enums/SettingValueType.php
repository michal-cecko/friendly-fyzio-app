<?php

namespace App\Enums;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Contracts\HasLabel;

enum SettingValueType: string implements HasLabel
{
    case Text = 'text';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Json = 'json';
    case Image = 'image';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Integer => 'Číslo',
            self::Boolean => 'Ano/Ne',
            self::Json => 'JSON',
            self::Image => 'Obrázek',
        };
    }

    /**
     * The Filament form component used to edit a setting of this type.
     */
    public function formComponent(string $key): TextInput|Toggle|Textarea|FileUpload
    {
        return match ($this) {
            self::Text => TextInput::make($key),
            self::Integer => TextInput::make($key)->numeric(),
            self::Boolean => Toggle::make($key),
            self::Json => Textarea::make($key)->rows(6),
            self::Image => FileUpload::make($key)->image()->disk('public')->visibility('public'),
        };
    }

    /**
     * Decode the raw stored string into a typed PHP value for display/use.
     */
    public function cast(?string $raw): mixed
    {
        if ($raw === null) {
            return match ($this) {
                self::Boolean => false,
                self::Json => [],
                default => null,
            };
        }

        return match ($this) {
            self::Integer => (int) $raw,
            self::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            self::Json => json_decode($raw, true) ?? [],
            self::Text, self::Image => $raw,
        };
    }

    /**
     * Encode a typed PHP value back into the string stored in the `value` column.
     */
    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Integer => (string) (int) $value,
            self::Boolean => $value ? '1' : '0',
            self::Json => is_string($value) ? $value : json_encode($value),
            self::Text, self::Image => (string) $value,
        };
    }
}
