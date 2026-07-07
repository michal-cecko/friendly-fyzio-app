<?php

namespace App\Filament\Clusters\Obsah\Resources\Reviews;

use App\Filament\Clusters\Obsah\ObsahCluster;
use App\Filament\Clusters\Obsah\Resources\Reviews\Pages\CreateReview;
use App\Filament\Clusters\Obsah\Resources\Reviews\Pages\EditReview;
use App\Filament\Clusters\Obsah\Resources\Reviews\Pages\ListReviews;
use App\Filament\Clusters\Obsah\Resources\Reviews\Schemas\ReviewForm;
use App\Filament\Clusters\Obsah\Resources\Reviews\Tables\ReviewsTable;
use App\Models\Review;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $cluster = ObsahCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'author_name';

    public static function getModelLabel(): string
    {
        return 'recenze';
    }

    public static function getPluralModelLabel(): string
    {
        return 'recenze';
    }

    public static function getNavigationLabel(): string
    {
        return 'Recenze';
    }

    public static function form(Schema $schema): Schema
    {
        return ReviewForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
            'create' => CreateReview::route('/create'),
            'edit' => EditReview::route('/{record}/edit'),
        ];
    }
}
