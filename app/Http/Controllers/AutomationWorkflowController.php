<?php

namespace App\Http\Controllers;

use App\Models\AutomationEdge;
use App\Models\AutomationNode;
use App\Models\AutomationTemplate;
use App\Models\AutomationWorkflow;
use App\Services\Automation\AutomationPlanGuard;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AutomationWorkflowController extends Controller
{
    public function index(): Response
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $workflows = AutomationWorkflow::where('business_id', $business->id)
            ->withCount(['nodes', 'executions'])
            ->orderBy('id', 'desc')
            ->get();

        $templates = AutomationTemplate::where('is_published', true)->get();

        return Inertia::render('Automations/Index', [
            'workflows' => $workflows,
            'templates' => $templates,
            'businessPlan' => [
                'plan' => $business->plan ?? 'free',
                'maxActive' => AutomationPlanGuard::maxActiveWorkflows($business),
            ],
        ]);
    }

    public function create(): Response
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        return Inertia::render('Automations/Builder', [
            'workflow' => null,
            'nodeTypes' => $this->getAvailableNodePalette(),
            'businessPlan' => [
                'plan' => $business->plan ?? 'free',
                'maxActive' => AutomationPlanGuard::maxActiveWorkflows($business),
            ],
        ]);
    }

    public function edit(AutomationWorkflow $workflow): Response
    {
        $business = TenantContext::get();
        abort_unless($business && $workflow->business_id === $business->id, 403);

        $workflow->load(['nodes', 'edges']);

        return Inertia::render('Automations/Builder', [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'trigger_type' => $workflow->trigger_type,
                'trigger_config' => $workflow->trigger_config,
                'is_active' => (bool) $workflow->is_active,
                'nodes' => $workflow->nodes->map(fn ($n) => [
                    'id' => $n->node_id,
                    'type' => $n->type,
                    'label' => $n->label,
                    'config' => $n->config ?? [],
                    'position' => ['x' => (float) $n->position_x, 'y' => (float) $n->position_y],
                ]),
                'edges' => $workflow->edges->map(fn ($e) => [
                    'id' => $e->edge_id,
                    'source' => $e->source_node_id,
                    'target' => $e->target_node_id,
                    'label' => $e->condition_label,
                ]),
            ],
            'nodeTypes' => $this->getAvailableNodePalette(),
            'businessPlan' => [
                'plan' => $business->plan ?? 'free',
                'maxActive' => AutomationPlanGuard::maxActiveWorkflows($business),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|string',
            'trigger_config' => 'nullable|array',
            'nodes' => 'present|array',
            'edges' => 'present|array',
            'is_active' => 'boolean',
        ]);

        $isActiveRequested = ! empty($validated['is_active']);
        $warningMessage = null;

        $workflow = DB::transaction(function () use ($validated, $business) {
            $wf = AutomationWorkflow::create([
                'business_id' => $business->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'trigger_type' => $validated['trigger_type'],
                'trigger_config' => $validated['trigger_config'] ?? [],
                'is_active' => false, // Set to false initially, checked by guard below
            ]);

            foreach ($validated['nodes'] as $n) {
                AutomationNode::create([
                    'workflow_id' => $wf->id,
                    'node_id' => $n['id'],
                    'type' => $n['type'],
                    'label' => $n['label'] ?? null,
                    'config' => $n['config'] ?? [],
                    'position_x' => $n['position']['x'] ?? 0,
                    'position_y' => $n['position']['y'] ?? 0,
                ]);
            }

            foreach ($validated['edges'] as $e) {
                AutomationEdge::create([
                    'workflow_id' => $wf->id,
                    'edge_id' => $e['id'],
                    'source_node_id' => $e['source'],
                    'target_node_id' => $e['target'],
                    'condition_label' => $e['label'] ?? null,
                ]);
            }

            return $wf;
        });

        if ($isActiveRequested) {
            $guard = AutomationPlanGuard::canActivate($workflow, $business);
            if ($guard['allowed']) {
                $workflow->update(['is_active' => true]);
            } else {
                $warningMessage = $guard['reason'];
            }
        }

        $redirect = redirect()->route('automations.edit', $workflow->id)->with('success', 'Workflow created successfully.');
        if ($warningMessage) {
            $redirect->with('warning', $warningMessage);
        }

        return $redirect;
    }

    public function update(Request $request, AutomationWorkflow $workflow): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $workflow->business_id === $business->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|string',
            'trigger_config' => 'nullable|array',
            'nodes' => 'present|array',
            'edges' => 'present|array',
            'is_active' => 'boolean',
        ]);

        $isActiveRequested = ! empty($validated['is_active']);
        $warningMessage = null;

        DB::transaction(function () use ($workflow, $validated) {
            $workflow->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'trigger_type' => $validated['trigger_type'],
                'trigger_config' => $validated['trigger_config'] ?? [],
                'is_active' => false,
                'version' => $workflow->version + 1,
            ]);

            // Sync nodes
            $workflow->nodes()->delete();
            foreach ($validated['nodes'] as $n) {
                AutomationNode::create([
                    'workflow_id' => $workflow->id,
                    'node_id' => $n['id'],
                    'type' => $n['type'],
                    'label' => $n['label'] ?? null,
                    'config' => $n['config'] ?? [],
                    'position_x' => $n['position']['x'] ?? 0,
                    'position_y' => $n['position']['y'] ?? 0,
                ]);
            }

            // Sync edges
            $workflow->edges()->delete();
            foreach ($validated['edges'] as $e) {
                AutomationEdge::create([
                    'workflow_id' => $workflow->id,
                    'edge_id' => $e['id'],
                    'source_node_id' => $e['source'],
                    'target_node_id' => $e['target'],
                    'condition_label' => $e['label'] ?? null,
                ]);
            }
        });

        if ($isActiveRequested) {
            $guard = AutomationPlanGuard::canActivate($workflow, $business);
            if ($guard['allowed']) {
                $workflow->update(['is_active' => true]);
            } else {
                $warningMessage = $guard['reason'];
            }
        }

        $redirect = redirect()->back()->with('success', 'Workflow saved successfully.');
        if ($warningMessage) {
            $redirect->with('warning', $warningMessage);
        }

        return $redirect;
    }

    public function fromTemplate(AutomationTemplate $template): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $snapshot = $template->workflow_snapshot;

        $workflow = DB::transaction(function () use ($template, $snapshot, $business) {
            $wf = AutomationWorkflow::create([
                'business_id' => $business->id,
                'name' => $template->name,
                'description' => $template->description,
                'trigger_type' => 'new_message',
                'is_active' => false,
            ]);

            foreach ($snapshot['nodes'] ?? [] as $n) {
                AutomationNode::create([
                    'workflow_id' => $wf->id,
                    'node_id' => $n['id'],
                    'type' => $n['type'],
                    'label' => $n['label'] ?? null,
                    'config' => $n['config'] ?? [],
                    'position_x' => $n['position']['x'] ?? 0,
                    'position_y' => $n['position']['y'] ?? 0,
                ]);
            }

            foreach ($snapshot['edges'] ?? [] as $e) {
                AutomationEdge::create([
                    'workflow_id' => $wf->id,
                    'edge_id' => $e['id'],
                    'source_node_id' => $e['source'],
                    'target_node_id' => $e['target'],
                    'condition_label' => $e['label'] ?? null,
                ]);
            }

            return $wf;
        });

        return redirect()->route('automations.edit', $workflow->id)->with('success', 'Workflow imported from template.');
    }

    public function destroy(AutomationWorkflow $workflow): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $workflow->business_id === $business->id, 403);

        $workflow->delete();

        return redirect()->route('automations.index')->with('success', 'Workflow deleted.');
    }

    private function getAvailableNodePalette(): array
    {
        return [
            'triggers' => [
                ['type' => 'trigger_whatsapp', 'label' => 'WhatsApp Message', 'desc' => 'Fires on incoming WhatsApp customer message', 'icon' => 'whatsapp'],
                ['type' => 'trigger_telegram', 'label' => 'Telegram Message', 'desc' => 'Fires on incoming Telegram bot message', 'icon' => 'telegram'],
                ['type' => 'trigger_messenger', 'label' => 'Messenger Message', 'desc' => 'Fires on incoming Facebook Messenger message', 'icon' => 'messenger'],
                ['type' => 'trigger_order', 'label' => 'Order Completed / Paid', 'desc' => 'Fires when an order is created upon customer checkout', 'icon' => 'shopping-bag'],
                ['type' => 'trigger_abandoned_checkout', 'label' => 'Abandoned Checkout', 'desc' => 'Fires when customer generates checkout link but does not pay', 'icon' => 'clock'],
                ['type' => 'trigger_inventory_low', 'label' => 'Low Stock Warning', 'desc' => 'Fires when a catalog product drops to or below threshold', 'icon' => 'alert-triangle'],
                ['type' => 'trigger_inventory_out_of_stock', 'label' => 'Out of Stock Alert', 'desc' => 'Fires when product inventory reaches 0', 'icon' => 'slash'],
                ['type' => 'trigger_lead', 'label' => 'Lead Created / Stage', 'desc' => 'Fires when a new lead is captured or stage changed', 'icon' => 'user'],
                ['type' => 'trigger_payment', 'label' => 'Payment Received', 'desc' => 'Fires when payment is verified (Paystack/Monnify)', 'icon' => 'credit-card'],
                ['type' => 'trigger_appointment', 'label' => 'Appointment Booked', 'desc' => 'Fires when an inspection or appointment is scheduled', 'icon' => 'calendar'],
                ['type' => 'trigger_scheduled', 'label' => 'Schedule (Cron)', 'desc' => 'Fires periodically on a cron schedule', 'icon' => 'clock'],
                ['type' => 'trigger_webhook', 'label' => 'Inbound Webhook', 'desc' => 'Fires on external POST to secret webhook URL', 'icon' => 'globe'],
            ],
            'flow_control' => [
                ['type' => 'delay', 'label' => 'Wait / Delay', 'desc' => 'Pauses workflow execution for N minutes/hours/days (drip)', 'icon' => 'clock'],
                ['type' => 'condition_check_field', 'label' => 'Check Field Value', 'desc' => 'Branches path based on customer, lead, or conversation fields', 'icon' => 'git-branch'],
                ['type' => 'condition_time_window', 'label' => 'Business Hours', 'desc' => 'Branches path based on time of day and day of week', 'icon' => 'calendar'],
                ['type' => 'ai_intent', 'label' => 'AI Intent Classifier', 'desc' => 'Uses LLM to classify customer intent & route edges', 'icon' => 'cpu'],
            ],
            'actions' => [
                ['type' => 'action_send_message', 'label' => 'Send Channel Message', 'desc' => 'Replies to customer on WhatsApp, Telegram, or Messenger', 'icon' => 'send'],
                ['type' => 'action_send_email', 'label' => 'Send Email', 'desc' => 'Sends email using SMTP credentials or default mailer', 'icon' => 'mail'],
                ['type' => 'action_http_request', 'label' => 'HTTP / Webhook Request', 'desc' => 'Calls external API with payload & template variables', 'icon' => 'globe'],
                ['type' => 'action_update_lead', 'label' => 'Update Lead Status', 'desc' => 'Updates lead stage, score, or notes', 'icon' => 'user-check'],
                ['type' => 'action_add_tag', 'label' => 'Add Customer Tag', 'desc' => 'Appends a tag (e.g. VIP, Prospect) to customer', 'icon' => 'tag'],
                ['type' => 'action_assign_staff', 'label' => 'Assign Staff Member', 'desc' => 'Assigns conversation to agent or round-robin', 'icon' => 'users'],
                ['type' => 'action_update_conversation', 'label' => 'Update Conversation', 'desc' => 'Updates priority, status (e.g. resolved), or snooze', 'icon' => 'check-circle'],
                ['type' => 'action_search_catalog', 'label' => 'Search Catalog / Inventory', 'desc' => 'Queries business catalog items for matching criteria', 'icon' => 'search'],
            ],
        ];
    }
}
