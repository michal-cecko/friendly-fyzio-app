<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\Pages;

use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Models\User;
use Illuminate\Support\Str;

class CreateUser extends BaseCreateRecord
{
    protected static string $resource = UserResource::class;

    /** @var list<string> */
    protected array $selectedCapabilities = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Capabilities are Spatie roles, not a column — stash and apply after save.
        $this->selectedCapabilities = $data['capabilities'] ?? [];
        unset($data['capabilities']);

        // Password is managed via the "Reset hesla" action, not the form, so a
        // new account starts with a random password the admin can later set.
        $data['password'] = Str::password(16);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $record */
        $record = $this->record;
        /** @var User $actor */
        $actor = auth()->user();
        $record->applyCapabilitySelection($this->selectedCapabilities, $actor);
    }
}
