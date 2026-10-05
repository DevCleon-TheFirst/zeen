<?php

namespace App\Services\Automation\Nodes;

use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActionHttpRequestHandler implements NodeHandlerInterface
{
    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $method = strtoupper($node->config['method'] ?? 'POST');
        $url = $node->config['url'] ?? null;
        $headers = $node->config['headers'] ?? [];
        $body = $node->config['body'] ?? [];

        if (! $url) {
            return ['action' => 'failed', 'reason' => 'no_url_configured'];
        }

        // Interpolate {{variable}} placeholders from execution context
        $context = $execution->context ?? [];
        $customerId = $context['customer_id'] ?? $execution->customer_id;
        $customer = $customerId ? Customer::find($customerId) : null;
        if ($customer) {
            $context['customer'] = [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ];
            $context['customer.name'] = $customer->name;
            $context['customer.email'] = $customer->email;
            $context['customer.phone'] = $customer->phone;
        }

        if (! empty($context['lead_id'])) {
            $lead = Lead::find($context['lead_id']);
            if ($lead) {
                $context['lead.stage'] = $lead->stage;
                $context['lead.status'] = $lead->status;
            }
        }

        $url = $this->interpolate($url, $context);
        $body = $this->interpolateArray($body, $context);
        $headers = $this->interpolateArray($headers, $context);

        try {
            $request = Http::timeout(15)->withHeaders($headers);

            $response = match ($method) {
                'GET' => $request->get($url, $body),
                'POST' => $request->post($url, $body),
                'PUT' => $request->put($url, $body),
                'PATCH' => $request->patch($url, $body),
                'DELETE' => $request->delete($url, $body),
                default => $request->post($url, $body),
            };

            $statusCode = $response->status();
            $responseBody = $response->json() ?? $response->body();

            Log::info("ActionHttpRequest: {$method} {$url} → {$statusCode}");

            return [
                'action' => 'http_request_sent',
                'method' => $method,
                'url' => $url,
                'status_code' => $statusCode,
                'response_body' => is_array($responseBody) ? array_slice($responseBody, 0, 5) : substr((string) $responseBody, 0, 500),
                'success' => $response->successful(),
            ];
        } catch (\Throwable $e) {
            Log::error('ActionHttpRequest failed: '.$e->getMessage());

            return ['action' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /** Replace {{key}} placeholders with values from the context bag. */
    protected function interpolate(string $value, array $context): string
    {
        return preg_replace_callback('/\{\{([\w.]+)\}\}/', function ($matches) use ($context) {
            $key = $matches[1];
            $parts = explode('.', $key);
            $val = $context;

            foreach ($parts as $part) {
                if (is_array($val) && array_key_exists($part, $val)) {
                    $val = $val[$part];
                } else {
                    return $matches[0]; // leave placeholder if not found
                }
            }

            return is_scalar($val) ? (string) $val : json_encode($val);
        }, $value);
    }

    /** Recursively interpolate all string values in an array. */
    protected function interpolateArray(array $arr, array $context): array
    {
        foreach ($arr as $key => $value) {
            if (is_string($value)) {
                $arr[$key] = $this->interpolate($value, $context);
            } elseif (is_array($value)) {
                $arr[$key] = $this->interpolateArray($value, $context);
            }
        }

        return $arr;
    }
}
