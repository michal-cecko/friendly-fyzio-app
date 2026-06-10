<?php

namespace App\Filament\Support\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Password;

class ResetPasswordAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'resetPassword';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Reset hesla')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->modalHeading('Reset hesla')
            ->modalIcon(Heroicon::OutlinedKey)
            ->modalSubmitActionLabel('Potvrdit')
            ->schema([
                Radio::make('method')
                    ->hiddenLabel()
                    ->options([
                        'manual' => 'Nastavit nové heslo ručně',
                        'email' => 'Odeslat e-mail s odkazem pro obnovu hesla',
                    ])
                    ->descriptions([
                        'manual' => 'Zadejte konkrétní heslo a potvrďte ho.',
                        'email' => 'Uživateli přijde e-mail, kde si heslo nastaví sám.',
                    ])
                    ->default('manual')
                    ->required()
                    ->live(),
                TextInput::make('password')
                    ->label('Nové heslo')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required()
                    ->confirmed()
                    ->visible(fn (Get $get): bool => $get('method') === 'manual'),
                TextInput::make('password_confirmation')
                    ->label('Potvrzení hesla')
                    ->password()
                    ->revealable()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('method') === 'manual'),
            ])
            ->action(function (array $data, User $record): void {
                if (($data['method'] ?? 'manual') === 'email') {
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

                    return;
                }

                $record->update(['password' => $data['password']]);

                Notification::make()
                    ->title('Heslo bylo změněno')
                    ->success()
                    ->send();
            });
    }
}
