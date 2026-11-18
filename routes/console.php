<?php

use Illuminate\Support\Facades\Schedule;

/*
| Background work (emails, image resizing) uses the database queue.
|
| There is no long-running worker process. On servers where PHP may start
| other processes, ONE cron job runs `php artisan schedule:run` every minute
| and the schedule below starts a queue worker that handles whatever is
| waiting and then exits. Hostinger disables proc_open, which schedule:run
| needs, so there the cron jobs run `queue:work --stop-when-empty …` and
| `queue:prune-failed` directly. See docs/DEPLOYMENT.md, step 9.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

// Forget failed jobs after a week and expired password-reset tokens daily.
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:clear-resets')->daily();
