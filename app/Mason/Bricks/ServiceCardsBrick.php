<?php

namespace App\Mason\Bricks;

use App\Enums\ServiceType;
use App\Mason\Support\ButtonsField;
use App\Mason\Support\Fields;
use App\Models\ServiceCategory;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Guava\IconPicker\Forms\Components\IconPicker;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Image cards mirroring "Karty s obrázkem", but auto-populated from the
 * published service categories instead of a manual repeater. Each card links to
 * the category's public page.
 */
class ServiceCardsBrick extends Brick
{
    public static function getId(): string
    {
        return 'service-cards';
    }

    public static function getLabel(): string
    {
        return 'Karty služeb';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedSquares2x2;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        $categories = ServiceCategory::query()
            ->published()
            ->when(
                ! empty($config['type']),
                fn (Builder $query) => $query->where('type', $config['type']),
            )
            ->orderBy('name')
            ->get();

        return view('bricks.service-cards', [
            'config' => $config,
            'categories' => $categories,
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Select::make('type')
                    ->label('Typ služby')
                    ->options(ServiceType::class)
                    ->placeholder('Všechny typy'),
                Select::make('background')
                    ->label('Pozadí')
                    ->options(['white' => 'Bílé', 'alt' => 'Světle růžové'])
                    ->default('white'),
                Select::make('columns')
                    ->label('Počet sloupců')
                    ->options([2 => '2', 3 => '3', 4 => '4'])
                    ->default(4),
                TextInput::make('link_text')
                    ->label('Text odkazu')
                    ->default('Zjistit více'),
                Select::make('link_style')
                    ->label('Styl odkazu')
                    ->options(ButtonsField::styles())
                    ->default('text'),
                ColorPicker::make('link_color')
                    ->label('Vlastní barva (nepovinné)'),
                IconPicker::make('link_icon')
                    ->label('Ikona (nepovinné)')
                    ->sets(['lucide'])
                    ->searchable(),
            ]);
    }
}
