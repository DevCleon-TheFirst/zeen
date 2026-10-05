<?php

namespace App\Services\Automation;

use App\Jobs\ExecuteWorkflowJob;
use App\Models\AutomationExecution;
use App\Models\AutomationWorkflow;
use App\Models\Business;
use Illuminate\Support\Facades\Log;

class AutomationTriggerDispatcher
{
    /**
     * Find all active workflows matching the trigger type for the business
     * and dispatch an execution job for each one.
     *
     * @param  array<string, mixed>  $context  Runtime data: conversation_id, customer_id, lead_id, payment_id, etc.
     */
    public static function dispatch(string $triggerType, array $context, Business $business): void
    {
        $workflows = AutomationWorkflow::where('business_id', $business->id)
            ->where('trigger_type', $triggerType)
            ->where('is_active', true)
            ->get();

        if ($workflows->isEmpty()) {
            return;
        }

        foreach ($workflows as $workflow) {
            try {
                // Check business plan limits
                $guardCheck = AutomationPlanGuard::canExecute($workflow, $business);
                if (! $guardCheck['allowed']) {
                    Log::warning("Workflow {$workflow->id} blocked by plan guard: {$guardCheck['reason']}");

                    continue;
                }

                // Check trigger_config filter rules before creating an execution
                if (! self::passesTriggerFilter($workflow, $context)) {
                    continue;
                }

                // Find the start node (no incoming edges)
                $startNodeId = $workflow->nodes()
                    ->whereNotIn('node_id', function ($query) use ($workflow) {
                        $query->select('target_node_id')
                            ->from('automation_edges')
                            ->where('workflow_id', $workflow->id);
                    })
                    ->value('node_id');

                if (! $startNodeId) {
                    Log::warning("Workflow {$workflow->id} ({$workflow->name}) has no start node — skipping.");

                    continue;
                }

                $execution = AutomationExecution::create([
                    'workflow_id' => $workflow->id,
                    'conversation_id' => $context['conversation_id'] ?? null,
                    'customer_id' => $context['customer_id'] ?? null,
                    'status' => 'pending',
                    'current_node_id' => $startNodeId,
                    'context' => $context,
                    'execution_log' => [],
                    'started_at' => now(),
                ]);

                ExecuteWorkflowJob::dispatch($execution);

                Log::info("Automation dispatched: workflow={$workflow->id} trigger={$triggerType} execution={$execution->id}");
            } catch (\Throwable $e) {
                Log::error("AutomationTriggerDispatcher failed for workflow {$workflow->id}: ".$e->getMessage());
            }
        }
    }

    /**
     * Evaluate optional filter rules stored in trigger_config.
     * For example a new_message trigger may only want to fire for a specific channel.
     *
     * @param  array<string, mixed>  $context
     */
    protected static function passesTriggerFilter(AutomationWorkflow $workflow, array $context): bool
    {
        $config = $workflow->trigger_config ?? [];

        // Channel filter — only fire for a specific messaging channel
        if (! empty($config['channel_filter']) && isset($context['channel'])) {
            if ($config['channel_filter'] !== $context['channel']) {
                return false;
            }
        }

        // Lead stage filter — only fire when moving to a specific stage
        if (! empty($config['lead_stage']) && isset($context['new_stage'])) {
            if ($config['lead_stage'] !== $context['new_stage']) {
                return false;
            }
        }

        // Tag filter — only fire when a specific tag is added
        if (! empty($config['tag_filter']) && isset($context['tag'])) {
            if ($config['tag_filter'] !== $context['tag']) {
                return false;
            }
        }

        // Keyword filter — fire when inbound message matches keyword rule
        if (! empty($config['keyword_filter'])) {
            $incomingText = mb_strtolower(trim($context['message_text'] ?? $context['message'] ?? $context['text'] ?? ''));
            $filterKeyword = mb_strtolower(trim($config['keyword_filter']));
            $matchType = $config['keyword_match_type'] ?? 'contains';

            $matched = match ($matchType) {
                'equals' => $incomingText === $filterKeyword,
                'starts_with' => str_starts_with($incomingText, $filterKeyword),
                default => str_contains($incomingText, $filterKeyword),
            };

            if (! $matched) {
                return false;
            }
        }

        // Order status filter — fire only when order enters specific status (e.g., dispatched, delivered)
        if (! empty($config['order_status']) && isset($context['status'])) {
            if (mb_strtolower(trim($config['order_status'])) !== mb_strtolower(trim($context['status']))) {
                return false;
            }
        }

        return true;
    }
}
