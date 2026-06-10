<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Calendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Kalendář';

    protected static ?string $title = 'Kalendář';

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.calendar';
}
