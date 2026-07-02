<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('users:prune-unverified')->daily();
Schedule::command('instagram:sync')->everyTwoHours();
