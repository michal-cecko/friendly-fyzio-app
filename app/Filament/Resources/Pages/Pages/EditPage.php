<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Page $record): bool => ! $record->is_system),
        ];
    }
}
