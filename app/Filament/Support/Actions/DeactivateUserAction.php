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
 * only shown for active accounts the current user is allowed to manage.
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

                // Already deactivated → only Reactivate applies.
                if ($record->isDeactivated()) {
                    return false;
                }

                // Never let someone lock themselves out.
                if ($actor instanceof User && $record->is($actor)) {
                    return false;
                }

                // Customers are any staff member's to deactivate; a colleague's
                // account is admins-only, and a peer admin/super-admin needs a
                // super-admin. See User::isManageableBy().
                return $record->isManageableBy($actor);
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
