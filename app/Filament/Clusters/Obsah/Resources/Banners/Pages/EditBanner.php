<?php

namespace App\Filament\Clusters\Obsah\Resources\Banners\Pages;

use App\Filament\Clusters\Obsah\Resources\Banners\BannerResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Banner;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;

class EditBanner extends BaseEditRecord
{
    protected static string $resource = BannerResource::class;

    public function getTitle(): string
    {
        /** @var Banner $record */
        $record = $this->getRecord();

        return 'Upravit banner '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
