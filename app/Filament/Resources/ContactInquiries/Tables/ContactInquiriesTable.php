<?php

namespace App\Filament\Resources\ContactInquiries\Tables;

use App\Enums\ContactInquiryStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactInquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Jméno')
                    ->weight(fn ($record) => $record->status === ContactInquiryStatus::New ? 'bold' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->label('Telefon')
                    ->toggleable(),
                TextColumn::make('message')
                    ->label('Zpráva')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
                SelectColumn::make('status')
                    ->label('Stav')
                    ->options(ContactInquiryStatus::class)
                    ->selectablePlaceholder(false),
                TextColumn::make('created_at')
                    ->label('Přijato')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(ContactInquiryStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
