<?php

namespace App\Services\Automation\Nodes;

use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class ConditionCheckFieldHandler implements NodeHandlerInterface
{
    /**
     * Returns "yes" or "no" based on evaluating a field condition.
     * The engine picks the edge whose condition_label matches the return value.
     */
    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $subject = $node->config['subject'] ?? null;   // customer|lead|conversation|payment
        $field = $node->config['field'] ?? null;
        $operator = $node->config['operator'] ?? 'equals';
        $value = $node->config['value'] ?? null;

        if (! $subject || ! $field) {
            return 'no';
        }

        $actualValue = $this->resolveFieldValue($subject, $field, $execution);

        $result = $this->evaluate($actualValue, $operator, $value);

        Log::info("ConditionCheckField: {$subject}.{$field} {$operator} {$value} → ".($result ? 'yes' : 'no'));

        return $result ? 'yes' : 'no';
    }

    protected function resolveFieldValue(string $subject, string $field, AutomationExecution $execution): mixed
    {
        $context = $execution->context ?? [];

        $model = match ($subject) {
            'customer' => isset($context['customer_id']) ? Customer::find($context['customer_id']) : null,
            'lead' => isset($context['lead_id']) ? Lead::find($context['lead_id']) : (isset($context['customer_id']) ? Lead::where('customer_id', $context['customer_id'])->latest()->first() : null),
            'conversation' => isset($context['conversation_id']) ? Conversation::find($context['conversation_id']) : null,
            'payment' => isset($context['payment_id']) ? Payment::find($context['payment_id']) : null,
            default => null,
        };

        if (! $model) {
            return null;
        }

        // Support dot notation: e.g. "tags" on customer
        return data_get($model->toArray(), $field);
    }

    protected function evaluate(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'contains' => is_array($actual)
                                 ? in_array($expected, $actual)
                                 : str_contains((string) $actual, (string) $expected),
            'not_contains' => is_array($actual)
                                 ? ! in_array($expected, $actual)
                                 : ! str_contains((string) $actual, (string) $expected),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'is_empty' => empty($actual),
            'is_not_empty' => ! empty($actual),
            'starts_with' => is_string($actual) && str_starts_with($actual, (string) $expected),
            'ends_with' => is_string($actual) && str_ends_with($actual, (string) $expected),
            default => false,
        };
    }
}
