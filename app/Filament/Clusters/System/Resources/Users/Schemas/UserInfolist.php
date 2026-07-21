<?php

namespace App\Filament\Clusters\System\Resources\Users\Schemas;

use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Účet')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Jméno')
                            ->state(fn (User $record): string => $record->full_name),
                        TextEntry::make('email')
                            ->label('E-mail')
                            ->copyable()
                            ->icon(Heroicon::OutlinedEnvelope),
                        TextEntry::make('phone')->label('Telefon')->placeholder('—'),
                        TextEntry::make('role')->label('Typ účtu')->badge(),
                        IconEntry::make('email_verified_at')->label('Ověřen email?')->boolean(),
                        RecordTimestamps::entries(),
                    ]),
                Section::make('Terapeut')
                    ->columns(2)
                    ->visible(fn (User $record): bool => $record->isTherapist())
                    ->schema([
                        IconEntry::make('staffProfile.is_collaborator')
                            ->label('Spolupracovník')
                            ->boolean(),
                        TextEntry::make('staffProfile.published_at')
                            ->label('Publikován')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('staffProfile.specializations.name')
                            ->label('Specializace')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('staffProfile.bio')
                            ->label('Bio')
                            ->prose()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
