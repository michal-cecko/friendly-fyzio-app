<?php

namespace App\Filament\Clusters\Provoz\Resources\ReservationDayWaitlist\Tables;

use App\Models\ReservationDayWaitlistEntry;
use App\Support\Reservations\NotifyReservationDayWaitlist;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReservationDayWaitlistTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['client', 'therapist.user', 'service']))
            ->defaultSort('reservation_date')
            ->columns([
                TextColumn::make('reservation_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('therapist')
                    ->label('Terapeut')
                    ->state(fn (ReservationDayWaitlistEntry $record): string => $record->therapistLabel()),
                TextColumn::make('service.name')
                    ->label('Služba (kontext)')
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label('Klient')
                    ->state(fn (ReservationDayWaitlistEntry $record): string => $record->displayName())
                    ->searchable(['name', 'email']),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->state(fn (ReservationDayWaitlistEntry $record): ?string => $record->displayEmail())
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Telefon')
                    ->state(fn (ReservationDayWaitlistEntry $record): ?string => $record->displayPhone())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('notified_at')
                    ->label('Upozorněn')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Čeká')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Zapsán')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('reservation_date')
                    ->schema([
                        DatePicker::make('value')->label('Datum'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('reservation_date', $date)))
                    ->indicateUsing(fn (array $data): array => ($data['value'] ?? null)
                        ? ['Datum: '.Carbon::parse($data['value'])->format('d.m.Y')]
                        : []),
                TernaryFilter::make('notified_at')
                    ->label('Upozorněn')
                    ->nullable()
                    ->placeholder('Vše')
                    ->trueLabel('Upozorněn')
                    ->falseLabel('Čeká'),
                SelectFilter::make('service')
                    ->label('Služba')
                    ->relationship('service', 'name')
                    ->preload(),
            ])
            ->recordActions([
                Action::make('notify')
                    ->label('Upozornit')
                    ->icon(Heroicon::OutlinedBell)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Upozorní všechny čekající v pořadníku pro tohoto terapeuta a den, že se uvolnilo místo.')
                    // Only a concrete-therapist entry can be notified by hand; an "any"
                    // entry has no single therapist to build the booking link from.
                    ->visible(fn (ReservationDayWaitlistEntry $record): bool => $record->therapist_id !== null && $record->notified_at === null)
                    ->action(function (ReservationDayWaitlistEntry $record): void {
                        app(NotifyReservationDayWaitlist::class)($record->therapist_id, $record->reservation_date->toDateString());

                        Notification::make()->success()->title('Pořadník byl upozorněn.')->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Zatím nikdo v pořadníku')
            ->emptyStateDescription('Klienti se zapisují z rezervačního průvodce u plně obsazených dnů.');
    }
}
