<?php

namespace App\Filament\Clusters\Obsah\Resources\Reviews\Pages;

use App\Filament\Clusters\Obsah\Resources\Reviews\ReviewResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use Filament\Support\Enums\Width;

class CreateReview extends BaseCreateRecord
{
    protected static string $resource = ReviewResource::class;

    protected static ?string $title = 'Nová recenze';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
