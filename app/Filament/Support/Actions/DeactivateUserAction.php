<?php

namespace App\Filament\Support\Actions;

use App\Models\User;
use App\Support\Clients\DeactivateAccount;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Fully deactivates an account (see User::deactivated_at): the person loses
 * admin-panel access, client-zone login and online booking, while the account
 * and its history stay on record. The mirror of {@see ReactivateUserAction};
 * only shown for active accounts the current admin is allowed to deactivate.
 */
class DeactivateUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deactivate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Deaktivovat účet')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Deaktivovat účet')
            ->modalIcon(Heroicon::OutlinedLockClosed)
            // Spells out the cascade — this takes down live bookings, so nobody
            // should be able to press it without seeing what goes with them.
            ->modalDescription(function (User $record): string {
                $base = 'Deaktivací účet ztratí přístup k přihlášení, do administrace i k online rezervacím. Účet a jeho historie zůstávají zachovány kvůli záznamům. Lze kdykoli reaktivovat.';

                $releases = app(DeactivateAccount::class)->previewSentence($record);

                if ($releases === null) {
                    return $base;
                }

                return $base.' Zároveň zrušíme '.$releases
                    .'. Uvolněná místa se nabídnou pořadníku a nezaplacené platby k těmto rezervacím označíme jako zrušené (dluhy za už proběhlé návštěvy zůstávají).';
            })
            ->modalSubmitActionLabel('Deaktivovat')
            ->visible(function (User $record): bool {
                $actor = auth()->user();

                if (! $actor instanceof User || ! $actor->isAdmin()) {
                    return false;
                }

                // Already deactivated → only Reactivate applies.
                if ($record->isDeactivated()) {
                    return false;
                }

                // Never let an admin lock themselves out.
                if ($record->is($actor)) {
                    return false;
                }

                // A peer admin/super-admin may only be deactivated by a
                // super-admin (mirrors UserResource::canDeleteUser).
                return $record->isAdmin() ? $actor->isSuperAdmin() : true;
            })
            ->action(function (User $record): void {
                $released = app(DeactivateAccount::class)($record);

                Notification::make()
                    ->title('Účet byl deaktivován.')
                    ->body(array_sum($released) > 0
                        ? 'Zrušili jsme '.$released['reservations'].' rezervací, '.($released['enrollments'] + $released['bookings']).' přihlášek a '.$released['waitlist'].' míst v pořadníku.'
                        : null)
                    ->success()
                    ->send();
            });
    }
}
