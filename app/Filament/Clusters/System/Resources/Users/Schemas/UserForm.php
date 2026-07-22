<?php

namespace App\Filament\Clusters\System\Resources\Users\Schemas;

use App\Enums\Capability;
use App\Filament\Support\Schemas\PresenceBanner;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserForm
{
    /**
     * Capabilities the signed-in user may grant, or all of them when unauthenticated
     * (never happens in the panel, but keeps the schema pure for tests/tooling).
     *
     * @return list<Capability>
     */
    protected static function assignableCapabilities(): array
    {
        return auth()->user()?->assignableCapabilities() ?? Capability::cases();
    }

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
                                CheckboxList::make('capabilities')
                                    // Only the capabilities the current user may assign are shown;
                                    // Admin / super-admin appear only for a super-admin. Any the
                                    // actor can't manage are preserved on save by
                                    // User::applyCapabilitySelection(), so nothing is lost.
                                    ->label('Schopnosti')
                                    ->options(fn (): array => collect(self::assignableCapabilities())
                                        ->mapWithKeys(fn (Capability $c): array => [$c->value => $c->getLabel()])
                                        ->all())
                                    ->descriptions([
                                        Capability::SuperAdmin->value => 'Nejvyšší přístup; jen super administrátor může přidělit administrátory a super administrátory.',
                                        Capability::Admin->value => 'Správa celé administrace.',
                                        Capability::Therapist->value => 'Rezervovatelný na terapie, kalendář a pracovní doba.',
                                        Capability::Lecturer->value => 'Může být lektorem kurzů, lekcí a workshopů.',
                                    ])
                                    // Not a model column — the Create/Edit pages read it out of the
                                    // form data and apply it via User::applyCapabilitySelection().
                                    ->helperText('Bez zvolené schopnosti je uživatel pouze klient. Schopnosti lze libovolně kombinovat.'),
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
