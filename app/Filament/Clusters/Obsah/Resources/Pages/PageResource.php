<?php

namespace App\Filament\Clusters\Obsah\Resources\Pages;

use App\Filament\Clusters\Obsah\ObsahCluster;
use App\Filament\Clusters\Obsah\Resources\Pages\Pages\CreatePage;
use App\Filament\Clusters\Obsah\Resources\Pages\Pages\EditPage;
use App\Filament\Clusters\Obsah\Resources\Pages\Pages\ListPages;
use App\Filament\Clusters\Obsah\Resources\Pages\Schemas\PageForm;
use App\Filament\Clusters\Obsah\Resources\Pages\Tables\PagesTable;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $cluster = ObsahCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return 'stránka';
    }

    public static function getPluralModelLabel(): string
    {
        return 'stránky';
    }

    public static function getNavigationLabel(): string
    {
        return 'Stránky';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'slug'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Page $record */
        return array_filter([
            'Adresa' => '/'.ltrim($record->slug ?? '', '/'),
            'Stav' => $record->published_at ? 'Publikováno' : 'Koncept',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
