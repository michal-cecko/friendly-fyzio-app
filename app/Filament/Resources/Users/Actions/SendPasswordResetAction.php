<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Password;

class SendPasswordResetAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendPasswordReset';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Reset hesla')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Odeslat odkaz pro obnovu hesla')
            ->modalDescription(fn (User $record): string => "Uživateli {$record->email} bude odeslán e-mail s odkazem pro nastavení nového hesla.")
            ->modalSubmitActionLabel('Odeslat')
            ->action(function (User $record): void {
                $status = Password::sendResetLink(['email' => $record->email]);

                if ($status === Password::RESET_LINK_SENT) {
                    Notification::make()
                        ->title('Odkaz pro obnovu hesla byl odeslán')
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Odkaz se nepodařilo odeslat')
                    ->body(__($status))
                    ->danger()
                    ->send();
            });
    }
}
