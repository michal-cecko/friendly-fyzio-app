<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The Kontakt page section: a centered page heading, a Livewire contact form and
 * the clinic's contact details + map. Contact details (phone/e-mail/address/hours)
 * are read from the Settings store in the blade, so they stay in sync with the footer.
 */
class ContactBrick extends Brick
{
    public static function getId(): string
    {
        return 'contact';
    }

    public static function getLabel(): string
    {
        return 'Kontakt';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedEnvelope;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.contact', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                TextInput::make('form_title')
                    ->label('Nadpis formuláře')
                    ->default('Napište nám'),
                TextInput::make('form_button_text')
                    ->label('Text tlačítka')
                    ->default('Odeslat zprávu'),
                TextInput::make('map_embed_url')
                    ->label('URL mapy (embed)')
                    ->helperText('Ponechte prázdné pro automatické zobrazení podle adresy z nastavení.'),
            ]);
    }
}
