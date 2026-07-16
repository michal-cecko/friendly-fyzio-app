<?php

namespace App\Filament\Clusters\Finance\Resources\Payments\Pages;

use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
