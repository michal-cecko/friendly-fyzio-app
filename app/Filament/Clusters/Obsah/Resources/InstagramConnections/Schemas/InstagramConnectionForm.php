<?php

namespace App\Filament\Clusters\Obsah\Resources\InstagramConnections\Schemas;

use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\InstagramConnection;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstagramConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                PresenceBanner::make(),
                Section::make('Připojený účet')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('authorize_hint')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->content('Účet vytvořte a poté klikněte na „Autorizovat“ pro připojení přes Instagram. Údaje níže se doplní automaticky po přihlášení.')
                            ->visible(fn (?InstagramConnection $record): bool => $record === null || $record->needsReauthorization()),
                        TextEntry::make('username')
                            ->label('Účet')
                            ->placeholder('Nepřipojeno')
                            ->formatStateUsing(fn (?string $state): string => $state ? '@'.$state : 'Nepřipojeno'),
                        TextEntry::make('status')
                            ->label('Stav')
                            ->badge(),
                        TextEntry::make('token_expires_at')
                            ->label('Platnost tokenu do')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('last_synced_at')
                            ->label('Poslední synchronizace')
                            ->dateTime()
                            ->placeholder('Zatím neproběhla'),
                        TextEntry::make('last_error')
                            ->label('Poslední chyba')
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (?InstagramConnection $record): bool => filled($record?->last_error)),
                    ]),

                Section::make('Nastavení')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Aktivní')
                            ->helperText('Neaktivní účty se nesynchronizují a nezobrazují na webu.')
                            ->default(true),
                    ]),
            ]);
    }
}
