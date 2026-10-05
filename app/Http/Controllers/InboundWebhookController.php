<?php

namespace App\Http\Controllers;

use App\Models\AutomationWorkflow;
use App\Services\Automation\AutomationTriggerDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives external HTTP POST webhooks and fires matching automation workflows.
 *
 * Route: POST /api/webhooks/automation/{workflow}/{secret}
 *
 * Businesses can share this URL with any external service (Shopify, Typeform,
 * Calendly, etc.) to trigger their automation workflows.
 */
class InboundWebhookController extends Controller
{
    public function receive(Request $request, int $workflowId, string $secret): JsonResponse
    {
        $workflow = AutomationWorkflow::find($workflowId);

        if (! $workflow) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Validate the secret stored in trigger_config
        $expectedSecret = $workflow->trigger_config['secret'] ?? null;

        if (! $expectedSecret || ! hash_equals($expectedSecret, $secret)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! $workflow->is_active) {
            return response()->json(['status' => 'ignored', 'reason' => 'workflow_inactive'], 200);
        }

        // Merge request payload into context
        $payload = $request->all();
        $context = array_merge($payload, [
            'webhook_received_at' => now()->toIso8601String(),
            'workflow_id' => $workflow->id,
        ]);

        AutomationTriggerDispatcher::dispatch('webhook_received', $context, $workflow->business);

        return response()->json(['status' => 'accepted'], 202);
    }
}
