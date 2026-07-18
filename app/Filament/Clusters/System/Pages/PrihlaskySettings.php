<?php

namespace App\Filament\Clusters\System\Pages;

use App\Filament\Clusters\System\Pages\Concerns\SettingsGroupPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PrihlaskySettings extends SettingsGroupPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Přihlášky';

    protected static ?string $title = 'Přihlášky';

    protected static ?int $navigationSort = 14;

    protected static function group(): string
    {
        return 'Přihlášky';
    }
}
