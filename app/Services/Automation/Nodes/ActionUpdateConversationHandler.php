<?php

namespace App\Services\Automation\Nodes;

use App\Enums\ConversationState;
use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class ActionUpdateConversationHandler implements NodeHandlerInterface
{
    protected array $allowedFields = ['status', 'priority', 'handler', 'assigned_to'];

    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $context = $execution->context ?? [];

        if (empty($context['conversation_id'])) {
            return ['action' => 'skipped', 'reason' => 'no_conversation_in_context'];
        }

        $conversation = Conversation::find($context['conversation_id']);

        if (! $conversation) {
            return ['action' => 'failed', 'reason' => 'conversation_not_found'];
        }

        $updates = [];
        foreach ($this->allowedFields as $field) {
            if (array_key_exists($field, $node->config ?? [])) {
                $updates[$field] = $node->config[$field];
            }
        }

        if (empty($updates)) {
            return ['action' => 'skipped', 'reason' => 'no valid fields configured'];
        }

        // Handle resolved_at automatically when resolving
        if (isset($updates['status']) && $updates['status'] === ConversationState::Resolved->value) {
            $updates['resolved_at'] = now();
        }

        $conversation->update($updates);

        Log::info("ActionUpdateConversation: updated conversation#{$conversation->id}", $updates);

        return ['action' => 'conversation_updated', 'conversation_id' => $conversation->id, 'updates' => $updates];
    }
}
