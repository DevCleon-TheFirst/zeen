<?php

namespace App\Jobs;

use App\Models\AutomationExecution;
use App\Services\Automation\AutomationEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AutomationExecution $execution
    ) {}

    public function handle(AutomationEngineService $engine): void
    {
        $engine->execute($this->execution);
    }
}
