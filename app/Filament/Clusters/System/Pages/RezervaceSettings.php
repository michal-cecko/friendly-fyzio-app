<?php

namespace App\Filament\Clusters\System\Pages;

use App\Filament\Clusters\System\Pages\Concerns\SettingsGroupPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class RezervaceSettings extends SettingsGroupPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Rezervace';

    protected static ?string $title = 'Rezervace';

    protected static ?int $navigationSort = 14;

    protected static function group(): string
    {
        return 'Rezervace';
    }
}
