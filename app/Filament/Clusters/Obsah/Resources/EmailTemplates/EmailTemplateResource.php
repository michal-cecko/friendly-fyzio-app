<?php

namespace App\Filament\Clusters\Obsah\Resources\EmailTemplates;

use App\Filament\Clusters\Obsah\ObsahCluster;
use App\Filament\Clusters\Obsah\Resources\EmailTemplates\Pages\EditEmailTemplates;
use App\Filament\Clusters\Obsah\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Filament\Clusters\Obsah\Resources\EmailTemplates\Schemas\EmailTemplateForm;
use App\Filament\Clusters\Obsah\Resources\EmailTemplates\Tables\EmailTemplatesTable;
use App\Models\EmailTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $cluster = ObsahCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'e-mail';
    }

    public static function getPluralModelLabel(): string
    {
        return 'e-maily';
    }

    public static function getNavigationLabel(): string
    {
        return 'E-maily';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'subject'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var EmailTemplate $record */
        return array_filter([
            'Předmět' => $record->subject,
        ]);
    }

    // Fixed, seeded set of templates: admins edit but never create or delete.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return EmailTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'edit' => EditEmailTemplates::route('/{record}/edit'),
        ];
    }
}
