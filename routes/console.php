<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('users:prune-unverified')->daily();
Schedule::command('instagram:sync')->everyTwoHours();
Schedule::command('reviews:send-requests')->daily();
