<?php

namespace App\Filament\Clusters\Finance\Resources\InvoiceSeries\Pages;

use App\Filament\Clusters\Finance\Resources\InvoiceSeries\InvoiceSeriesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoiceSeries extends CreateRecord
{
    protected static string $resource = InvoiceSeriesResource::class;
}
