<?php

namespace App\Http\Controllers;

use App\Jobs\ExecuteWorkflowJob;
use App\Models\AutomationExecution;
use App\Models\AutomationWorkflow;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationExecutionController extends Controller
{
    public function index(Request $request, AutomationWorkflow $workflow): Response
    {
        $isSuperAdmin = (bool) auth()->user()?->is_super_admin;
        $business = TenantContext::get();
        abort_unless($isSuperAdmin || ($business && $workflow->business_id === $business->id), 403);

        $workflow->loadMissing('nodes');

        $statusFilter = $request->query('status');

        $query = AutomationExecution::where('workflow_id', $workflow->id)
            ->with(['conversation.customer', 'customer'])
            ->orderBy('id', 'desc');

        if ($statusFilter && in_array($statusFilter, ['completed', 'failed', 'waiting', 'running', 'pending'], true)) {
            $query->where('status', $statusFilter);
        }

        $executions = $query->paginate(25)->withQueryString();

        $stats = [
            'total' => AutomationExecution::where('workflow_id', $workflow->id)->count(),
            'completed' => AutomationExecution::where('workflow_id', $workflow->id)->where('status', 'completed')->count(),
            'failed' => AutomationExecution::where('workflow_id', $workflow->id)->where('status', 'failed')->count(),
            'waiting' => AutomationExecution::where('workflow_id', $workflow->id)->where('status', 'waiting')->count(),
            'running' => AutomationExecution::where('workflow_id', $workflow->id)->whereIn('status', ['running', 'pending'])->count(),
        ];

        return Inertia::render('Automations/Executions', [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'trigger_type' => $workflow->trigger_type,
                'is_active' => (bool) $workflow->is_active,
            ],
            // Flat node_id → {label, type} map so the execution timeline shows node labels
            'nodeMap' => $workflow->nodes->keyBy('node_id')->map(fn ($n) => [
                'label' => $n->label,
                'type' => $n->type,
            ])->all(),
            'executions' => $executions,
            'stats' => $stats,
            'currentFilter' => $statusFilter,
        ]);
    }

    public function show(AutomationWorkflow $workflow, AutomationExecution $execution): JsonResponse
    {
        $isSuperAdmin = (bool) auth()->user()?->is_super_admin;
        $business = TenantContext::get();
        abort_unless(
            ($isSuperAdmin || ($business && $workflow->business_id === $business->id)) &&
            $execution->workflow_id === $workflow->id,
            403
        );

        $execution->load(['conversation.customer', 'customer']);

        return response()->json([
            'execution' => $execution,
        ]);
    }

    public function retry(AutomationWorkflow $workflow, AutomationExecution $execution): RedirectResponse
    {
        $isSuperAdmin = (bool) auth()->user()?->is_super_admin;
        $business = TenantContext::get();
        abort_unless(
            ($isSuperAdmin || ($business && $workflow->business_id === $business->id)) &&
            $execution->workflow_id === $workflow->id,
            403
        );

        // Find the start node of the workflow
        $startNodeId = $workflow->nodes()
            ->whereNotIn('node_id', function ($query) use ($workflow) {
                $query->select('target_node_id')
                    ->from('automation_edges')
                    ->where('workflow_id', $workflow->id);
            })
            ->value('node_id');

        $execution->update([
            'status' => 'pending',
            'current_node_id' => $startNodeId,
            'error_message' => null,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        ExecuteWorkflowJob::dispatch($execution);

        return redirect()->back()->with('success', "Execution #{$execution->id} re-queued for processing.");
    }
}
