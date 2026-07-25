<?php

namespace App\Filament\Clusters\Obsah\Resources\EmailTemplates\Pages;

use App\Filament\Clusters\Obsah\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\EmailTemplate;
use App\Support\EmailTemplateRenderer;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class EditEmailTemplates extends BaseEditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    public function getTitle(): string
    {
        /** @var EmailTemplate $record */
        $record = $this->getRecord();

        return 'Upravit e-mail '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Náhled')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->modalHeading('Náhled e-mailu')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Zavřít')
                ->modalWidth(Width::FiveExtraLarge)
                ->modalContent(fn (): View => view('filament.email-template-preview', [
                    'html' => EmailTemplateRenderer::render(
                        $this->getRecord(),
                        $this->getRecord()->templateKey()?->sampleContext() ?? [],
                    ),
                ])),
            ActivityLogAction::make(),
        ];
    }
}
