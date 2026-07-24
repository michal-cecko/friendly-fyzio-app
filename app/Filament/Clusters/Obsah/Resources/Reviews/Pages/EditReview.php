<?php

namespace App\Filament\Clusters\Obsah\Resources\Reviews\Pages;

use App\Filament\Clusters\Obsah\Resources\Reviews\ReviewResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\Width;

class EditReview extends BaseEditRecord
{
    protected static string $resource = ReviewResource::class;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
