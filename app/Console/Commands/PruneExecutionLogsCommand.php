<?php

namespace App\Console\Commands;

use App\Models\AutomationExecution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneExecutionLogsCommand extends Command
{
    /**
     * Prune completed automation execution logs older than a configured retention window.
     *
     * Usage:
     *   php artisan automations:prune-executions
     *   php artisan automations:prune-executions --completed-days=14 --failed-days=60
     *   php artisan automations:prune-executions --dry-run
     */
    protected $signature = 'automations:prune-executions
                            {--completed-days=30 : Retain completed executions for this many days}
                            {--failed-days=90    : Retain failed executions for this many days}
                            {--dry-run           : Report what would be pruned without deleting}';

    protected $description = 'Delete old automation execution records to keep the table lean';

    public function handle(): int
    {
        $completedDays = (int) $this->option('completed-days');
        $failedDays = (int) $this->option('failed-days');
        $isDryRun = (bool) $this->option('dry-run');

        $completedCutoff = now()->subDays($completedDays);
        $failedCutoff = now()->subDays($failedDays);

        $completedQuery = AutomationExecution::where('status', 'completed')
            ->where('completed_at', '<', $completedCutoff);

        $failedQuery = AutomationExecution::where('status', 'failed')
            ->where('completed_at', '<', $failedCutoff);

        $completedCount = $completedQuery->count();
        $failedCount = $failedQuery->count();
        $total = $completedCount + $failedCount;

        if ($isDryRun) {
            $this->line('');
            $this->info('[DRY RUN] No records will be deleted.');
            $this->table(
                ['Status', 'Older Than', 'Would Delete'],
                [
                    ['completed', "{$completedDays} days", $completedCount],
                    ['failed', "{$failedDays} days", $failedCount],
                    ['TOTAL', '—', $total],
                ]
            );

            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('No execution records matched the pruning criteria. Nothing to delete.');

            return self::SUCCESS;
        }

        $completedQuery->delete();
        $failedQuery->delete();

        $summary = "Pruned {$completedCount} completed + {$failedCount} failed automation executions (total: {$total}).";

        $this->info($summary);

        Log::info('[PruneExecutionLogsCommand] '.$summary, [
            'completed_days' => $completedDays,
            'failed_days' => $failedDays,
            'completed_deleted' => $completedCount,
            'failed_deleted' => $failedCount,
        ]);

        return self::SUCCESS;
    }
}
