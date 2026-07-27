<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\Schemas;

use App\Filament\Support\Schemas\DeactivatedBanner;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\User;
use App\Support\Media;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DeactivatedBanner::make(),
                Section::make('Účet')
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Jméno'),
                        TextEntry::make('account_status')
                            ->label('Stav účtu')
                            ->badge()
                            ->state(fn (User $record): string => $record->isDeactivated() ? 'Deaktivován' : 'Aktivní')
                            ->color(fn (User $record): string => $record->isDeactivated() ? 'danger' : 'success'),
                        TextEntry::make('title_before')->label('Titul před jménem')->placeholder('—'),
                        TextEntry::make('title_after')->label('Titul za jménem')->placeholder('—'),
                        TextEntry::make('email')
                            ->label('E-mail')
                            ->copyable()
                            ->icon(Heroicon::OutlinedEnvelope),
                        TextEntry::make('phone')->label('Telefon')->placeholder('—'),
                        IconEntry::make('email_verified_at')->label('Ověřen e-mail?')->boolean(),
                        TextEntry::make('deactivated_at')
                            ->label('Deaktivováno')
                            ->dateTime('d.m.Y H:i')
                            ->visible(fn (User $record): bool => $record->isDeactivated()),
                        RecordTimestamps::entries(),
                    ]),
                Section::make('Veřejný profil')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->collapsible()
                    ->visible(fn (User $record): bool => $record->staffProfile()->exists())
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                ImageEntry::make('staffProfile.photo')
                                    ->label('Fotografie')
                                    ->circular()
                                    ->columnSpan(1)
                                    ->state(fn (User $record): ?string => Media::url($record->staffProfile?->photo, '200'))
                                    ->visible(fn (User $record): bool => filled($record->staffProfile?->photo)),
                                Grid::make(2)
                                    ->columnSpan(2)
                                    ->schema([
                                        TextEntry::make('staffProfile.title')->label('Pozice')->placeholder('—'),
                                        TextEntry::make('staffProfile.badge')->label('Odznak')->placeholder('—'),
                                        TextEntry::make('staffProfile.slug')->label('URL název')->placeholder('—'),
                                        IconEntry::make('staffProfile.is_collaborator')
                                            ->label('Spolupracující terapeut')
                                            ->boolean(),
                                        TextEntry::make('staffProfile.display_order')->label('Pořadí')->numeric(),
                                        TextEntry::make('staffProfile.published_at')
                                            ->label('Publikováno')
                                            ->dateTime('d.m.Y H:i')
                                            ->placeholder('Koncept'),
                                    ]),
                            ]),
                        TextEntry::make('staffProfile.bio')
                            ->label('Medailonek')
                            ->html()
                            ->prose()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('staffProfile.specializations.specialization.name')
                            ->label('Specializace')
                            ->badge()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        RepeatableEntry::make('staffProfile.education')
                            ->label('Vzdělání')
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('degree')->label('Titul / obor')->columnSpanFull(),
                                TextEntry::make('institution')->label('Instituce')->placeholder('—'),
                                TextEntry::make('period')->label('Období')->placeholder('—'),
                            ])
                            ->visible(fn (User $record): bool => filled($record->staffProfile?->education)),
                        RepeatableEntry::make('staffProfile.certifications')
                            ->label('Certifikace a kurzy')
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('name')->label('Název')->columnSpanFull(),
                                TextEntry::make('institution')->label('Instituce')->placeholder('—'),
                                TextEntry::make('year')->label('Rok')->placeholder('—'),
                            ])
                            ->visible(fn (User $record): bool => filled($record->staffProfile?->certifications)),
                    ]),
                Section::make('Oprávnění')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('capabilities')
                            ->label('Schopnosti')
                            ->badge()
                            ->placeholder('Klient')
                            ->state(fn (User $record): array => $record->capabilities()
                                ->map(fn ($capability) => $capability->getLabel())
                                ->all()),
                        TextEntry::make('permissions.name')
                            ->label('Přímá oprávnění')
                            ->badge()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
