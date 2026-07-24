<?php

namespace App\Filament\Clusters\Obsah\Resources\Pages\Pages;

use App\Filament\Clusters\Obsah\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\OpenPublicPageAction;
use App\Models\Page;
use Filament\Actions\DeleteAction;

class EditPage extends BaseEditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OpenPublicPageAction::make(),
            DeleteAction::make()
                ->visible(fn (Page $record): bool => ! $record->is_system),
            ActivityLogAction::make(),
        ];
    }
}
