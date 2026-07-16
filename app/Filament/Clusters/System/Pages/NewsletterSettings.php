<?php

namespace App\Filament\Clusters\System\Pages;

use App\Filament\Clusters\System\Pages\Concerns\SettingsGroupPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class NewsletterSettings extends SettingsGroupPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Newsletter';

    protected static ?string $title = 'Newsletter';

    protected static ?int $navigationSort = 12;

    protected static function group(): string
    {
        return 'Newsletter';
    }
}
