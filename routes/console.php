<?php

use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fire scheduled automation workflows every minute
Schedule::command('automations:run-scheduled')->everyMinute();

// Prune old automation execution logs every Sunday at 03:00
Schedule::command('automations:prune-executions')->weeklyOn(0, '03:00');

// Auto-resume AI handling for conversations where owner has been idle for 3+ minutes
Schedule::command('conversation:resume-ai')->everyMinute();

// Exclude Horizon because this app uses database queue (QUEUE_CONNECTION=database), not Redis.
// Excluding Horizon automatically enables Laravel's built-in `queue:listen` process.
DevCommands::except('horizon');

// Auto-run the scheduler alongside serve, vite, reverb, and queue
DevCommands::register('php artisan schedule:work', 'Scheduler');

// Auto-poll Telegram in local dev so messages are ingested in real-time without needing a public webhook
DevCommands::register('php artisan telegram:poll', 'Telegram Poller');
