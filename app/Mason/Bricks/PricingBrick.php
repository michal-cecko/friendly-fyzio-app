<?php

namespace App\Mason\Bricks;

use App\Mason\Support\ButtonsField;
use App\Mason\Support\Fields;
use App\Models\Service;
use App\Models\ServiceCategory;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class PricingBrick extends Brick
{
    public static function getId(): string
    {
        return 'pricing';
    }

    public static function getLabel(): string
    {
        return 'Ceník';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedBanknotes;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.pricing', [
            'config' => $config,
            'rows' => self::resolveRows($config),
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Select::make('category_id')
                    ->label('Načíst služby z kategorie')
                    ->helperText('Pokud vyberete kategorii, ceník se sestaví automaticky z jejích veřejných služeb.')
                    ->options(fn (): array => ServiceCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->live(),
                Repeater::make('rows')
                    ->label('Položky ceníku')
                    ->visible(fn (Get $get): bool => blank($get('category_id')))
                    ->schema([
                        TextInput::make('name')
                            ->label('Název')
                            ->required(),
                        TextInput::make('note')
                            ->label('Délka / poznámka'),
                        TextInput::make('price')
                            ->label('Cena'),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Položka'),
                ButtonsField::make(),
            ]);
    }

    /**
     * Resolve the rows to render: from a category's public services when a
     * category is selected, otherwise from the manually entered rows.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, array{name: string, note: string, price: string}>
     */
    protected static function resolveRows(array $config): array
    {
        $categoryId = $config['category_id'] ?? null;

        if (! empty($categoryId)) {
            $category = ServiceCategory::find($categoryId);

            if ($category === null) {
                return [];
            }

            return $category->services()
                ->public()
                ->orderBy('name')
                ->get()
                ->map(fn (Service $service): array => [
                    'name' => $service->name,
                    'note' => $service->duration_minutes.' min',
                    'price' => number_format($service->price, 0, ',', ' ').' Kč',
                ])
                ->all();
        }

        return collect($config['rows'] ?? [])
            ->map(fn (array $row): array => [
                'name' => $row['name'] ?? '',
                'note' => $row['note'] ?? '',
                'price' => $row['price'] ?? '',
            ])
            ->all();
    }
}
