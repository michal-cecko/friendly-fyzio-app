<?php

namespace App\Filament\Clusters\System\Resources\ActivityLog\Schemas;

use App\Support\ActivityLog\ActivityPresenter;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\Activitylog\Models\Activity;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Přehled')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('created_at')
                            ->label('Kdy')
                            ->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('event')
                            ->label('Akce')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => ActivityPresenter::eventLabel($state))
                            ->color(fn (?string $state): string => ActivityPresenter::eventColor($state)),
                        TextEntry::make('causer')
                            ->label('Kdo')
                            ->state(fn (Activity $record): string => ActivityPresenter::causerLabel($record)),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('subject_type')
                            ->label('Typ záznamu')
                            ->formatStateUsing(fn (?string $state): string => ActivityPresenter::subjectLabel($state)),
                        TextEntry::make('subject_title')
                            ->label('Záznam')
                            ->state(fn (Activity $record): string => ActivityPresenter::subjectTitle($record))
                            ->weight('bold'),
                    ]),
                    TextEntry::make('subject_id')
                        ->label('ID záznamu (UUID)')
                        ->fontFamily('mono')
                        ->copyable()
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make('Změny')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('attribute_changes')
                        ->hiddenLabel()
                        ->view('filament.activity.diff'),
                ]),
        ]);
    }
}
