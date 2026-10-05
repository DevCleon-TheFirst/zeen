<?php

namespace App\Services\Automation;

use App\Models\AutomationEdge;
use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\AutomationWorkflow;
use App\Services\Automation\Nodes\ActionAddTagHandler;
use App\Services\Automation\Nodes\ActionAssignStaffHandler;
use App\Services\Automation\Nodes\ActionHttpRequestHandler;
use App\Services\Automation\Nodes\ActionSearchCatalogHandler;
use App\Services\Automation\Nodes\ActionSendEmailHandler;
use App\Services\Automation\Nodes\ActionUpdateConversationHandler;
use App\Services\Automation\Nodes\ActionUpdateLeadHandler;
use App\Services\Automation\Nodes\AiIntentHandler;
use App\Services\Automation\Nodes\ConditionCheckFieldHandler;
use App\Services\Automation\Nodes\ConditionTimeWindowHandler;
use App\Services\Automation\Nodes\DelayHandler;
use App\Services\Automation\Nodes\NodeHandlerInterface;
use App\Services\Automation\Nodes\SendMessageHandler;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Log;

class AutomationEngineService
{
    /**
     * Start a new workflow execution.
     */
    public function triggerWorkflow(AutomationWorkflow $workflow, array $context = []): AutomationExecution
    {
        // Find the trigger node (usually the one with no incoming edges)
        $triggerNodeId = AutomationNode::where('workflow_id', $workflow->id)
            ->whereNotIn('node_id', function ($query) use ($workflow) {
                $query->select('target_node_id')
                    ->from('automation_edges')
                    ->where('workflow_id', $workflow->id);
            })
            ->value('node_id');

        if (! $triggerNodeId) {
            throw new \RuntimeException('No trigger node found for workflow.');
        }

        $execution = AutomationExecution::create([
            'workflow_id' => $workflow->id,
            'status' => 'pending',
            'current_node_id' => $triggerNodeId,
            'context' => $context,
            'execution_log' => [],
            'started_at' => now(),
        ]);

        return $execution;
    }

    /**
     * Process an execution from its current node.
     */
    public function execute(AutomationExecution $execution): void
    {
        TenantContext::set($execution->workflow->business);

        // If the execution was re-dispatched after a delay, transition back to running
        if ($execution->status === 'waiting') {
            $execution->update(['status' => 'running']);
        }

        if (! in_array($execution->status, ['running', 'pending'])) {
            Log::info("AutomationEngine: skipping execution {$execution->id} with status={$execution->status}");

            return;
        }

        $execution->update(['status' => 'running']);

        try {
            $steps = 0;
            $maxSteps = 50;

            while ($execution->current_node_id) {
                $steps++;
                if ($steps > $maxSteps) {
                    throw new \RuntimeException("Workflow execution exceeded maximum limit of {$maxSteps} steps. Circular edge loop detected.");
                }

                $node = AutomationNode::where('workflow_id', $execution->workflow_id)
                    ->where('node_id', $execution->current_node_id)
                    ->first();

                if (! $node) {
                    throw new \RuntimeException("Node {$execution->current_node_id} not found.");
                }

                $handler = $this->getHandlerForNode($node->type);
                $result = null;
                $nextEdgeCondition = null;

                if ($handler) {
                    $result = $handler->handle($node, $execution);
                    // If the handler returns a string, we treat it as a condition label
                    if (is_string($result)) {
                        $nextEdgeCondition = $result;
                        $result = ['condition' => $result];
                    }
                }

                // Log execution
                $log = $execution->execution_log ?? [];
                $log[] = [
                    'node_id' => $node->node_id,
                    'executed_at' => now()->toIso8601String(),
                    'result' => $result,
                ];

                $execution->update(['execution_log' => $log]);

                // If the handler returned null AND execution is now 'waiting', the DelayHandler
                // has already re-dispatched the job — break out of the loop.
                if ($execution->status === 'waiting') {
                    break;
                }

                // Find next node with condition matching
                $edges = AutomationEdge::where('workflow_id', $execution->workflow_id)
                    ->where('source_node_id', $node->node_id)
                    ->get();

                $edge = null;
                if ($nextEdgeCondition) {
                    $conditionLower = mb_strtolower(trim((string) $nextEdgeCondition));
                    $edge = $edges->first(function ($e) use ($conditionLower) {
                        return mb_strtolower(trim((string) $e->condition_label)) === $conditionLower;
                    });

                    // If condition didn't match, fall back only to else/default or unlabeled edges
                    if (! $edge) {
                        $edge = $edges->first(function ($e) {
                            $label = mb_strtolower(trim((string) $e->condition_label));

                            return in_array($label, ['', 'else', 'default'], true);
                        });
                    }
                } else {
                    $edge = $edges->first();
                }

                if ($edge) {
                    $execution->update(['current_node_id' => $edge->target_node_id]);
                } else {
                    $execution->update([
                        'current_node_id' => null,
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Automation Engine Error: '.$e->getMessage());
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    protected function getHandlerForNode(string $type): ?NodeHandlerInterface
    {
        $normalizedType = match ($type) {
            'action' => 'action_send_message',
            'ai_step' => 'ai_intent',
            'condition' => 'condition_check_field',
            default => $type,
        };

        // Simple registry. In a larger app, use the Service Container.
        $map = [
            // Phase 1
            'action_send_message' => SendMessageHandler::class,
            'ai_intent' => AiIntentHandler::class,
            'action_search_catalog' => ActionSearchCatalogHandler::class,
            'delay' => DelayHandler::class,
            // Phase 2 — actions
            'action_http_request' => ActionHttpRequestHandler::class,
            'action_update_lead' => ActionUpdateLeadHandler::class,
            'action_add_tag' => ActionAddTagHandler::class,
            'action_assign_staff' => ActionAssignStaffHandler::class,
            'action_send_email' => ActionSendEmailHandler::class,
            'action_update_conversation' => ActionUpdateConversationHandler::class,
            // Phase 2 — conditions
            'condition_check_field' => ConditionCheckFieldHandler::class,
            'condition_time_window' => ConditionTimeWindowHandler::class,
        ];

        if (isset($map[$normalizedType])) {
            return app($map[$normalizedType]);
        }

        return null;
    }
}
