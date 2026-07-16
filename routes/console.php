<?php

use App\Models\Reservation;
use Illuminate\Support\Facades\Schedule;

Schedule::command('users:prune-unverified')->daily();
// Purge reservations that have sat in the trash for 30 days (their payments are
// kept, only unlinked — see Reservation::booted()).
Schedule::command('model:prune', ['--model' => [Reservation::class]])->daily();
Schedule::command('instagram:sync')->everyTwoHours();
Schedule::command('reviews:send-requests')->daily();
Schedule::command('reservations:send-confirmations')->hourly();
Schedule::command('reservations:send-reminders')->hourly();
Schedule::command('reservations:cancel-unconfirmed')->hourly();
Schedule::command('payments:mark-overdue')->dailyAt('06:00');
Schedule::command('invoices:mark-overdue')->dailyAt('06:10');
Schedule::command('invoices:prune-exports')->dailyAt('06:20');
Schedule::command('work-blocks:extend')->dailyAt('05:30');
