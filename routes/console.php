<?php

use Illuminate\Support\Facades\Schedule;

/*
| Background work (emails, image resizing) uses the database queue.
|
| On Hostinger there is no long-running worker process. Instead, ONE cron job
| runs `php artisan schedule:run` every minute, and that in turn starts a
| queue worker which handles whatever is waiting and then exits.
| See docs/DEPLOYMENT.md for the exact cron line.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

// Forget failed jobs after a week and expired password-reset tokens daily.
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:clear-resets')->daily();
