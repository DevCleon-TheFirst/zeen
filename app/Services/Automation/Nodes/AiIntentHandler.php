<?php

namespace App\Services\Automation\Nodes;

use App\Models\AiProviderSetting;
use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiService;
use Illuminate\Support\Facades\Log;

class AiIntentHandler implements NodeHandlerInterface
{
    public function __construct(protected AiService $aiService) {}

    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $intents = $node->config['intents'] ?? [];
        $defaultIntent = $node->config['default_intent'] ?? 'default';

        if (empty($intents)) {
            return $defaultIntent;
        }

        $lastMessage = $this->resolveLastMessage($execution);

        if (! $lastMessage) {
            Log::warning("AiIntentHandler: no message found in context for execution {$execution->id}");

            return $defaultIntent;
        }

        $business = $execution->workflow->business;
        $setting = AiProviderSetting::where('business_id', $business->id)->first();

        if (! $setting?->api_key) {
            // No API key — use keyword matching as a free fallback
            return $this->keywordMatch($lastMessage->content ?? '', $intents, $defaultIntent);
        }

        try {
            return $this->classifyWithLlm($lastMessage->content ?? '', $intents, $setting, $defaultIntent);
        } catch (\Throwable $e) {
            Log::error('AiIntentHandler LLM call failed: '.$e->getMessage());

            return $this->keywordMatch($lastMessage->content ?? '', $intents, $defaultIntent);
        }
    }

    protected function classifyWithLlm(string $text, array $intents, AiProviderSetting $setting, string $default): string
    {
        $intentList = collect($intents)
            ->map(fn ($i) => "- \"{$i['name']}\": {$i['description']}")
            ->implode("\n");

        $messages = [
            [
                'role' => 'system',
                'content' => "You are an intent classifier. Given a user message, classify it into exactly one of the following intents:\n{$intentList}\n\nRespond with ONLY the intent name — no explanation, no punctuation, just the name.",
            ],
            [
                'role' => 'user',
                'content' => $text,
            ],
        ];

        $response = $this->aiService->chat($setting, $messages, ['max_tokens' => 20, 'temperature' => 0.0]);
        $matched = strtolower(trim($response['content'] ?? ''));

        // Validate the response is one of the defined intents
        $validNames = array_map(fn ($i) => strtolower($i['name']), $intents);

        return in_array($matched, $validNames) ? $matched : $default;
    }

    /**
     * Simple keyword matching fallback when no AI API key is configured.
     */
    protected function keywordMatch(string $text, array $intents, string $default): string
    {
        $text = strtolower($text);

        foreach ($intents as $intent) {
            $keywords = $intent['keywords'] ?? [];
            foreach ($keywords as $keyword) {
                if (str_contains($text, strtolower($keyword))) {
                    return $intent['name'];
                }
            }
        }

        return $default;
    }

    protected function resolveLastMessage(AutomationExecution $execution): ?Message
    {
        $context = $execution->context ?? [];

        // Direct message reference from new_message trigger
        if (! empty($context['message_id'])) {
            return Message::find($context['message_id']);
        }

        // Fallback: last message in the conversation
        if (! empty($context['conversation_id'])) {
            return Message::where('conversation_id', $context['conversation_id'])
                ->where('direction', 'inbound')
                ->latest()
                ->first();
        }

        return null;
    }
}
