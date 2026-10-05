<?php

namespace App\Services\Automation\Nodes;

use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

class ActionUpdateLeadHandler implements NodeHandlerInterface
{
    /** @var string[] Allowed updatable fields */
    protected array $allowedFields = ['stage', 'score', 'product_interest', 'notes', 'estimated_value', 'next_follow_up_at', 'assigned_to'];

    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $field = $node->config['field'] ?? null;
        $value = $node->config['value'] ?? null;

        if (! $field || ! in_array($field, $this->allowedFields)) {
            return ['action' => 'failed', 'reason' => "field '{$field}' not allowed"];
        }

        $context = $execution->context ?? [];
        $customerId = $context['customer_id'] ?? null;
        $leadId = $context['lead_id'] ?? null;

        $lead = $leadId
            ? Lead::find($leadId)
            : ($customerId ? Lead::where('customer_id', $customerId)->latest()->first() : null);

        if (! $lead) {
            Log::warning("ActionUpdateLead: no lead found for execution {$execution->id}");

            return ['action' => 'failed', 'reason' => 'no_lead_found'];
        }

        $lead->update([$field => $value]);

        Log::info("ActionUpdateLead: set lead#{$lead->id}.{$field} = {$value}");

        return [
            'action' => 'lead_updated',
            'lead_id' => $lead->id,
            'field' => $field,
            'value' => $value,
        ];
    }
}
