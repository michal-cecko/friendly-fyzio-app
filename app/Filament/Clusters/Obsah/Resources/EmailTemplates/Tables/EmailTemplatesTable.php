<?php

namespace App\Filament\Clusters\Obsah\Resources\EmailTemplates\Tables;

use App\Models\EmailTemplate;
use App\Support\EmailTemplateRenderer;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Předmět')
                    ->wrap()
                    ->color('gray'),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Náhled')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading('Náhled e-mailu')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalContent(fn (EmailTemplate $record): View => view('filament.email-template-preview', [
                        'html' => EmailTemplateRenderer::render(
                            $record,
                            $record->templateKey()?->sampleContext() ?? [],
                        ),
                    ])),
                EditAction::make(),
            ]);
    }
}
