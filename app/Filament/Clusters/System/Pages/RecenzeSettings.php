<?php

namespace App\Filament\Clusters\System\Pages;

use App\Filament\Clusters\System\Pages\Concerns\SettingsGroupPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class RecenzeSettings extends SettingsGroupPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Recenze';

    protected static ?string $title = 'Recenze';

    protected static ?int $navigationSort = 13;

    protected static function group(): string
    {
        return 'Recenze';
    }
}
