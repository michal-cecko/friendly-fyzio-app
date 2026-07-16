<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Schemas;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\User;
use App\Support\Invoices\ClientSnapshot;
use App\Support\Invoices\DocumentNumberAllocator;
use App\Support\Pdf\InvoicePdfData;
use App\Support\Settings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Doklad')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 3])
                    ->schema([
                        Select::make('series_id')
                            ->label('Číselná řada')
                            ->options(fn () => InvoiceSeries::query()
                                ->where('document_type', DocumentType::Invoice->value)
                                ->pluck('name', 'id'))
                            ->default(fn (): ?string => app(DocumentNumberAllocator::class)
                                ->defaultSeries(DocumentType::Invoice)?->getKey())
                            ->required()
                            ->native(false)
                            ->helperText('Číslo bude přiděleno při uložení.')
                            ->visibleOn('create'),
                        Placeholder::make('invoice_number_display')
                            ->label('Číslo faktury')
                            ->content(fn (?Invoice $record): string => $record?->invoice_number ?? '—')
                            ->visibleOn('edit'),
                        Select::make('client_id')
                            ->label('Klient')
                            ->relationship(
                                'client',
                                'name',
                                fn (Builder $query): Builder => $query->where('role', UserRole::Customer),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $client = $state !== null ? User::query()->find($state) : null;

                                if ($client === null) {
                                    return;
                                }

                                foreach (ClientSnapshot::for($client) as $key => $value) {
                                    $set('client_snapshot.'.$key, $value);
                                }
                            }),
                        Select::make('payment_method')
                            ->label('Způsob platby')
                            ->options(PaymentMethod::class)
                            ->default(PaymentMethod::Qr)
                            ->required()
                            ->native(false),
                        DatePicker::make('issued_at')
                            ->label('Datum vystavení')
                            ->native(false)
                            ->default(today())
                            ->required(),
                        DatePicker::make('due_at')
                            ->label('Splatnost')
                            ->native(false)
                            ->default(fn (): Carbon => today()->addDays(Settings::invoiceDueDays()))
                            ->required(),
                        TextInput::make('variable_symbol')
                            ->label('Variabilní symbol')
                            ->maxLength(10)
                            ->helperText('Prázdný = přidělí se symbol platby.'),
                        Select::make('status')
                            ->label('Stav')
                            ->options(InvoiceStatus::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->visibleOn('edit'),
                        DateTimePicker::make('paid_at')
                            ->label('Uhrazeno')
                            ->native(false)
                            ->visible(fn (Get $get): bool => $get('status') === InvoiceStatus::Paid->value
                                || $get('status') === InvoiceStatus::Paid)
                            ->visibleOn('edit'),
                    ]),
                Section::make('Odběratel (fakturační údaje)')
                    ->description('Předvyplní se z profilu klienta; faktura si uchová vlastní kopii.')
                    ->icon(Heroicon::OutlinedUser)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 3])
                    ->schema([
                        TextInput::make('client_snapshot.name')
                            ->label('Jméno / název')
                            ->required(),
                        TextInput::make('client_snapshot.address')
                            ->label('Adresa'),
                        TextInput::make('client_snapshot.ico')
                            ->label('IČO'),
                        TextInput::make('client_snapshot.dic')
                            ->label('DIČ'),
                        TextInput::make('client_snapshot.email')
                            ->label('E-mail')
                            ->email(),
                        TextInput::make('client_snapshot.phone')
                            ->label('Telefon'),
                    ]),
                Section::make('Položky')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->orderColumn('sort')
                            ->reorderable()
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Přidat položku')
                            ->columns(['default' => 1, 'lg' => 12])
                            ->schema([
                                TextInput::make('title')
                                    ->label('Název')
                                    ->required()
                                    ->columnSpan(['default' => 1, 'lg' => 4]),
                                TextInput::make('description')
                                    ->label('Popis')
                                    ->columnSpan(['default' => 1, 'lg' => 3]),
                                TextInput::make('quantity')
                                    ->label('Počet')
                                    ->integer()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                                TextInput::make('unit_price')
                                    ->label('Cena/ks')
                                    ->integer()
                                    ->required()
                                    ->suffix('Kč')
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                Select::make('vat_rate')
                                    ->label('DPH')
                                    ->options([21 => '21 %', 12 => '12 %', 0 => '0 %'])
                                    ->default(fn (): ?int => Settings::vatPayer() ? Settings::defaultVatRate() : null)
                                    ->visible(fn (): bool => Settings::vatPayer())
                                    ->native(false)
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                                Placeholder::make('row_total')
                                    ->label('Celkem')
                                    ->content(fn (Get $get): string => InvoicePdfData::money(
                                        (int) $get('quantity') * (int) $get('unit_price'),
                                    ))
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                            ]),
                        Placeholder::make('grand_total')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $total = collect($get('items') ?? [])->sum(
                                    fn (array $item): int => (int) ($item['quantity'] ?? 0) * (int) ($item['unit_price'] ?? 0),
                                );

                                return new HtmlString(
                                    '<div style="text-align: right;"><span style="font-weight: 600;">Celkem k úhradě: </span>'
                                    .'<span style="font-weight: 700;">'.e(InvoicePdfData::money($total)).'</span></div>',
                                );
                            }),
                    ]),
                Section::make('Texty na faktuře')
                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->collapsible()
                    ->schema([
                        Textarea::make('text_before_items')
                            ->label('Text před položkami')
                            ->rows(2)
                            ->default(fn (): string => Settings::invoiceTextBeforeItems()),
                        Textarea::make('text_after_items')
                            ->label('Text za položkami')
                            ->rows(2)
                            ->default(fn (): string => Settings::invoiceTextAfterItems()),
                        TextInput::make('footer_note')
                            ->label('Poděkování v patičce')
                            ->default(fn (): string => Settings::invoiceFooterThankYou()),
                        TextInput::make('vat_note')
                            ->label('Poznámka k DPH')
                            ->default(fn (): ?string => Settings::vatPayer() ? null : Settings::vatNote()),
                    ]),
                Section::make('Dodavatel (zmrazeno při vystavení)')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->columnSpanFull()
                    ->collapsed()
                    ->visibleOn('edit')
                    ->schema([
                        Placeholder::make('supplier_display')
                            ->label('')
                            ->content(function (?Invoice $record): string {
                                $supplier = $record?->supplier_snapshot ?? [];

                                return implode(' · ', array_filter([
                                    $supplier['name'] ?? null,
                                    $supplier['address'] ?? null,
                                    filled($supplier['ico'] ?? null) ? 'IČO: '.$supplier['ico'] : null,
                                    filled($supplier['dic'] ?? null) ? 'DIČ: '.$supplier['dic'] : null,
                                    $supplier['iban'] ?? null,
                                ]));
                            }),
                    ]),
            ]);
    }
}
