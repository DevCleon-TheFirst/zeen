<?php

namespace App\Services\Automation\Nodes;

use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ActionAssignStaffHandler implements NodeHandlerInterface
{
    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $context = $execution->context ?? [];
        $conversation = $this->resolveConversation($execution, $context);

        if (! $conversation) {
            return ['action' => 'failed', 'reason' => 'no_conversation_found'];
        }

        $business = $execution->workflow->business;
        $userId = $node->config['user_id'] ?? null;
        $strategy = $node->config['strategy'] ?? 'specific';

        if ($strategy === 'round_robin' || ! $userId) {
            // Pick the active staff member with the fewest open conversations
            $user = User::where('business_id', $business->id)
                ->where('is_active', true)
                ->withCount(['conversations' => function ($q) {
                    $q->whereNotIn('status', ['resolved']);
                }])
                ->orderBy('conversations_count')
                ->first();
        } else {
            $user = User::where('id', $userId)->where('business_id', $business->id)->first();
        }

        if (! $user) {
            Log::warning("ActionAssignStaff: no eligible staff found for execution {$execution->id}");

            return ['action' => 'failed', 'reason' => 'no_staff_found'];
        }

        $conversation->update([
            'assigned_to' => $user->id,
            'handler' => 'human',
        ]);

        Log::info("ActionAssignStaff: assigned conversation#{$conversation->id} to user#{$user->id}");

        return [
            'action' => 'staff_assigned',
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
        ];
    }

    protected function resolveConversation(AutomationExecution $execution, array $context): ?Conversation
    {
        if (! empty($context['conversation_id'])) {
            return Conversation::find($context['conversation_id']);
        }

        return null;
    }
}
