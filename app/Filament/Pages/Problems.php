<?php

namespace App\Filament\Pages;

use App\Support\Reservations\Conflict;
use App\Support\Reservations\ConflictFinder;
use App\Support\StaffScope;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Everything currently wrong with the schedule.
 *
 * Reached from the topbar rather than the sidebar ({@see resources/views/filament/topbar/problems-link.blade.php}):
 * it is something you glance at, not a section you navigate into, and it sits
 * next to Návrhy — what is wrong beside what is undone.
 */
class Problems extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Problémy';

    protected static ?string $title = 'Problémy';

    protected string $view = 'filament.pages.problems';

    /** Conflicts are checked up to a month ahead. */
    private const HORIZON_DAYS = 30;

    /**
     * Staff only. A pure therapist gets the same page narrowed to clashes they
     * are a party to; administrators see the whole clinic.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isTherapist());
    }

    /** The way in is the topbar icon, not a sidebar entry. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * The badge counts real conflicts; when there are none it falls back to the
     * expected overlaps, so the page stays discoverable without pretending
     * something is broken. Null when there is nothing at all to report — the
     * topbar icon then carries no badge.
     */
    public static function getNavigationBadge(): ?string
    {
        $hard = count(self::hardConflicts());

        if ($hard > 0) {
            return (string) $hard;
        }

        $soft = count(self::softConflicts());

        return $soft > 0 ? (string) $soft : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::hardConflicts() === [] ? 'warning' : 'danger';
    }

    /**
     * Memoised per request so the nav registration, the badge, its colour and the
     * page body share one sweep. Deliberately not cached inside
     * {@see ConflictFinder}, which must stay a pure query.
     *
     * @return list<Conflict>
     */
    private static function conflicts(): array
    {
        return once(fn (): array => StaffScope::current()->filterConflicts(
            ConflictFinder::upcoming(self::HORIZON_DAYS)
        ));
    }

    /**
     * @return list<Conflict>
     */
    private static function hardConflicts(): array
    {
        return array_values(array_filter(self::conflicts(), fn (Conflict $conflict): bool => $conflict->isHard()));
    }

    /**
     * @return list<Conflict>
     */
    private static function softConflicts(): array
    {
        return ConflictFinder::collapseRecurring(
            array_values(array_filter(self::conflicts(), fn (Conflict $conflict): bool => ! $conflict->isHard()))
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'problems' => self::hardConflicts(),
            'expected' => self::softConflicts(),
        ];
    }
}
