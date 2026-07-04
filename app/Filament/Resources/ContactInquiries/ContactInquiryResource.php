<?php

namespace App\Filament\Resources\ContactInquiries;

use App\Enums\ContactInquiryStatus;
use App\Filament\Resources\ContactInquiries\Pages\ListContactInquiries;
use App\Filament\Resources\ContactInquiries\Pages\ViewContactInquiry;
use App\Filament\Resources\ContactInquiries\Schemas\ContactInquiryInfolist;
use App\Filament\Resources\ContactInquiries\Tables\ContactInquiriesTable;
use App\Models\ContactInquiry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContactInquiryResource extends Resource
{
    protected static ?string $model = ContactInquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'zpráva';
    }

    public static function getPluralModelLabel(): string
    {
        return 'zprávy';
    }

    public static function getNavigationLabel(): string
    {
        return 'Zprávy z webu';
    }

    public static function getNavigationBadge(): ?string
    {
        $new = static::getModel()::where('status', ContactInquiryStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContactInquiryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactInquiriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactInquiries::route('/'),
            'view' => ViewContactInquiry::route('/{record}'),
        ];
    }
}
