<?php

namespace App\Filament\Support\Schemas;

use App\Models\StaffProfile;
use App\Support\Settings;
use Closure;
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
            ->default(0)
            ->required()
            ->native(false)
            ->helperText('Kolik času si terapeut nechává po každém termínu. Jednotlivé služby mohou mít vlastní hodnotu; kde ji nemají, platí tato.');
    }

    /**
     * The per-service override, for rows that name their therapist — the service
     * form. Left empty it inherits, which the placeholder and the helper text
     * both spell out using whichever therapist the row currently names.
     */
    public static function override(string $name = 'break_blocks', string $therapistField = 'therapist_id'): Select
    {
        return Select::make($name)
            ->label('Pauza po termínu')
            ->options(self::options())
            ->native(false)
            ->placeholder(fn (Get $get): string => 'Výchozí'.self::inheritedSuffix($get($therapistField)))
            ->helperText(fn (Get $get): string => self::inheritHelperText(
                self::defaultBlocksOf($get($therapistField)),
            ));
    }

    /**
     * The same override seen from the therapist's side, where the row names the
     * service and the therapist is the record being edited — so the inherited
     * value is handed in (as a value, or as a closure reading the form state)
     * rather than looked up from the row.
     */
    public static function inheriting(string $name = 'break_blocks', Closure|int|null $defaultBlocks = null): Select
    {
        $inherited = static function (Select $component) use ($defaultBlocks): ?int {
            $blocks = $defaultBlocks instanceof Closure
                ? $component->evaluate($defaultBlocks)
                : $defaultBlocks;

            return $blocks === null ? null : (int) $blocks;
        };

        return Select::make($name)
            ->label('Pauza po termínu')
            ->options(self::options())
            ->native(false)
            ->placeholder(fn (Select $component): string => 'Výchozí'.self::minutesSuffix($inherited($component)))
            ->helperText(fn (Select $component): string => self::inheritHelperText($inherited($component)));
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
     * What an empty value means, said out loud. An override is only ever absent,
     * never zero — „bez pauzy" is a deliberate choice the therapist's default
     * does not get to overrule.
     */
    private static function inheritHelperText(?int $defaultBlocks): string
    {
        return $defaultBlocks === null
            ? 'Prázdné = platí výchozí pauza terapeuta.'
            : 'Prázdné = platí výchozí pauza terapeuta ('.self::minutesLabel($defaultBlocks).').';
    }

    /**
     * „ (30 min)" for a named therapist, empty while the row has none yet.
     */
    private static function inheritedSuffix(mixed $therapistId): string
    {
        return self::minutesSuffix(self::defaultBlocksOf($therapistId));
    }

    private static function minutesSuffix(?int $blocks): string
    {
        return $blocks === null ? '' : ' ('.self::minutesLabel($blocks).')';
    }

    /**
     * The default break of the named therapist, or null while the row has no
     * therapist yet (or names one that has since gone).
     */
    private static function defaultBlocksOf(mixed $therapistId): ?int
    {
        if (blank($therapistId)) {
            return null;
        }

        $blocks = StaffProfile::query()->whereKey($therapistId)->value('break_blocks');

        return $blocks === null ? null : (int) $blocks;
    }
}
