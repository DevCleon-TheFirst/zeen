<?php

namespace App\Console\Commands;

use App\Models\AutomationWorkflow;
use App\Services\Automation\AutomationTriggerDispatcher;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunScheduledWorkflowsCommand extends Command
{
    protected $signature = 'automations:run-scheduled';

    protected $description = 'Fire all scheduled automation workflows whose next_run_at is due';

    public function handle(): int
    {
        $now = Carbon::now();

        $workflows = AutomationWorkflow::where('trigger_type', 'scheduled')
            ->where('is_active', true)
            ->get();

        $fired = 0;

        foreach ($workflows as $workflow) {
            $config = $workflow->trigger_config ?? [];
            $nextRun = isset($config['next_run_at']) ? Carbon::parse($config['next_run_at']) : null;

            if ($nextRun && $nextRun->isAfter($now)) {
                continue; // Not due yet
            }

            try {
                AutomationTriggerDispatcher::dispatch('scheduled', [
                    'workflow_id' => $workflow->id,
                    'triggered_at' => $now->toIso8601String(),
                ], $workflow->business);

                // Calculate next run time from cron expression
                $nextRunAt = $this->calculateNextRun($config['cron'] ?? null, $now);

                $workflow->update([
                    'trigger_config' => array_merge($config, [
                        'last_run_at' => $now->toIso8601String(),
                        'next_run_at' => $nextRunAt?->toIso8601String(),
                    ]),
                ]);

                $fired++;
                Log::info("Scheduled workflow fired: {$workflow->id} ({$workflow->name})");
            } catch (\Throwable $e) {
                Log::error("Failed to fire scheduled workflow {$workflow->id}: ".$e->getMessage());
            }
        }

        $this->info("Fired {$fired} scheduled workflow(s).");

        return self::SUCCESS;
    }

    protected function calculateNextRun(?string $cron, Carbon $from): ?Carbon
    {
        if (! $cron) {
            return null; // One-time workflow — do not reschedule
        }

        try {
            $schedule = CronExpression::factory($cron);

            return Carbon::instance($schedule->getNextRunDate($from->toDateTimeString()));
        } catch (\Throwable) {
            return null;
        }
    }
}
