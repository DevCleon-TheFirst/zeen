<?php

namespace App\Services;

use App\Models\AiProviderSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * Send chat completion request to the active AI provider.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{content: string, raw: array<string, mixed>}
     */
    public function chat(AiProviderSetting $setting, array $messages, array $options = []): array
    {
        $provider = $setting->provider;
        $apiKey = $setting->api_key;
        $model = $options['model'] ?? $setting->model;
        $temperature = (float) ($options['temperature'] ?? $setting->temperature);
        $maxTokens = (int) ($options['max_tokens'] ?? $setting->max_tokens);

        if ($provider === 'deepseek' || $provider === 'openai' || $provider === 'custom') {
            $baseUrl = match ($provider) {
                'deepseek' => 'https://api.deepseek.com/v1',
                'openai' => 'https://api.openai.com/v1',
                'custom' => rtrim($setting->base_url ?: 'https://api.deepseek.com/v1', '/'),
                default => 'https://api.deepseek.com/v1',
            };

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);

            if ($response->failed()) {
                Log::error("AI Chat Request failed ({$provider}): ".$response->body());
                throw new \RuntimeException($response->json('error.message') ?? 'AI provider request failed: '.$response->status());
            }

            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? '';

            return [
                'content' => $content,
                'raw' => $json,
            ];
        }

        if ($provider === 'anthropic') {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => $model ?: 'claude-3-5-sonnet-20241022',
                'messages' => $messages,
                'max_tokens' => $maxTokens ?: 1024,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->json('error.message') ?? 'Anthropic API failed');
            }

            $json = $response->json();
            $content = $json['content'][0]['text'] ?? '';

            return [
                'content' => $content,
                'raw' => $json,
            ];
        }

        throw new \InvalidArgumentException("Unsupported AI provider: {$provider}");
    }

    /**
     * Test connection to configured AI provider.
     *
     * @return array{success: bool, message: string, latency_ms: int}
     */
    public function testConnection(AiProviderSetting $setting): array
    {
        $start = microtime(true);

        try {
            $response = $this->chat($setting, [
                ['role' => 'user', 'content' => 'Hello! Please reply with exactly: Connection successful.'],
            ], ['max_tokens' => 20]);

            $latency = (int) round((microtime(true) - $start) * 1000);

            $setting->update([
                'last_tested_at' => now(),
                'last_test_passed' => true,
            ]);

            return [
                'success' => true,
                'message' => trim($response['content']) ?: 'Connection verified.',
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);

            $setting->update([
                'last_tested_at' => now(),
                'last_test_passed' => false,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'latency_ms' => $latency,
            ];
        }
    }

    /**
     * Query remaining credit balance from DeepSeek API.
     *
     * @return array{
     *     success: bool,
     *     is_available: bool,
     *     total_balance: float,
     *     granted_balance: float,
     *     topped_up_balance: float,
     *     currency: string,
     *     formatted: string,
     *     error?: string
     * }
     */
    public function getDeepSeekBalance(?string $apiKey = null): array
    {
        if (empty($apiKey)) {
            $apiKey = env('DEEPSEEK_API_KEY');

            if (empty($apiKey)) {
                $setting = AiProviderSetting::withoutGlobalScopes()
                    ->where('provider', 'deepseek')
                    ->whereNotNull('api_key')
                    ->where('api_key', '!=', '')
                    ->where('api_key', 'not like', '%placeholder%')
                    ->first();
                $apiKey = $setting?->api_key;
            }
        }

        if (empty($apiKey)) {
            return [
                'success' => false,
                'is_available' => false,
                'total_balance' => 0.0,
                'granted_balance' => 0.0,
                'topped_up_balance' => 0.0,
                'currency' => 'USD',
                'formatted' => '$0.00 USD',
                'error' => 'No DeepSeek API key configured.',
            ];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(6)
                ->get('https://api.deepseek.com/user/balance');

            if ($response->failed()) {
                return [
                    'success' => false,
                    'is_available' => false,
                    'total_balance' => 0.0,
                    'granted_balance' => 0.0,
                    'topped_up_balance' => 0.0,
                    'currency' => 'USD',
                    'formatted' => '$0.00 USD',
                    'error' => $response->json('error.message') ?? 'DeepSeek balance query failed (HTTP '.$response->status().')',
                ];
            }

            $json = $response->json();
            $info = $json['balance_infos'][0] ?? [];
            $total = (float) ($info['total_balance'] ?? 0.0);
            $granted = (float) ($info['granted_balance'] ?? 0.0);
            $toppedUp = (float) ($info['topped_up_balance'] ?? 0.0);
            $currency = $info['currency'] ?? 'USD';

            return [
                'success' => true,
                'is_available' => (bool) ($json['is_available'] ?? ($total > 0)),
                'total_balance' => $total,
                'granted_balance' => $granted,
                'topped_up_balance' => $toppedUp,
                'currency' => $currency,
                'formatted' => '$'.number_format($total, 2).' '.$currency,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'is_available' => false,
                'total_balance' => 0.0,
                'granted_balance' => 0.0,
                'topped_up_balance' => 0.0,
                'currency' => 'USD',
                'formatted' => '$0.00 USD',
                'error' => $e->getMessage(),
            ];
        }
    }
}
