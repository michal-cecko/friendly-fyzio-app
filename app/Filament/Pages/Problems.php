<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Support\Reservations\ConflictFinder;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Problems extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Problémy';

    protected static ?string $title = 'Problémy';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.problems';

    /** Conflicts are checked up to a month ahead. */
    private const HORIZON_DAYS = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::Admin;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = count(ConflictFinder::upcoming(self::HORIZON_DAYS));

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['problems' => ConflictFinder::upcoming(self::HORIZON_DAYS)];
    }
}
