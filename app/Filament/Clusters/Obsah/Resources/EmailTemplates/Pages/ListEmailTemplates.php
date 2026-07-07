<?php

namespace App\Filament\Clusters\Obsah\Resources\EmailTemplates\Pages;

use App\Filament\Clusters\Obsah\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    protected static string $resource = EmailTemplateResource::class;
}
