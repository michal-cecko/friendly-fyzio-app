<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Models\User;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Team grid auto-populated from the users with a published therapist profile —
 * staff therapists and administrators acting as therapists alike. Users without
 * a published profile stay off the public team page (they can still be booked
 * through the reservation wizard); a card links to the profile detail when the
 * profile has a slug, otherwise it renders non-clickable.
 */
class TeamBrick extends Brick
{
    public static function getId(): string
    {
        return 'team';
    }

    public static function getLabel(): string
    {
        return 'Náš tým';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedUserGroup;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        $therapists = User::query()
            ->whereHas('therapistProfile', fn ($query) => $query->published())
            ->with(['therapistProfile.specializations' => fn ($query) => $query->orderBy('display_order')])
            ->get()
            ->sortBy(fn (User $user): string => sprintf('%04d-%s', $user->therapistProfile?->display_order ?? 999, $user->name))
            ->values();

        return view('bricks.team', [
            'config' => $config,
            'therapists' => $therapists,
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Select::make('background')
                    ->label('Pozadí')
                    ->options(['white' => 'Bílé', 'alt' => 'Světle růžové'])
                    ->default('alt'),
                Select::make('columns')
                    ->label('Počet sloupců')
                    ->options([2 => '2', 3 => '3', 4 => '4'])
                    ->default(4),
            ]);
    }
}
