<?php

namespace App\Enums;

use App\Models\CourseSeries;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a series is in its sales cycle. Only {@see self::Inactive} is a state
 * the app can't work out on its own — fullness is otherwise derived live from
 * the spot count in {@see CourseSeries::offerState()}, so
 * {@see self::Full} is purely a manual "stop taking sign-ups now" override.
 *
 * That is why it reads "Uzavřený" in the UI: the case name and its stored value
 * stay `full` (renaming them would mean migrating every existing row), but for
 * an admin it is a close-the-doors switch, not a report of the spot count.
 */
enum CourseSeriesStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Full = 'full';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Otevřený',
            self::Full => 'Uzavřený',
            self::Inactive => 'Neaktivní',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Full => 'warning',
            self::Inactive => 'gray',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Open => 'Běžný stav — série je v nabídce na webu a přihlašování běží. Až se kapacita naplní, web sám místo formuláře nabídne čekací listinu; přepínat na „Uzavřený“ ručně kvůli tomu nemusíte.',
            self::Full => 'Ruční uzavření přihlašování, i když jsou ještě volná místa. Série v nabídce zůstane, ale nabízí už jen čekací listinu — a to i tomu, kdo má přihlašovací odkaz.',
            self::Inactive => 'Připravujeme — série se ve výpisu kurzů vůbec neukáže a na stránce kurzu je místo formuláře jen informace, že přihlašování zatím není otevřené. Přihlásit se dá pouze přes přihlašovací odkaz (předprodej).',
        };
    }

    /**
     * Keyed by case value for a Radio / ToggleButtons `->descriptions()` call.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $status): array => $carry + [$status->value => $status->description()],
            [],
        );
    }
}
