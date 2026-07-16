<?php

namespace App\Filament\Clusters\Finance\Resources\CashReceipts\Pages;

use App\Filament\Clusters\Finance\Resources\CashReceipts\CashReceiptResource;
use Filament\Resources\Pages\ListRecords;

class ListCashReceipts extends ListRecords
{
    protected static string $resource = CashReceiptResource::class;
}
