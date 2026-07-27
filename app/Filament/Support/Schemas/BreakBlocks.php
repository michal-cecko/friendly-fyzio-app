<?php

namespace App\Filament\Support\Schemas;

use App\Models\StaffProfile;
use App\Support\Settings;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The „pauza po termínu" picker, shared by the therapist's profile (their
 * default) and by each service assignment (their override for that service).
 *
 * Breaks are counted in reservation blocks rather than minutes, because the
 * whole schedule is — but nobody thinks in blocks when reading a form, so every
 * option spells out what the count currently amounts to.
 */
class BreakBlocks
{
    /**
     * Eight blocks is two hours at the usual setting: far past anything a
     * turnaround could plausibly need, and short enough to stay a dropdown.
     */
    public const MAX = 8;

    /**
     * The therapist's own default, applied wherever no service overrides it.
     */
    public static function field(string $name = 'break_blocks'): Select
    {
        return Select::make($name)
            ->label('Pauza po termínu')
            ->options(self::options())
            ->default(1)
            ->required()
            ->native(false)
            ->helperText('Kolik času si terapeut nechává po každém termínu. Jednotlivé služby mohou mít vlastní hodnotu.');
    }

    /**
     * The per-service override. Left empty it inherits, which the placeholder
     * spells out using whichever therapist the row currently names.
     */
    public static function override(string $name = 'break_blocks', string $therapistField = 'therapist_id'): Select
    {
        return Select::make($name)
            ->label('Pauza po termínu')
            ->options(self::options())
            ->native(false)
            ->placeholder(fn (Get $get): string => 'Výchozí'.self::inheritedSuffix($get($therapistField)));
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [0 => 'Bez pauzy'];

        for ($blocks = 1; $blocks <= self::MAX; $blocks++) {
            $options[$blocks] = self::countLabel($blocks).' ('.self::minutes($blocks).' min)';
        }

        return $options;
    }

    public static function minutes(?int $blocks): int
    {
        return (int) $blocks * Settings::blockMinutes();
    }

    /**
     * How a resolved break reads in prose: „30 min" or „bez pauzy".
     */
    public static function minutesLabel(?int $blocks): string
    {
        $minutes = self::minutes($blocks);

        return $minutes > 0 ? $minutes.' min' : 'bez pauzy';
    }

    private static function countLabel(int $blocks): string
    {
        $word = match (true) {
            $blocks === 1 => 'blok',
            $blocks <= 4 => 'bloky',
            default => 'bloků',
        };

        return $blocks.' '.$word;
    }

    /**
     * „ (30 min)" for a named therapist, empty while the row has none yet.
     */
    private static function inheritedSuffix(mixed $therapistId): string
    {
        if (blank($therapistId)) {
            return '';
        }

        $blocks = StaffProfile::query()->whereKey($therapistId)->value('break_blocks');

        return $blocks === null ? '' : ' ('.self::minutesLabel((int) $blocks).')';
    }
}
