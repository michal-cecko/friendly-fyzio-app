<?php

namespace App\Support\WorkBlocks;

use App\Filament\Pages\Calendar;
use App\Models\TherapistWorkBlock;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * In-app notice to the office whenever a therapist changes their own working
 * hours in the calendar.
 *
 * Therapists keep their availability up to date themselves, but the schedule is
 * what the public booking wizard offers, so the admins have to hear about it.
 * Only the edits of staff scoped to their own work are announced — an admin
 * editing a schedule is the office and does not need to tell itself.
 * Deliberately in-app only: no e-mail, no queue.
 */
class NotifyWorkScheduleChange
{
    /**
     * Announce a change to the current user's working hours. Does nothing when
     * the author is an admin (or nobody at all).
     */
    public static function send(string $summary, ?string $detail = null): void
    {
        $author = auth()->user();

        if (! $author instanceof User || ! $author->isScopedToOwnWork()) {
            return;
        }

        $url = Calendar::getUrl([
            'mode' => 'template',
            'therapists' => array_values(array_filter([$author->staffProfile?->getKey()])),
        ]);

        User::query()->admins()->whereNull('deactivated_at')->get()
            ->each(fn (User $admin) => Notification::make()
                ->title($author->full_name.' — '.$summary)
                ->body($detail)
                ->icon('heroicon-o-calendar-days')
                ->info()
                ->actions([
                    Action::make('open')
                        ->label('Zobrazit pracovní dobu')
                        ->url($url),
                ])
                ->sendToDatabase($admin));
    }

    /**
     * "12. 8. 2026, 8:00–12:00 (Sál 1)" — the one-line description of a block
     * used as the notification body.
     */
    public static function describe(TherapistWorkBlock $block): string
    {
        $times = substr((string) $block->start_time, 0, 5).'–'.substr((string) $block->end_time, 0, 5);

        return $block->work_date->format('j. n. Y').', '.$times
            .($block->room?->display_short_name ? ' ('.$block->room->display_short_name.')' : '');
    }

    /**
     * "od 12. 8. 2026 do 19. 8. 2026" — the date range of a bulk removal.
     */
    public static function describeRange(string $from, string $until): string
    {
        return 'od '.Carbon::parse($from)->format('j. n. Y').' do '.Carbon::parse($until)->format('j. n. Y');
    }
}
