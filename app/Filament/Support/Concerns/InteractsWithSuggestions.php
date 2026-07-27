<?php

namespace App\Filament\Support\Concerns;

use App\Models\SuggestionDismissal;
use App\Support\Suggestions\SuggestionFinder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * The three things you can do to a Návrhy card, shared by the dashboard widget
 * and the Návrhy page so both behave identically.
 *
 * Cards are plain arrays, not Livewire components, so each action is mounted
 * with the card's own arguments — `mountAction('resolveSuggestion', {...})` —
 * rather than being built per card.
 *
 * Every action ends with {@see SuggestionFinder::flush()}: the list is memoised
 * for the request, and without the flush the re-render would still show the card
 * that was just dealt with.
 */
trait InteractsWithSuggestions
{
    public function resolveSuggestionAction(): Action
    {
        return Action::make('resolveSuggestion')
            ->label(fn (array $arguments): string => $arguments['label'] ?? 'Vyřídit')
            ->icon(Heroicon::OutlinedBolt)
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => $arguments['label'] ?? 'Vyřídit návrh')
            ->modalDescription(fn (array $arguments): ?string => $arguments['confirm'] ?? null)
            ->modalSubmitActionLabel('Provést')
            ->action(function (array $arguments): void {
                try {
                    $message = SuggestionFinder::ruleFor($arguments['type'])->resolve($arguments['id'] ?? null);

                    Notification::make()->success()->title($message)->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Návrh se nepodařilo vyřídit')
                        ->body($exception->getMessage())
                        ->send();
                }

                SuggestionFinder::flush();
            });
    }

    public function dismissSuggestionAction(): Action
    {
        return Action::make('dismissSuggestion')
            ->label('Skrýt')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('gray')
            ->action(function (array $arguments): void {
                SuggestionDismissal::query()->updateOrCreate(
                    ['key' => $arguments['key']],
                    [
                        'type' => $arguments['type'],
                        'fingerprint' => $arguments['fingerprint'] ?? '',
                        // Aggregate cards go quiet for a week; per-record ones
                        // stay hidden until their own facts change.
                        'snoozed_until' => ($arguments['snooze'] ?? false) ? now()->addWeek() : null,
                        'dismissed_by' => auth()->id(),
                    ],
                );

                Notification::make()->success()->title('Návrh skryt')->send();

                SuggestionFinder::flush();
            });
    }

    public function restoreSuggestionAction(): Action
    {
        return Action::make('restoreSuggestion')
            ->label('Vrátit')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->action(function (array $arguments): void {
                SuggestionDismissal::query()->where('key', $arguments['key'])->delete();

                Notification::make()->success()->title('Návrh vrácen zpět')->send();

                SuggestionFinder::flush();
            });
    }
}
