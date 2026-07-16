<?php

namespace App\Filament\Clusters\System\Pages;

use App\Filament\Clusters\System\Pages\Concerns\SettingsGroupPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PlatbySettings extends SettingsGroupPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Platby';

    protected static ?string $title = 'Platby';

    protected static ?int $navigationSort = 11;

    protected static function group(): string
    {
        return 'Platby';
    }
}
