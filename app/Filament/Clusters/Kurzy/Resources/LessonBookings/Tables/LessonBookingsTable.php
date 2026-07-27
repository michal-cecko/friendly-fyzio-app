<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonBookings\Tables;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoicesBulkAction;
use App\Filament\Support\Actions\CancelSignupAction;
use App\Filament\Support\Actions\CancelSignupBulkAction;
use App\Filament\Support\Actions\MarkSignupsPaidBulkAction;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\RevertSignupAction;
use App\Filament\Support\Actions\SendReviewRequestAction;
use App\Filament\Support\Tables\TimestampColumns;
use App\Support\Enrollments\SignupStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LessonBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lesson.name')
                    ->label('Akce')
                    ->searchable(),
                TextColumn::make('lesson.category.name')
                    ->label('Kategorie')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('lesson.lesson_date')
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
                RevertSignupAction::make(),
                CancelSignupAction::make(),
                SendReviewRequestAction::make(),
                // Zrušit already hard-deletes active sign-ups via its toggle, so a
                // plain delete is only offered to purge already-cancelled rows.
                DeleteAction::make()
                    ->visible(fn (Model $record): bool => ! SignupStatus::isActiveSignup($record)),
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
