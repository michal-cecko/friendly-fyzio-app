<?php

namespace App\Mason\Bricks\Email;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A generic label/value detail box (reservation summary, original vs. new termín,
 * cancelled visit, …). The `variant` picks the box + title colours; each row is a
 * free label + value, and the value may contain {{ tokens }} that the renderer
 * substitutes with reservation data.
 */
class EmailReservationDetailsBrick extends Brick
{
    public static function getId(): string
    {
        return 'email-reservation-details';
    }

    public static function getLabel(): string
    {
        return 'Detailní box';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedClipboardDocumentList;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('emails.bricks.reservation-details', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                Select::make('variant')
                    ->label('Styl boxu')
                    ->options([
                        'default' => 'Základní (růžový)',
                        'muted' => 'Tlumený (šedý)',
                        'success' => 'Zvýrazněný (zelený)',
                        'danger' => 'Zrušeno (červený nadpis)',
                    ])
                    ->default('default')
                    ->required(),
                TextInput::make('title')
                    ->label('Nadpis boxu'),
                Repeater::make('rows')
                    ->label('Řádky')
                    ->schema([
                        TextInput::make('label')
                            ->label('Popisek')
                            ->required(),
                        TextInput::make('value')
                            ->label('Hodnota')
                            ->helperText('Může obsahovat proměnné, např. {{ sluzba }}.')
                            ->required(),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->reorderable()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
            ]);
    }
}
