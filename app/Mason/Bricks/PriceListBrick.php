<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Models\Service;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A full price list grouped into tabbed categories (Fyzioterapie, Masáže, …).
 * Each row is either linked to an existing Service — in which case its name,
 * duration and price are pulled live from the record — or entered by hand, which
 * covers services that are not reservable and free-form fees (storno, cestovné).
 */
class PriceListBrick extends Brick
{
    public static function getId(): string
    {
        return 'price-list';
    }

    public static function getLabel(): string
    {
        return 'Ceník (více kategorií)';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedTableCells;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.price-list', [
            'config' => $config,
            'categories' => self::resolveCategories($config),
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Repeater::make('categories')
                    ->label('Kategorie (záložky)')
                    ->schema([
                        TextInput::make('label')
                            ->label('Název záložky')
                            ->required(),
                        TextInput::make('heading')
                            ->label('Nadpis tabulky')
                            ->helperText('Nepovinné — pokud necháte prázdné, použije se název záložky.'),
                        Repeater::make('rows')
                            ->label('Položky')
                            ->schema([
                                Select::make('service_id')
                                    ->label('Propojit se službou')
                                    ->helperText('Vyberte službu a její název, délka i cena se načtou automaticky. Jinak vyplňte pole ručně.')
                                    ->options(fn (): array => self::serviceOptions())
                                    ->searchable()
                                    ->live(),
                                TextInput::make('name')
                                    ->label('Název')
                                    ->required(fn (Get $get): bool => blank($get('service_id')))
                                    ->visible(fn (Get $get): bool => blank($get('service_id'))),
                                TextInput::make('note')
                                    ->label('Délka / poznámka')
                                    ->visible(fn (Get $get): bool => blank($get('service_id'))),
                                TextInput::make('price')
                                    ->label('Cena')
                                    ->placeholder('např. 900 Kč, od 500 Kč, 50 % ceny')
                                    ->visible(fn (Get $get): bool => blank($get('service_id'))),
                            ])
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => self::rowLabel($state)),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Kategorie'),
                Fields::richText('note', 'Poznámka pod ceníkem'),
            ]);
    }

    /**
     * Resolve every category and its rows into flat render data.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, array{label: string, heading: string, rows: array<int, array{name: string, note: string, price: string}>}>
     */
    protected static function resolveCategories(array $config): array
    {
        return collect($config['categories'] ?? [])
            ->map(fn (array $category): array => [
                'label' => $category['label'] ?? '',
                'heading' => filled($category['heading'] ?? null) ? $category['heading'] : ($category['label'] ?? ''),
                'rows' => collect($category['rows'] ?? [])
                    ->map(fn (array $row): array => self::resolveRow($row))
                    ->all(),
            ])
            ->all();
    }

    /**
     * Resolve a single row: pull from the linked Service when set, otherwise use
     * the hand-entered values.
     *
     * @param  array<string, mixed>  $row
     * @return array{name: string, note: string, price: string}
     */
    protected static function resolveRow(array $row): array
    {
        if (! empty($row['service_id']) && ($service = Service::find($row['service_id'])) !== null) {
            return [
                'name' => $service->name,
                'note' => $service->duration_minutes.' min',
                'price' => number_format($service->price, 0, ',', ' ').' Kč',
            ];
        }

        return [
            'name' => $row['name'] ?? '',
            'note' => $row['note'] ?? '',
            'price' => $row['price'] ?? '',
        ];
    }

    /**
     * Services grouped by category name for the picker, e.g. "Fyzioterapie · Vstupní vyšetření".
     *
     * @return array<string, string>
     */
    protected static function serviceOptions(): array
    {
        return Service::query()
            ->with('category')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Service $service): array => [
                $service->id => filled($service->category?->name)
                    ? "{$service->category->name} · {$service->name}"
                    : $service->name,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected static function rowLabel(array $state): ?string
    {
        if (! empty($state['service_id']) && ($service = Service::find($state['service_id'])) !== null) {
            return $service->name;
        }

        return $state['name'] ?? 'Položka';
    }
}
