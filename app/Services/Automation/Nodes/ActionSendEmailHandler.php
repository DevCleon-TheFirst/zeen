<?php

namespace App\Services\Automation\Nodes;

use App\Enums\ChannelType;
use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\Customer;
use App\Services\Channels\EmailSenderService;
use Illuminate\Support\Facades\Log;

class ActionSendEmailHandler implements NodeHandlerInterface
{
    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $subject = $node->config['subject'] ?? '(No Subject)';
        $body = $node->config['body'] ?? '';
        $toRaw = $node->config['to'] ?? null;

        $context = $execution->context ?? [];
        $customer = isset($context['customer_id']) ? Customer::find($context['customer_id']) : null;

        // Interpolate {{customer.name}}, {{customer.email}}, etc.
        $to = $this->interpolate($toRaw ?? $customer?->email ?? '', $context, $customer);
        $subject = $this->interpolate($subject, $context, $customer);
        $body = $this->interpolate($body, $context, $customer);

        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning("ActionSendEmail: invalid or missing recipient for execution {$execution->id} (to={$to})");

            return ['action' => 'failed', 'reason' => 'invalid_recipient'];
        }

        $business = $execution->workflow?->business;
        $emailChannel = $business?->channels()
            ->where('channel', ChannelType::Email)
            ->where('is_active', true)
            ->first();

        try {
            $emailSender = app(EmailSenderService::class);
            $sent = $emailSender->send($to, $subject, $body, $emailChannel);

            if ($sent) {
                Log::info("ActionSendEmail: sent to {$to} — {$subject}");

                return [
                    'action' => 'email_sent',
                    'to' => $to,
                    'subject' => $subject,
                ];
            }

            return ['action' => 'failed', 'reason' => 'email_send_failed'];
        } catch (\Throwable $e) {
            Log::error('ActionSendEmail failed: '.$e->getMessage());

            return ['action' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    protected function interpolate(string $value, array $context, ?Customer $customer): string
    {
        $replacements = array_merge($context, [
            'customer.name' => $customer?->name ?? '',
            'customer.email' => $customer?->email ?? '',
            'customer.phone' => $customer?->phone ?? '',
        ]);

        foreach ($replacements as $key => $val) {
            if (is_scalar($val)) {
                $value = str_replace("{{{$key}}}", (string) $val, $value);
            }
        }

        return $value;
    }
}
