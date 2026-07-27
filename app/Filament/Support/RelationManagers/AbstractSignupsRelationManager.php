<?php

namespace App\Filament\Support\RelationManagers;

use App\Contracts\Emailable;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceFromPayableAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoicesBulkAction;
use App\Filament\Support\Actions\AddParticipantAction;
use App\Filament\Support\Actions\CancelSignupAction;
use App\Filament\Support\Actions\CancelSignupBulkAction;
use App\Filament\Support\Actions\MarkSignupsPaidBulkAction;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\RevertSignupAction;
use App\Filament\Support\Actions\SendBulkParticipantEmailAction;
use App\Filament\Support\Actions\SendEmailAction;
use App\Filament\Support\Tables\TimestampColumns;
use App\Jobs\SendBulkParticipantEmailJob;
use App\Mason\Support\EmailFields;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Shared "who is signed up" tab for the three enrollable offers, rendered on the
 * offer's view page next to the waitlist. Lists the offer's registrations /
 * enrollments / bookings and carries the full management toolkit: record
 * payment, cancel/storno, issue invoice (row + bulk) and manually add a
 * participant. Subclasses only vary in the relationship name, status enum, a
 * couple of extra columns/actions, and the deep-link to the standalone resource.
 */
abstract class AbstractSignupsRelationManager extends RelationManager
{
    /**
     * The status enum class backing the "Stav" filter (BookingStatus or
     * CourseEnrollmentStatus).
     *
     * @return class-string
     */
    abstract protected function statusOptions(): string;

    /**
     * Deep-link to the record's standalone resource view page.
     */
    abstract protected function detailUrl(Model $record): string;

    /**
     * @return array<int, TextColumn>
     */
    protected function extraColumns(): array
    {
        return [];
    }

    /**
     * @return array<int, Action>
     */
    protected function extraRecordActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // Clicking a row opens the sign-up's own detail page, same as the
            // série rows on a course; the row actions stay for everything else.
            ->recordUrl(fn (Model $record): string => $this->detailUrl($record))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label('Platba')
                    ->badge(),
                ...$this->extraColumns(),
                TextColumn::make('paid_at')
                    ->label('Zaplaceno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options($this->statusOptions()),
                SelectFilter::make('payment_status')
                    ->label('Platba')
                    ->options(PaymentStatus::class),
            ])
            ->headerActions([
                AddParticipantAction::make(),
            ])
            ->recordActions([
                RecordPaymentAction::make(),
                GenerateInvoiceFromPayableAction::make(),
                SendEmailAction::make(),
                RevertSignupAction::make(),
                CancelSignupAction::make(),
                ...$this->extraRecordActions(),
                Action::make('detail')
                    ->label('Detail')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Model $record): string => $this->detailUrl($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    MarkSignupsPaidBulkAction::make(),
                    GenerateInvoicesBulkAction::make(),
                    $this->sendEmailBulkAction(),
                    CancelSignupBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Table-selection "Odeslat e-mail" for the checked participants — the
     * checkbox-driven counterpart of the header {@see SendBulkParticipantEmailAction}
     * (which mails everyone). Reuses the queued participant fan-out job.
     */
    protected function sendEmailBulkAction(): BulkAction
    {
        return BulkAction::make('sendParticipantEmail')
            ->label('Odeslat e-mail')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->modalHeading('Odeslat e-mail vybraným')
            ->modalSubmitActionLabel('Odeslat')
            ->schema([
                ToggleButtons::make('mode')
                    ->label('Režim')
                    ->options(['custom' => 'Vlastní e-mail', 'template' => 'Šablona'])
                    ->default('custom')
                    ->inline()
                    ->live()
                    ->required(),
                Select::make('template_key')
                    ->label('Šablona e-mailu')
                    ->required()
                    ->options(SendBulkParticipantEmailAction::broadcastTemplateOptions())
                    ->helperText('Nabízené jsou jen šablony, které dávají smysl hromadně.')
                    ->visible(fn (Get $get): bool => $get('mode') === 'template'),
                TextInput::make('subject')
                    ->label('Předmět')
                    ->required()
                    ->visible(fn (Get $get): bool => $get('mode') === 'custom'),
                EmailFields::richText('body', 'Text e-mailu', required: true)
                    ->visible(fn (Get $get): bool => $get('mode') === 'custom'),
            ])
            ->action(function (Collection $records, array $data): void {
                $recipients = $records->filter(
                    fn (Model $record): bool => $record instanceof Emailable && filled($record->emailRecipientAddress())
                );

                if ($recipients->isEmpty()) {
                    Notification::make()
                        ->warning()
                        ->title('Nebyl vybrán žádný platný příjemce.')
                        ->send();

                    return;
                }

                $isTemplate = ($data['mode'] ?? 'custom') === 'template';

                SendBulkParticipantEmailJob::dispatch(
                    signupClass: $recipients->first()::class,
                    signupIds: $recipients->pluck('id')->all(),
                    templateKey: $isTemplate ? $data['template_key'] : null,
                    subject: $isTemplate ? null : $data['subject'],
                    bodyHtml: $isTemplate ? null : $data['body'],
                    senderId: auth()->id(),
                );

                Notification::make()
                    ->success()
                    ->title('Odesílání spuštěno')
                    ->body('Zpráva míří na '.$recipients->count().' příjemců. Až budou odeslány, dáme vám vědět.')
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
