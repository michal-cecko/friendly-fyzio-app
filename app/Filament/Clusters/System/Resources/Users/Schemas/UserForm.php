<?php

namespace App\Filament\Clusters\System\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                PresenceBanner::make(),
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
                                Grid::make(2)
                                    ->columnSpan(1)
                                    ->schema([
                                        TextInput::make('title_before')
                                            ->label('Titul před jménem')
                                            ->placeholder('Bc.')
                                            ->maxLength(255),
                                        TextInput::make('title_after')
                                            ->label('Titul za jménem')
                                            ->placeholder('DiS.')
                                            ->maxLength(255),
                                    ]),
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
                        Tab::make('Oprávnění')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->schema([
                                ToggleButtons::make('role')
                                    ->label('Role')
                                    ->options(collect(UserRole::cases())
                                        ->reject(fn (UserRole $role): bool => $role === UserRole::Customer)
                                        ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->getLabel()])
                                        ->all())
                                    ->required()
                                    ->inline()
                                    ->live()
                                    ->helperText('Určuje přístup do administrace i odpovídající roli oprávnění. Klienti se spravují v sekci Klienti.'),
                                Toggle::make('acts_as_therapist')
                                    ->label('Působí i jako terapeut')
                                    ->visible(fn (Get $get): bool => in_array($get('role'), [UserRole::Admin, UserRole::Admin->value], true))
                                    ->disabled(fn (?User $record): bool => $record?->staffProfile !== null)
                                    ->helperText(fn (?User $record): string => $record?->staffProfile !== null
                                        ? 'Administrátor má terapeutický profil. Pro vypnutí nejprve smažte jeho profil v sekci Terapeuti.'
                                        : 'Založí administrátorovi nepublikovaný profil terapeuta — objeví se v kalendáři, pracovní době a rezervacích.'),
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
