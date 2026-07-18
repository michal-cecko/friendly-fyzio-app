<?php

namespace App\Filament\Support\RelationManagers;

use App\Enums\PaymentStatus;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceFromPayableAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoicesBulkAction;
use App\Filament\Support\Actions\AddParticipantAction;
use App\Filament\Support\Actions\CancelSignupAction;
use App\Filament\Support\Actions\CancelSignupBulkAction;
use App\Filament\Support\Actions\MarkSignupsPaidBulkAction;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
                    CancelSignupBulkAction::make(),
                ]),
            ]);
    }
}
