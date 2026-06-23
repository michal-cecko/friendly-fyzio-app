<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\Pages;

use App\Enums\UserRole;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = UserRole::Customer;

        // Password is managed via the "Reset hesla" action, not the form, so a
        // new account starts with a random password the admin can later set.
        $data['password'] = Str::password(16);

        return $data;
    }
}
