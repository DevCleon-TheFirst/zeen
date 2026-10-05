<?php

namespace App\Http\Controllers;

use App\Models\AiProviderSetting;
use App\Services\AiService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingController extends Controller
{
    public function __construct(
        protected AiService $aiService,
    ) {}

    public function index(): Response
    {
        $business = TenantContext::get();
        abort_unless($business, 404, 'Business context not found');

        $setting = AiProviderSetting::firstOrCreate(
            ['business_id' => $business->id],
            [
                'provider' => 'deepseek',
                'api_key' => '',
                'base_url' => 'https://api.deepseek.com',
                'model' => 'deepseek-chat',
                'temperature' => 0.70,
                'max_tokens' => 2000,
                'is_active' => true,
            ]
        );

        $maskedKey = '';
        if (! empty($setting->api_key)) {
            $len = strlen($setting->api_key);
            $maskedKey = $len > 8
                ? substr($setting->api_key, 0, 4).str_repeat('•', max(4, $len - 8)).substr($setting->api_key, -4)
                : '••••••••';
        }

        return Inertia::render('Settings/Ai', [
            'setting' => [
                'id' => $setting->id,
                'provider' => $setting->provider,
                'has_api_key' => ! empty($setting->api_key),
                'masked_api_key' => $maskedKey,
                'base_url' => $setting->base_url,
                'model' => $setting->model,
                'temperature' => (float) $setting->temperature,
                'max_tokens' => (int) $setting->max_tokens,
                'is_active' => (bool) $setting->is_active,
                'last_tested_at' => $setting->last_tested_at?->toIso8601String(),
                'last_test_passed' => $setting->last_test_passed,
            ],
            'availableProviders' => [
                [
                    'id' => 'deepseek',
                    'name' => 'DeepSeek AI (Default & Recommended)',
                    'description' => 'Fast, cost-effective, state-of-the-art reasoning (DeepSeek-V3 / R1)',
                    'defaultModel' => 'deepseek-chat',
                    'models' => ['deepseek-chat', 'deepseek-reasoner'],
                    'needsBaseUrl' => false,
                ],
                [
                    'id' => 'openai',
                    'name' => 'OpenAI',
                    'description' => 'GPT-4o, GPT-4o-mini',
                    'defaultModel' => 'gpt-4o',
                    'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
                    'needsBaseUrl' => false,
                ],
                [
                    'id' => 'anthropic',
                    'name' => 'Anthropic Claude',
                    'description' => 'Claude 3.5 Sonnet, Claude 3.5 Haiku',
                    'defaultModel' => 'claude-3-5-sonnet-20241022',
                    'models' => ['claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022'],
                    'needsBaseUrl' => false,
                ],
                [
                    'id' => 'custom',
                    'name' => 'Custom OpenAI-Compatible API / Local LLM',
                    'description' => 'Ollama, vLLM, self-hosted, or third-party proxy',
                    'defaultModel' => 'custom-model',
                    'models' => ['custom-model'],
                    'needsBaseUrl' => true,
                ],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $validated = $request->validate([
            'provider' => 'required|string|in:deepseek,openai,anthropic,custom',
            'api_key' => 'nullable|string',
            'base_url' => 'nullable|string|url',
            'model' => 'required|string|max:100',
            'temperature' => 'required|numeric|min:0|max:2',
            'max_tokens' => 'required|integer|min:50|max:8192',
            'is_active' => 'boolean',
        ]);

        $setting = AiProviderSetting::firstOrNew(['business_id' => $business->id]);
        $setting->provider = $validated['provider'];
        if (! empty($validated['api_key'])) {
            $setting->api_key = $validated['api_key'];
        }
        $setting->base_url = $validated['base_url'] ?? ($validated['provider'] === 'deepseek' ? 'https://api.deepseek.com' : null);
        $setting->model = $validated['model'];
        $setting->temperature = $validated['temperature'];
        $setting->max_tokens = $validated['max_tokens'];
        $setting->is_active = $validated['is_active'] ?? true;
        $setting->save();

        return redirect()->back()->with('success', 'AI provider settings updated successfully.');
    }

    public function test(): JsonResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $setting = AiProviderSetting::where('business_id', $business->id)->first();
        if (! $setting || empty($setting->api_key)) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide and save an API key before testing connection.',
            ], 422);
        }

        $result = $this->aiService->testConnection($setting);

        return response()->json($result);
    }
}
