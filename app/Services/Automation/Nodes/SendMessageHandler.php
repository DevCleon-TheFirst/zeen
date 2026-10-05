<?php

namespace App\Services\Automation\Nodes;

use App\Enums\ConversationState;
use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Channels\ChannelSender;
use Illuminate\Support\Facades\Log;

class SendMessageHandler implements NodeHandlerInterface
{
    public function __construct(protected ChannelSender $channelSender) {}

    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $rawMessage = $node->config['message'] ?? '';
        if (! $rawMessage) {
            return ['action' => 'skipped', 'reason' => 'empty message'];
        }

        $message = $this->interpolateVariables($rawMessage, $execution);

        $conversation = $this->resolveConversation($execution);

        if (! $conversation) {
            Log::warning("SendMessageHandler: no conversation found for execution {$execution->id}");

            return ['action' => 'failed', 'reason' => 'no_conversation'];
        }

        $this->channelSender->send($conversation, $message, null, 'automation');

        return [
            'action' => 'message_sent',
            'message' => $message,
        ];
    }

    /**
     * Interpolate runtime variables into the template message.
     */
    public function interpolateVariables(string $template, AutomationExecution $execution): string
    {
        $context = $execution->context ?? [];
        $customer = $execution->customer ?? (! empty($context['customer_id']) ? Customer::find($context['customer_id']) : null);

        $variables = [
            'customer.name' => $customer?->name ?? $context['customer_name'] ?? 'Customer',
            'customer_name' => $customer?->name ?? $context['customer_name'] ?? 'Customer',
            'customer.phone' => $customer?->phone ?? $context['customer_phone'] ?? '',
            'customer_phone' => $customer?->phone ?? $context['customer_phone'] ?? '',
            'customer.email' => $customer?->email ?? $context['customer_email'] ?? '',
            'customer_email' => $customer?->email ?? $context['customer_email'] ?? '',

            'order.tracking_code' => $context['tracking_code'] ?? '',
            'tracking_code' => $context['tracking_code'] ?? '',
            'order.total_amount' => isset($context['total_amount']) ? number_format((float) $context['total_amount'], 2) : '',
            'total_amount' => isset($context['total_amount']) ? number_format((float) $context['total_amount'], 2) : '',
            'order.currency' => $context['currency'] ?? 'NGN',
            'currency' => $context['currency'] ?? 'NGN',
            'order.status' => $context['status'] ?? '',
            'status' => $context['status'] ?? '',

            'checkout_url' => $context['checkout_url'] ?? '',
            'payment.checkout_url' => $context['checkout_url'] ?? '',
            'payment.amount' => isset($context['amount']) ? number_format((float) $context['amount'], 2) : '',

            'catalog.summary' => $context['catalog_summary'] ?? '',
            'catalog_summary' => $context['catalog_summary'] ?? '',
            'item.name' => $context['item_name'] ?? '',
            'item_name' => $context['item_name'] ?? '',
            'item.stock' => isset($context['stock_quantity']) ? (string) $context['stock_quantity'] : '',
            'stock_quantity' => isset($context['stock_quantity']) ? (string) $context['stock_quantity'] : '',
        ];

        // Also merge any scalar values from context directly
        foreach ($context as $key => $val) {
            if (is_scalar($val) && ! isset($variables[$key])) {
                $variables[$key] = (string) $val;
            }
        }

        return preg_replace_callback('/\{\{\s*([\w\.\-]+)\s*\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];

            return $variables[$key] ?? $matches[0];
        }, $template);
    }

    /**
     * Resolve the conversation from the execution context.
     * Falls back to the customer's latest active conversation if no conversation_id is provided.
     */
    protected function resolveConversation(AutomationExecution $execution): ?Conversation
    {
        $context = $execution->context ?? [];

        // Direct conversation reference
        if (! empty($context['conversation_id'])) {
            return Conversation::find($context['conversation_id']);
        }

        // Fallback: find the customer's most recent active conversation
        if (! empty($context['customer_id'])) {
            return Conversation::where('customer_id', $context['customer_id'])
                ->whereNotIn('status', [ConversationState::Resolved])
                ->latest('last_message_at')
                ->first();
        }

        // Last resort: try Eloquent relation (backwards compat)
        try {
            return $execution->conversation;
        } catch (\Throwable) {
            return null;
        }
    }
}
