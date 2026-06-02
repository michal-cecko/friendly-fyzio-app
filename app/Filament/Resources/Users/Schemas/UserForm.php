<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Účet')
                            ->icon(Heroicon::OutlinedUser)
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Jméno')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->label('Telefon')
                                    ->tel()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Heslo')
                            ->icon(Heroicon::OutlinedLockClosed)
                            ->columns(2)
                            ->schema([
                                TextInput::make('password')
                                    ->label('Heslo')
                                    ->password()
                                    ->revealable()
                                    ->minLength(8)
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->confirmed(),
                                TextInput::make('password_confirmation')
                                    ->label('Potvrzení hesla')
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(false),
                            ]),
                        Tab::make('Oprávnění')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->schema([
                                Select::make('role')
                                    ->label('Role')
                                    ->options(UserRole::class)
                                    ->required()
                                    ->native(false)
                                    ->helperText('Určuje přístup do administrace i odpovídající roli oprávnění.'),
                                Select::make('permissions')
                                    ->label('Přímá oprávnění')
                                    ->relationship('permissions', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('Volitelná oprávnění přidělená tomuto uživateli nad rámec jeho role.'),
                            ]),
                    ]),
            ]);
    }
}
