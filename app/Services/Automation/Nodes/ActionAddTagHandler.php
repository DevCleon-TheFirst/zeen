<?php

namespace App\Services\Automation\Nodes;

use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

class ActionAddTagHandler implements NodeHandlerInterface
{
    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $tag = trim($node->config['tag'] ?? '');

        if (! $tag) {
            return ['action' => 'failed', 'reason' => 'no tag configured'];
        }

        $context = $execution->context ?? [];
        $customerId = $context['customer_id'] ?? null;

        if (! $customerId) {
            return ['action' => 'failed', 'reason' => 'no customer_id in context'];
        }

        $customer = Customer::find($customerId);

        if (! $customer) {
            Log::warning("ActionAddTag: customer {$customerId} not found for execution {$execution->id}");

            return ['action' => 'failed', 'reason' => 'customer_not_found'];
        }

        $currentTags = (array) ($customer->tags ?? []);

        if (in_array($tag, $currentTags)) {
            return ['action' => 'skipped', 'reason' => 'tag_already_exists', 'tag' => $tag];
        }

        $currentTags[] = $tag;
        $customer->update(['tags' => array_values($currentTags)]);

        Log::info("ActionAddTag: added tag '{$tag}' to customer#{$customer->id}");

        return [
            'action' => 'tag_added',
            'customer_id' => $customer->id,
            'tag' => $tag,
        ];
    }
}
