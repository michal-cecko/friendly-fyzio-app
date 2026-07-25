<?php

namespace App\Filament\Clusters\Obsah\Resources\Reviews\Pages;

use App\Filament\Clusters\Obsah\Resources\Reviews\ReviewResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Review;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\Width;

class EditReview extends BaseEditRecord
{
    protected static string $resource = ReviewResource::class;

    public function getTitle(): string
    {
        /** @var Review $record */
        $record = $this->getRecord();

        return 'Upravit recenzi '.$record->author_name;
    }

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
