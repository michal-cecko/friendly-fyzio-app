<?php

namespace App\Filament\Support\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Clears a full account deactivation (see User::deactivated_at) — e.g. after a
 * late-cancel „won't pay" storno decision has been resolved with the client. Only
 * shown for accounts that are actually deactivated.
 */
class ReactivateUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reactivate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Reaktivovat účet')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Reaktivovat účet')
            ->modalIcon(Heroicon::OutlinedLockOpen)
            ->modalDescription('Účet byl deaktivován (např. neuhrazené storno). Reaktivací klient znovu získá přístup k přihlášení i online rezervacím.')
            ->modalSubmitActionLabel('Reaktivovat')
            ->visible(fn (User $record): bool => $record->isDeactivated())
            ->action(function (User $record): void {
                $record->update([
                    'deactivated_at' => null,
                    'reactivated_at' => now(),
                ]);

                Notification::make()
                    ->title('Účet byl reaktivován.')
                    ->success()
                    ->send();
            });
    }
}
