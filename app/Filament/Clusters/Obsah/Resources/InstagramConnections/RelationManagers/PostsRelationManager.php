<?php

namespace App\Filament\Clusters\Obsah\Resources\InstagramConnections\RelationManagers;

use App\Models\InstagramPost;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Synced Instagram posts shown as a grid of image cards (not a table): each
 * card previews the downloaded image with its caption and post date, and links
 * out to the original post on Instagram. Read-only — posts are pulled in by the
 * scheduled sync, never created or edited by hand.
 */
class PostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    protected static ?string $title = 'Synchronizované příspěvky';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedPhoto;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('posted_at', 'desc')
            ->modelLabel('příspěvek')
            ->pluralModelLabel('příspěvky')
            ->modifyQueryUsing(fn ($query) => $query->with('mediaLibraryItem'))
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginated([12, 24, 48])
            ->recordUrl(fn (InstagramPost $record): ?string => $record->permalink)
            ->openRecordUrlInNewTab()
            ->columns([
                Stack::make([
                    ImageColumn::make('image')
                        ->state(fn (InstagramPost $record): ?string => $record->imageUrl('400'))
                        ->height(240)
                        // Inline styles rather than Tailwind classes so the card
                        // image fills its width regardless of the admin theme's
                        // compiled utility set.
                        ->extraImgAttributes(['style' => 'width:100%;object-fit:cover;border-radius:0.5rem;'])
                        ->checkFileExistence(false),
                    TextColumn::make('caption')
                        ->limit(120)
                        ->wrap()
                        ->placeholder('Bez popisku')
                        ->color('gray'),
                    TextColumn::make('posted_at')
                        ->dateTime('d.m.Y H:i')
                        ->icon(Heroicon::OutlinedCalendar)
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->placeholder('—'),
                ])
                    ->space(2),
            ])
            ->emptyStateHeading('Zatím žádné příspěvky')
            ->emptyStateDescription('Po synchronizaci se zde zobrazí stažené příspěvky z Instagramu.')
            ->emptyStateIcon(Heroicon::OutlinedPhoto);
    }
}
