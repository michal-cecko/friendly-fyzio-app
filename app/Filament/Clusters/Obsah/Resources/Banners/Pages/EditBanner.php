<?php

namespace App\Filament\Clusters\Obsah\Resources\Banners\Pages;

use App\Filament\Clusters\Obsah\Resources\Banners\BannerResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Banner;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Icons\Heroicon;

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
            // The catch-all dropdown always sits last in the header.
            ActionGroup::make([
                ActivityLogAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
                ->label('Další akce')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }
}
