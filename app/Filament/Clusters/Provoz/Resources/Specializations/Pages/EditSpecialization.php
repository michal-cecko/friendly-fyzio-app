<?php

namespace App\Filament\Clusters\Provoz\Resources\Specializations\Pages;

use App\Filament\Clusters\Provoz\Resources\Specializations\SpecializationResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Models\Specialization;
use Filament\Actions\DeleteAction;

class EditSpecialization extends BaseEditRecord
{
    protected static string $resource = SpecializationResource::class;

    public function getTitle(): string
    {
        /** @var Specialization $record */
        $record = $this->getRecord();

        return 'Upravit specializaci '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
