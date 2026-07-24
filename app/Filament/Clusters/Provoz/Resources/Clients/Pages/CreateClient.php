<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\Pages;

use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Models\User;
use Illuminate\Support\Str;

class CreateClient extends BaseCreateRecord
{
    protected static string $resource = ClientResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Password is managed via the "Reset hesla" action, not the form, so a
        // new account starts with a random password the admin can later set.
        $data['password'] = Str::password(16);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $record */
        $record = $this->record;
        $record->markAsCustomer();
    }
}
