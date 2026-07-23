<?php

namespace App\Mason\Bricks;

use App\Mason\Support\LinkPickerField;
use App\Support\Reservations\LastMinuteAvailability;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class LastMinuteBrick extends Brick
{
    public static function getId(): string
    {
        return 'last-minute';
    }

    public static function getLabel(): string
    {
        return 'Last-minute termíny';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedClock;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        $openings = LastMinuteAvailability::cached();

        // Without any openings the section would only say "nothing available",
        // so it is hidden from the public site entirely. The editor preview
        // keeps rendering the empty state so admins can still see the brick.
        if ($openings === [] && ! request()->routeIs('mason.preview', 'mason.entry')) {
            return '';
        }

        return view('bricks.last-minute', [
            'config' => $config,
            'openings' => $openings,
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        // Therapists, their free slots and their services are pulled live from the
        // booking system (see LastMinuteAvailability) — only the framing text and
        // the button are authored here.
        return $action
            ->slideOver()
            ->schema([
                TextInput::make('eyebrow')
                    ->label('Nadtitulek')
                    ->default('Volné dnes a zítra'),
                TextInput::make('title')
                    ->label('Nadpis')
                    ->default('Last-minute termíny'),
                TextInput::make('empty_text')
                    ->label('Text když nejsou volné termíny')
                    ->default('Momentálně nejsou volné žádné last-minute termíny. Zkuste to prosím později.'),
                LinkPickerField::make('', 'Tlačítko', withText: true, withStyle: true, withColor: true, withIcon: true),
            ]);
    }
}
