<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Tables;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoicesBulkAction;
use App\Filament\Support\Actions\CancelSignupAction;
use App\Filament\Support\Actions\CancelSignupBulkAction;
use App\Filament\Support\Actions\MarkSignupsPaidBulkAction;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\SendReviewRequestAction;
use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OneOffEventBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.name')
                    ->label('Akce')
                    ->searchable(),
                TextColumn::make('event.category.name')
                    ->label('Kategorie')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('event.event_date')
                    ->label('Datum')
                    ->date('d.m.Y'),
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label('Platba')
                    ->badge(),
                TextColumn::make('paid_at')
                    ->label('Zaplaceno')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Platba')
                    ->options(PaymentStatus::class),
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(BookingStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                RecordPaymentAction::make(),
                CancelSignupAction::make(),
                SendReviewRequestAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    MarkSignupsPaidBulkAction::make(),
                    GenerateInvoicesBulkAction::make(),
                    CancelSignupBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
