<?php

namespace App\Filament\Clusters\System\Pages;

use App\Filament\Clusters\System\Pages\Concerns\SettingsGroupPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class FakturaceSettings extends SettingsGroupPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Fakturace';

    protected static ?string $title = 'Fakturace';

    protected static ?int $navigationSort = 10;

    protected static function group(): string
    {
        return 'Fakturace';
    }
}
