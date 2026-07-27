<?php

namespace App\Filament\Support\Actions;

use App\Filament\Support\Concerns\PromptsScheduleChangeNotification;
use App\Filament\Widgets\ReservationCalendar;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The follow-up modal shown after a session's or reservation's termín is changed:
 * asks whether to e-mail the affected people about the change and lets staff attach
 * an optional message. Mounted after the save (page: {@see PromptsScheduleChangeNotification};
 * calendar: {@see ReservationCalendar}) rather than sitting in
 * the form, so nothing is sent until staff explicitly confirm.
 *
 * The record and the pre-edit snapshot are passed as the mount arguments, so both
 * survive the extra Livewire round-trip the modal adds.
 */
class ScheduleChangeNotificationPrompt
{
    /**
     * @param  Closure(?string $reason, array<string, mixed> $arguments): int  $using  Sends the
     *                                                                                 e-mails and returns how many recipients were notified.
     */
    public static function make(string $name, string $audience, Closure $using): Action
    {
        return Action::make($name)
            ->modalHeading('Upozornit na změnu termínu?')
            ->modalDescription("Termín se změnil. Chcete o tom informovat {$audience} e-mailem? Zprávu můžete doplnit níže.")
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->modalIconColor('warning')
            ->schema([
                Textarea::make('reason')
                    ->label('Zpráva (nepovinné)')
                    ->helperText('Připojí se do e-mailu o změně termínu.')
                    ->rows(3),
            ])
            ->modalSubmitActionLabel('Odeslat upozornění')
            ->modalCancelActionLabel('Neposílat')
            ->action(function (array $arguments, array $data) use ($using): void {
                $reason = is_string($data['reason'] ?? null) && trim($data['reason']) !== ''
                    ? trim($data['reason'])
                    : null;

                $count = $using($reason, $arguments);

                Notification::make()
                    ->success()
                    ->title($count > 0
                        ? 'Upozornění na změnu termínu odesláno.'
                        : 'Nikdo nebyl upozorněn – chybí e-mailová adresa.')
                    ->send();
            });
    }
}
