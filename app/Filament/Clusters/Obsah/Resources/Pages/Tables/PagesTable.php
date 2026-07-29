<?php

namespace App\Filament\Clusters\Obsah\Resources\Pages\Tables;

use App\Filament\Clusters\Obsah\Resources\Pages\PageResource;
use App\Filament\Support\Actions\OpenPublicPageAction;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\Page;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('URL')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->label('Stav')
                    ->badge()
                    ->state(fn (Page $record): string => $record->isPublished() ? 'Publikováno' : 'Koncept')
                    ->color(fn (Page $record): string => $record->isPublished() ? 'success' : 'gray')
                    ->sortable(),
                IconColumn::make('is_system')
                    ->label('Systémová')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Pořadí')
                    ->sortable(),
                ...TimestampColumns::make(),
            ])
            ->filters([
                TernaryFilter::make('published')
                    ->label('Publikováno')
                    ->placeholder('Vše')
                    ->trueLabel('Publikované')
                    ->falseLabel('Koncepty')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->published(),
                        false: fn (Builder $query): Builder => $query->where(
                            fn (Builder $q): Builder => $q->whereNull('published_at')->orWhere('published_at', '>', now()),
                        ),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                OpenPublicPageAction::make(),
                EditAction::make(),
                self::duplicateAction(),
                DeleteAction::make()
                    ->visible(fn (Page $record): bool => ! $record->is_system),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Clone a finished page as a fresh draft, so a new one can start from an
     * existing layout instead of an empty canvas. The copy deliberately drops
     * everything that makes a page unique or owned: the system key and flag
     * (unique, and {@see Page::booted()} makes system pages undeletable) and the
     * pageable link — two pages must never claim the same public URL.
     */
    private static function duplicateAction(): ReplicateAction
    {
        return ReplicateAction::make()
            ->label('Duplikovat')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->modalHeading('Duplikovat stránku')
            ->modalDescription('Vznikne nová stránka jako koncept. Publikujete ji, až budete hotovi.')
            ->excludeAttributes(['system_key', 'is_system', 'published_at', 'pageable_type', 'pageable_id'])
            ->mutateRecordDataUsing(fn (array $data): array => [
                ...$data,
                'title' => ($data['title'] ?? '').' (kopie)',
                'slug' => Str::slug(($data['slug'] ?? '').'-kopie'),
            ])
            ->schema([
                TextInput::make('title')
                    ->label('Název')
                    ->required(),
                TextInput::make('slug')
                    ->label('URL název')
                    ->required()
                    ->unique(Page::class, 'slug'),
            ])
            ->beforeReplicaSaved(fn (Page $replica) => $replica->created_by = auth()->id())
            ->successNotificationTitle('Stránka zduplikována')
            ->successRedirectUrl(fn (Page $replica): string => PageResource::getUrl('edit', ['record' => $replica]));
    }
}
