<?php

namespace App\Filament\Clusters\Finance\Resources\InvoiceSeries\Schemas;

use App\Enums\DocumentType;
use App\Models\InvoiceSeries;
use App\Support\Invoices\DocumentNumberAllocator;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InvoiceSeriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Číselná řada')
                    ->icon(Heroicon::OutlinedHashtag)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 3])
                    ->schema([
                        TextInput::make('name')
                            ->label('Název')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('prefix')
                            ->label('Prefix')
                            ->required()
                            ->maxLength(10)
                            ->helperText('Prefixy řad by měly být unikátní, jinak hrozí kolize čísel dokladů.'),
                        Select::make('document_type')
                            ->label('Typ dokladu')
                            ->options(DocumentType::class)
                            ->default(DocumentType::Invoice)
                            ->required()
                            ->native(false),
                        TextInput::make('format')
                            ->label('Formát čísla')
                            ->required()
                            ->default('{PREFIX}-{YEAR}-{SEQ}')
                            ->helperText('Dostupné proměnné: {PREFIX}, {YEAR}, {SEQ}. Bez ročního resetu lze {YEAR} vynechat.'),
                        TextInput::make('padding')
                            ->label('Počet číslic pořadí')
                            ->integer()
                            ->required()
                            ->default(5)
                            ->minValue(1)
                            ->maxValue(8),
                        Toggle::make('reset_yearly')
                            ->label('Resetovat každý rok')
                            ->default(true)
                            ->inline(false),
                        Toggle::make('is_default')
                            ->label('Výchozí řada')
                            ->helperText('Pro každý typ dokladu může být výchozí jen jedna řada.')
                            ->inline(false),
                        Placeholder::make('current_number_display')
                            ->label('Aktuální pořadí')
                            ->content(fn (?InvoiceSeries $record): string => (string) ($record?->current_number ?? 0))
                            ->visibleOn('edit'),
                        Placeholder::make('next_number_display')
                            ->label('Další číslo')
                            ->content(fn (?InvoiceSeries $record): string => $record !== null
                                ? app(DocumentNumberAllocator::class)->preview($record)
                                : '—')
                            ->visibleOn('edit'),
                    ]),
            ]);
    }
}
