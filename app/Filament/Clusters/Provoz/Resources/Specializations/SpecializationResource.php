<?php

namespace App\Filament\Clusters\Provoz\Resources\Specializations;

use App\Filament\Clusters\Provoz\ProvozCluster;
use App\Filament\Clusters\Provoz\Resources\Specializations\Pages\CreateSpecialization;
use App\Filament\Clusters\Provoz\Resources\Specializations\Pages\EditSpecialization;
use App\Filament\Clusters\Provoz\Resources\Specializations\Pages\ListSpecializations;
use App\Filament\Clusters\Provoz\Resources\Specializations\Schemas\SpecializationForm;
use App\Filament\Clusters\Provoz\Resources\Specializations\Tables\SpecializationsTable;
use App\Models\Specialization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The shared specialization catalogue. Each entry names the {@see Service} it
 * stands for, which is what turns it into a booking link on the therapist's
 * public profile — an unmapped entry has nowhere to send anyone, so this is
 * where that mapping is seen and fixed for the whole catalogue at once.
 *
 * The same mapping is editable from the other end, on the service form
 * („Možné specializace"). Both write `specializations.service_id`.
 */
class SpecializationResource extends Resource
{
    protected static ?string $model = Specialization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $cluster = ProvozCluster::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'specializace';
    }

    public static function getPluralModelLabel(): string
    {
        return 'specializace';
    }

    public static function getNavigationLabel(): string
    {
        return 'Specializace';
    }

    /**
     * Unmapped entries are the ones that need attention, so the badge counts them.
     */
    public static function getNavigationBadge(): ?string
    {
        $unmapped = Specialization::query()->whereNull('service_id')->count();

        return $unmapped > 0 ? (string) $unmapped : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Specializace bez přiřazené služby';
    }

    public static function form(Schema $schema): Schema
    {
        return SpecializationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpecializationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpecializations::route('/'),
            'create' => CreateSpecialization::route('/create'),
            'edit' => EditSpecialization::route('/{record}/edit'),
        ];
    }
}
