<?php

namespace App\Filament\Clusters\Obsah\Resources\EmailTemplates\Schemas;

use App\Mason\EmailBrickRegistry;
use App\Models\EmailTemplate;
use Awcodes\Mason\Mason;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nastavení')
                    ->schema([
                        TextInput::make('name')
                            ->label('Název e-mailu')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('subject')
                            ->label('Předmět')
                            ->required(),
                        Placeholder::make('tokens_help')
                            ->label('Dostupné proměnné')
                            ->content(fn (?EmailTemplate $record): HtmlString => self::tokensHint($record)),
                    ]),
                Section::make('Obsah')
                    ->schema([
                        Mason::make('content')
                            ->label('Obsah e-mailu')
                            ->bricks(EmailBrickRegistry::all())
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function tokensHint(?EmailTemplate $record): HtmlString
    {
        $key = $record?->templateKey();

        if ($key === null) {
            return new HtmlString('—');
        }

        $items = collect($key->tokens())
            ->map(fn (string $description, string $token): string => '<code>{{ '.$token.' }}</code> — '.e($description))
            ->implode('<br>');

        return new HtmlString($items);
    }
}
