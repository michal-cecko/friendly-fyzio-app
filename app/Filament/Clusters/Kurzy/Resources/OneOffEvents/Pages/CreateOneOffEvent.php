<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages;

use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\OneOffEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOneOffEvent extends CreateRecord
{
    protected static string $resource = OneOffEventResource::class;
}
