<?php

use App\Models\Reservation;
use App\Models\SuggestionDismissal;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks are switched off until launch (SCHEDULED_TASKS_ENABLED).
|
| The database already carries real imported client records, so leaving the
| schedule running would let jobs act on — and e-mail about — live customer
| data before the clinic goes live. Flip SCHEDULED_TASKS_ENABLED=true in .env
| to bring it all back; nothing below has been removed.
|
| One job has a real deadline: `work-blocks:extend` keeps the therapists'
| work-block horizon rolling 26 weeks ahead, so it must be running again well
| before that runway is used up, or the calendar will run out of future slots.
*/
if (! config('app.scheduled_tasks_enabled')) {
    return;
}

Schedule::command('users:prune-unverified')->daily();
// Purge reservations that have sat in the trash for 30 days (their payments are
// kept, only unlinked — see Reservation::booted()).
Schedule::command('model:prune', ['--model' => [Reservation::class]])->daily();
Schedule::command('instagram:sync')->everyTwoHours();
Schedule::command('reviews:send-requests')->daily();
Schedule::command('reservations:send-confirmations')->hourly();
Schedule::command('reservations:send-reminders')->hourly();
Schedule::command('reservations:cancel-unconfirmed')->hourly();
Schedule::command('enrollments:cancel-unpaid')->hourly();
Schedule::command('lessons:release-free-spots')->hourly();
Schedule::command('credits:notify-expiring')->dailyAt('05:45');
Schedule::command('credits:expire')->dailyAt('05:50');
Schedule::command('payments:mark-overdue')->dailyAt('06:00');
Schedule::command('reservations:settle-past')->dailyAt('06:05');
Schedule::command('invoices:mark-overdue')->dailyAt('06:10');
Schedule::command('invoices:prune-exports')->dailyAt('06:20');
// Drop Návrhy dismissals whose snooze has run out — they hide nothing from that
// point on, and keeping them would make the "Skryté návrhy" list lie.
Schedule::call(fn () => SuggestionDismissal::prune())->dailyAt('06:25')->name('suggestions:prune-dismissals');
Schedule::command('work-blocks:extend')->dailyAt('05:30');
