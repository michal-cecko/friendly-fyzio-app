<?php

namespace App\Filament\Pages;

use App\Enums\SuggestionGroup;
use App\Filament\Support\Concerns\InteractsWithSuggestions;
use App\Support\Suggestions\SuggestionFinder;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Everything waiting for a decision, grouped by domain — the full list behind
 * the dashboard's Návrhy card, plus the cards that were put away.
 *
 * Reached from the topbar rather than the sidebar
 * ({@see resources/views/filament/topbar/suggestions-link.blade.php}), next to
 * {@see Problems} — what is undone beside what is wrong. The icon stays put
 * even with nothing open, because hidden cards are offered back here and the
 * way in must not vanish with the last open one.
 */
class Suggestions extends Page
{
    use InteractsWithSuggestions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?string $navigationLabel = 'Návrhy';

    protected static ?string $title = 'Návrhy';

    protected string $view = 'filament.pages.suggestions';

    /**
     * Staff only. Anyone who treats or teaches gets the same page narrowed to
     * their own work; administrators see the whole clinic.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isTherapist() || $user?->isLecturer());
    }

    /** The way in is the topbar icon, not a sidebar entry. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getSubheading(): ?string
    {
        return 'Věci, které čekají na vaše rozhodnutí — systém je sám neudělá.';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SuggestionFinder::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'groups' => SuggestionFinder::grouped(),
            'groupLabels' => collect(SuggestionGroup::cases())
                ->mapWithKeys(fn (SuggestionGroup $group): array => [$group->value => $group->label()])
                ->all(),
            'hidden' => SuggestionFinder::hidden(),
            'total' => SuggestionFinder::count(),
        ];
    }
}
