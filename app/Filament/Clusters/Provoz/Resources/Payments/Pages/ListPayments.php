<?php

namespace App\Filament\Clusters\Provoz\Resources\Payments\Pages;

use App\Filament\Clusters\Provoz\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
