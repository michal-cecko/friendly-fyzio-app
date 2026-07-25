<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\Schemas;

use App\Enums\Capability;
use App\Filament\Support\Schemas\DeactivatedBanner;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
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
                DeactivatedBanner::make(),
                PresenceBanner::make(),
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Účet')
                            ->icon(Heroicon::OutlinedUser)
                            ->columns(2)
                            ->schema([
                                // The two title fields come first in the markup so that on
                                // mobile (2-col grid) they pair at 50 % each on one row,
                                // with name / e-mail / phone full width below. On desktop
                                // (12-col) columnOrder slots `name` between the titles, so
                                // line one reads titul před · jméno · titul za and line two
                                // e-mail · telefon. columnOrder (not columnStart) is used
                                // because CSS grid auto-placement follows the order property.
                                Grid::make(['default' => 2, 'lg' => 12])
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('title_before')
                                            ->label('Titul před jménem')
                                            ->placeholder('Bc.')
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 1, 'lg' => 3])
                                            ->columnOrder(['lg' => 1]),
                                        TextInput::make('title_after')
                                            ->label('Titul za jménem')
                                            ->placeholder('DiS.')
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 1, 'lg' => 3])
                                            ->columnOrder(['lg' => 3]),
                                        TextInput::make('name')
                                            ->label('Jméno')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 2, 'lg' => 6])
                                            ->columnOrder(['lg' => 2]),
                                        TextInput::make('email')
                                            ->label('E-mail')
                                            ->email()
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 2, 'lg' => 6])
                                            ->columnOrder(['lg' => 4]),
                                        TextInput::make('phone')
                                            ->label('Telefon')
                                            ->tel()
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 2, 'lg' => 6])
                                            ->columnOrder(['lg' => 5]),
                                    ]),
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
                                    // Drives the "Veřejný profil" tab visibility reactively.
                                    ->live()
                                    ->options(fn (): array => collect(self::assignableCapabilities())
                                        ->mapWithKeys(fn (Capability $c): array => [$c->value => $c->getLabel()])
                                        ->all())
                                    ->descriptions([
                                        Capability::SuperAdmin->value => 'Nejvyšší přístup; jen super administrátor může přidělit administrátory a super administrátory.',
                                        Capability::Admin->value => 'Správa celé administrace.',
                                        Capability::Therapist->value => 'Rezervovatelný na terapie, kalendář a pracovní doba.',
                                        Capability::Lecturer->value => 'Může být lektorem kurzů, lekcí a workshopů.',
                                        Capability::Revenue->value => 'Vidí souhrny tržeb a dlužných částek; jen super administrátor může přidělit.',
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
                        Tab::make('Veřejný profil')
                            ->icon(Heroicon::OutlinedIdentification)
                            // Only staff who treat or teach get a public team profile.
                            // Shown when the Therapist/Lecturer capability is selected,
                            // or when a profile already exists (e.g. the assistant's).
                            ->visible(fn (Get $get, ?User $record): bool => self::hasStaffRole($get('capabilities') ?? [])
                                || (bool) $record?->staffProfile()->exists())
                            ->schema([
                                // hasOne saved together with the User form; a hidden tab
                                // is never dehydrated, so a non-staff user gets no profile.
                                Group::make()
                                    ->relationship('staffProfile')
                                    ->schema(StaffProfileSection::components()),
                            ]),
                    ]),
            ]);
    }

    /**
     * Whether a capability selection contains a role that warrants a public
     * team profile.
     *
     * @param  array<int, string>  $capabilities
     */
    protected static function hasStaffRole(array $capabilities): bool
    {
        return in_array(Capability::Therapist->value, $capabilities, true)
            || in_array(Capability::Lecturer->value, $capabilities, true);
    }
}
