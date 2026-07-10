<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('users:prune-unverified')->daily();
Schedule::command('instagram:sync')->everyTwoHours();
Schedule::command('reviews:send-requests')->daily();
Schedule::command('reservations:send-confirmations')->hourly();
Schedule::command('reservations:send-reminders')->hourly();
Schedule::command('reservations:cancel-unconfirmed')->hourly();
