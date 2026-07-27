<?php

namespace App\Filament\Support\Actions;

use App\Enums\LessonExcuseReason;
use App\Models\LessonAttendance;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Amends the note staff left on an absence — a reason they only learned
 * afterwards, a typo, a detail worth keeping.
 *
 * Deliberately cannot touch the presence itself: putting a client back into a
 * lesson has to withdraw the poukaz it bought them and re-check the spot they
 * gave up, which is {@see ToggleLessonAttendanceAction}'s job.
 */
class EditExcuseAction
{
    public static function make(): Action
    {
        return Action::make('editExcuse')
            ->label('Upravit omluvu')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (LessonAttendance $record): bool => $record->isExcused())
            ->modalHeading('Upravit omluvu')
            ->modalDescription('Mění jen důvod a poznámku. Vrátit klienta do lekce lze přepínačem ve sloupci účasti.')
            ->modalSubmitActionLabel('Uložit')
            ->fillForm(fn (LessonAttendance $record): array => [
                'excuse_reason' => $record->excuse_reason,
                'excuse_note' => $record->excuse_note,
            ])
            ->schema([
                Select::make('excuse_reason')
                    ->label('Důvod')
                    ->options(LessonExcuseReason::class)
                    ->native(false)
                    ->placeholder('Neuvedeno'),
                Textarea::make('excuse_note')
                    ->label('Poznámka')
                    ->rows(2)
                    ->maxLength(1000)
                    ->helperText('Nepovinné, uvidí jen personál.'),
            ])
            ->action(function (LessonAttendance $record, array $data): void {
                $note = trim((string) ($data['excuse_note'] ?? ''));

                $record->update([
                    'excuse_reason' => $data['excuse_reason'] ?? null,
                    'excuse_note' => $note === '' ? null : $note,
                ]);

                Notification::make()
                    ->title('Omluva byla upravena.')
                    ->success()
                    ->send();
            });
    }
}
