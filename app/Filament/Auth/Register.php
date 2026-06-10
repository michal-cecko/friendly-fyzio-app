<?php

namespace App\Filament\Auth;

use App\Enums\UserRole;
use App\Forms\Components\TurnstileField;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Public, self-service registration for the client zone. Every account created
 * here is a customer; staff accounts are provisioned from the admin panel only.
 */
class Register extends BaseRegister
{
    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Vytvořit účet');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    $this->getFirstNameFormComponent(),
                    $this->getLastNameFormComponent(),
                ]),
                $this->getEmailFormComponent(),
                $this->getPhoneFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getTurnstileFormComponent(),
            ]);
    }

    protected function getFirstNameFormComponent(): Component
    {
        return TextInput::make('first_name')
            ->label(__('Jméno'))
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    protected function getLastNameFormComponent(): Component
    {
        return TextInput::make('last_name')
            ->label(__('Příjmení'))
            ->required()
            ->maxLength(255);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('Telefon'))
            ->tel()
            ->required()
            ->maxLength(255);
    }

    protected function getTurnstileFormComponent(): Component
    {
        return TurnstileField::make('turnstile_token');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeRegister(#[SensitiveParameter] array $data): array
    {
        $data['name'] = trim("{$data['first_name']} {$data['last_name']}");
        $data['role'] = UserRole::Customer->value;

        unset($data['first_name'], $data['last_name'], $data['turnstile_token']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $user = $this->getUserModel()::create($data);

        // Give the new customer the client profile that backs their "klientská zóna".
        $user->clientProfile()->create();

        return $user;
    }
}
