<?php

namespace App\Services\Automation\Nodes;

use App\Jobs\ExecuteWorkflowJob;
use App\Models\AutomationEdge;
use App\Models\AutomationExecution;
use App\Models\AutomationNode;

class DelayHandler implements NodeHandlerInterface
{
    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $delayMinutes = (int) ($node->config['delay_minutes'] ?? 60);

        // Find the next node so we can advance current_node_id before pausing
        $nextEdge = AutomationEdge::where('workflow_id', $execution->workflow_id)
            ->where('source_node_id', $node->node_id)
            ->first();

        $nextNodeId = $nextEdge?->target_node_id;

        // Advance to the next node and mark as waiting
        $execution->update([
            'status' => 'waiting',
            'current_node_id' => $nextNodeId,
        ]);

        // Re-dispatch the job to resume after the delay
        ExecuteWorkflowJob::dispatch($execution)->delay(now()->addMinutes($delayMinutes));

        return [
            'action' => 'delay_started',
            'delay_minutes' => $delayMinutes,
            'resumes_at' => now()->addMinutes($delayMinutes)->toIso8601String(),
        ];
    }
}
