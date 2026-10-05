<?php

namespace App\Services\Channels;

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Events\MessageReceived;
use App\Models\Business;
use App\Models\BusinessChannel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerChannelIdentity;
use App\Models\Message;
use App\Services\AiAgentOrchestrator;
use App\Services\Automation\AutomationTriggerDispatcher;
use App\Services\Catalog\ChatAdminOpsService;
use App\Services\Orders\OrderFulfillmentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageIngestionService
{
    public function __construct(
        protected AiAgentOrchestrator $aiOrchestrator,
    ) {}

    /**
     * Ingest an incoming message from Telegram.
     */
    public function ingestTelegram(Business $business, array $payload): ?Message
    {
        $isCallback = isset($payload['callback_query']);
        $messageData = $payload['message'] ?? $payload['edited_message'] ?? $payload['callback_query']['message'] ?? null;

        if (! $messageData && ! $isCallback) {
            return null;
        }

        $chat = $messageData['chat'] ?? [];
        $from = $isCallback ? $payload['callback_query']['from'] : ($messageData['from'] ?? []);
        $platformMsgId = (string) ($messageData['message_id'] ?? uniqid('tg_'));
        $chatId = (string) ($chat['id'] ?? $from['id'] ?? '');
        $senderName = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')) ?: ($from['username'] ?? 'Telegram Customer');
        $username = $from['username'] ?? null;

        if ($isCallback) {
            $text = $payload['callback_query']['data'] ?? null;
        } else {
            $text = $messageData['text'] ?? $messageData['caption'] ?? null;
        }

        if (! $chatId) {
            return null;
        }

        $media = [];
        if (isset($messageData['photo'])) {
            $photo = end($messageData['photo']); // best quality
            $channel = BusinessChannel::where('business_id', $business->id)->where('channel', ChannelType::Telegram)->first();
            if ($channel) {
                $url = app(MediaDownloadService::class)->downloadTelegramMedia($channel, $photo['file_id'], 'image/jpeg');
                if ($url) {
                    $media[] = ['type' => 'image', 'url' => $url];
                }
            }
        } elseif (isset($messageData['voice'])) {
            $channel = BusinessChannel::where('business_id', $business->id)->where('channel', ChannelType::Telegram)->first();
            if ($channel) {
                $url = app(MediaDownloadService::class)->downloadTelegramMedia($channel, $messageData['voice']['file_id'], $messageData['voice']['mime_type'] ?? 'audio/ogg');
                if ($url) {
                    $media[] = ['type' => 'audio', 'url' => $url];
                }
            }
        }

        return $this->processInbound(
            business: $business,
            channelType: ChannelType::Telegram,
            channelUserId: $chatId,
            senderName: $senderName,
            username: $username,
            platformMessageId: 'tg_'.$platformMsgId,
            content: $text,
            media: empty($media) ? null : $media
        );
    }

    /**
     * Ingest an incoming message from WhatsApp Cloud API.
     */
    public function ingestWhatsapp(Business $business, array $payload): ?Message
    {
        $entry = $payload['entry'][0] ?? null;
        $change = $entry['changes'][0]['value'] ?? null;
        $messages = $change['messages'] ?? [];
        $statuses = $change['statuses'] ?? [];
        $contacts = $change['contacts'] ?? [];

        // ── Human Takeover Detection ──────────────────────────────────────────
        // Only trigger takeover if Meta reports a sent message not sent by our bot
        if (! empty($statuses) && empty($messages)) {
            foreach ($statuses as $status) {
                $msgId = $status['id'] ?? null;
                $statusVal = $status['status'] ?? '';

                // If this message already exists in our DB, it was sent by our system (AI or dashboard).
                // Just update delivery/read status and do NOT pause AI.
                if ($msgId) {
                    $existingMsg = Message::where('channel_message_id', $msgId)->first();
                    if ($existingMsg) {
                        if ($existingMsg->status !== 'read' && in_array($statusVal, ['sent', 'delivered', 'read'])) {
                            $existingMsg->update(['status' => $statusVal]);
                        }

                        continue;
                    }
                }

                // If status is 'sent' for an unknown message ID, it was sent externally from WhatsApp Business App
                if ($statusVal === 'sent') {
                    $recipientPhone = $status['recipient_id'] ?? null;

                    if ($recipientPhone) {
                        $identity = CustomerChannelIdentity::where('channel', ChannelType::Whatsapp)
                            ->where('channel_user_id', $recipientPhone)
                            ->first();

                        if ($identity) {
                            $conversation = Conversation::where('customer_id', $identity->customer_id)
                                ->where('business_id', $business->id)
                                ->where('channel', ChannelType::Whatsapp)
                                ->whereIn('status', [
                                    ConversationState::New,
                                    ConversationState::AiHandling,
                                    ConversationState::HumanHandling,
                                    ConversationState::WaitingForCustomer,
                                ])
                                ->latest()
                                ->first();

                            if ($conversation) {
                                $cacheKey = "takeover_cooldown_conv_{$conversation->id}";
                                if (! Cache::has($cacheKey)) {
                                    Cache::put($cacheKey, true, 60);
                                    $conversation->update([
                                        'handler' => 'human',
                                        'status' => ConversationState::HumanHandling,
                                        'human_last_replied_at' => now(),
                                        'last_message_at' => now(),
                                    ]);
                                    Log::info("[HumanTakeover] Owner sent from WhatsApp app to conversation #{$conversation->id}. AI paused for at least 3 minutes.");
                                }
                            }
                        }
                    }
                }
            }

            return null; // Status-only payload — nothing to ingest as a customer message
        }

        if (empty($messages)) {
            return null;
        }

        $channel = BusinessChannel::where('business_id', $business->id)->where('channel', ChannelType::Whatsapp)->first();
        $msg = $messages[0];
        $platformMsgId = (string) ($msg['id'] ?? uniqid('wa_'));
        $senderPhone = (string) ($msg['from'] ?? '');
        $contactName = $contacts[0]['profile']['name'] ?? ('WhatsApp '.substr($senderPhone, -4));

        $text = null;
        if (($msg['type'] ?? '') === 'text') {
            $text = $msg['text']['body'] ?? null;
        } elseif (($msg['type'] ?? '') === 'button') {
            $text = $msg['button']['text'] ?? null;
        } elseif (($msg['type'] ?? '') === 'interactive') {
            // Prefer ID for deterministic routing; fall back to title for plain-text flow
            $text = $msg['interactive']['button_reply']['id']
                ?? $msg['interactive']['list_reply']['id']
                ?? $msg['interactive']['button_reply']['title']
                ?? $msg['interactive']['list_reply']['title']
                ?? null;
        }

        if (! $senderPhone) {
            return null;
        }

        $media = [];
        if (isset($msg['image'])) {
            if ($channel) {
                $url = app(MediaDownloadService::class)->downloadWhatsappMedia($channel, $msg['image']['id'], $msg['image']['mime_type'] ?? 'image/jpeg');
                if ($url) {
                    $media[] = ['type' => 'image', 'url' => $url];
                }
            }
        } elseif (isset($msg['audio']) || isset($msg['voice'])) {
            $audioMsg = $msg['audio'] ?? $msg['voice'];
            if ($channel) {
                $url = app(MediaDownloadService::class)->downloadWhatsappMedia($channel, $audioMsg['id'], $audioMsg['mime_type'] ?? 'audio/ogg');
                if ($url) {
                    $media[] = ['type' => 'audio', 'url' => $url];
                }
            }
        }

        return $this->processInbound(
            business: $business,
            channelType: ChannelType::Whatsapp,
            channelUserId: $senderPhone,
            senderName: $contactName,
            username: null,
            platformMessageId: $platformMsgId,
            content: $text,
            media: empty($media) ? null : $media,
            phone: $senderPhone
        );
    }

    /**
     * Ingest an incoming message from WhatsApp Web (QR Scan Gateway).
     */
    public function ingestWhatsappQr(Business $business, array $payload): ?Message
    {
        // ── Human Owner Takeover Detection ──────────────────────────────────
        // When the business owner sends a message directly from the WhatsApp app,
        // the gateway sends event = 'owner_message' with 'to' = customer phone.
        if (($payload['event'] ?? '') === 'owner_message' || ! empty($payload['to'])) {
            $customerPhone = (string) ($payload['to'] ?? '');
            if (! $customerPhone) {
                return null;
            }

            $platformMsgId = (string) ($payload['message_id'] ?? uniqid('wa_owner_'));

            // Idempotency: Ignore if this owner message was already processed
            if ($platformMsgId && Message::where('channel_message_id', $platformMsgId)->exists()) {
                return null;
            }

            // Find customer identity
            $identity = CustomerChannelIdentity::where('channel', ChannelType::WhatsappWeb)
                ->where('channel_user_id', $customerPhone)
                ->first();

            $customer = $identity?->customer;
            if (! $customer) {
                $customer = Customer::where('business_id', $business->id)
                    ->where('phone', $customerPhone)
                    ->first();
            }

            if (! $customer) {
                return null;
            }

            $conversation = Conversation::where('customer_id', $customer->id)
                ->where('business_id', $business->id)
                ->where('channel', ChannelType::WhatsappWeb)
                ->latest()
                ->first();

            if (! $conversation) {
                return null;
            }

            $cacheKey = "takeover_cooldown_conv_{$conversation->id}";
            if (! Cache::has($cacheKey)) {
                Cache::put($cacheKey, true, 60);
                $conversation->update([
                    'handler' => 'human',
                    'status' => ConversationState::HumanHandling,
                    'human_last_replied_at' => now(),
                    'last_message_at' => now(),
                ]);
            } else {
                $conversation->update([
                    'human_last_replied_at' => now(),
                    'last_message_at' => now(),
                ]);
            }

            Log::info("[HumanTakeover] Owner sent message from WhatsApp app to conversation #{$conversation->id} ({$customerPhone}). AI paused for at least 3 minutes.");

            $text = (string) ($payload['text'] ?? '');
            if ($text) {
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'direction' => 'outbound',
                    'sender_type' => 'human',
                    'channel_message_id' => $platformMsgId,
                    'content' => $text,
                    'status' => 'sent',
                ]);

                try {
                    broadcast(new MessageReceived($message))->toOthers();
                } catch (\Throwable $e) {
                }

                return $message;
            }

            return null;
        }

        $senderPhone = (string) ($payload['from'] ?? '');
        $senderName = (string) ($payload['name'] ?? ('WhatsApp '.substr($senderPhone, -4)));
        $text = (string) ($payload['text'] ?? '');
        $platformMsgId = (string) ($payload['message_id'] ?? uniqid('waqr_'));

        if (! $senderPhone || ! $text) {
            return null;
        }

        return $this->processInbound(
            business: $business,
            channelType: ChannelType::WhatsappWeb,
            channelUserId: $senderPhone,
            senderName: $senderName,
            username: null,
            platformMessageId: $platformMsgId,
            content: $text,
            media: null,
            phone: $senderPhone
        );
    }

    /**
     * Ingest an incoming message from Facebook Messenger.
     */
    public function ingestMessenger(Business $business, array $payload): ?Message
    {
        $entry = $payload['entry'][0] ?? null;
        $messaging = $entry['messaging'][0] ?? null;

        if (! $messaging || ! isset($messaging['message'])) {
            return null;
        }

        $senderId = (string) ($messaging['sender']['id'] ?? '');
        $msgData = $messaging['message'];
        $platformMsgId = (string) ($msgData['mid'] ?? uniqid('fb_'));
        $text = $msgData['text'] ?? null;

        if (! $senderId) {
            return null;
        }

        return $this->processInbound(
            business: $business,
            channelType: ChannelType::Messenger,
            channelUserId: $senderId,
            senderName: 'Messenger Customer '.substr($senderId, -4),
            username: null,
            platformMessageId: $platformMsgId,
            content: $text,
            media: null
        );
    }

    /**
     * Core resolution and storage for any omnichannel inbound message.
     */
    public function processInbound(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $senderName,
        ?string $username,
        string $platformMessageId,
        ?string $content,
        ?array $media = null,
        ?string $phone = null
    ): ?Message {
        // Idempotency: skip if already ingested
        if (Message::where('channel_message_id', $platformMessageId)->exists()) {
            return null;
        }

        $channel = BusinessChannel::where('business_id', $business->id)
            ->where('channel', $channelType)
            ->first();

        if (! $channel) {
            Log::warning("No active {$channelType->value} channel found for business {$business->id}");

            return null;
        }

        return DB::transaction(function () use (
            $business,
            $channel,
            $channelType,
            $channelUserId,
            $senderName,
            $username,
            $platformMessageId,
            $content,
            $media,
            $phone
        ) {
            // Find existing customer identity or create
            $identity = CustomerChannelIdentity::where('channel', $channelType)
                ->where('channel_user_id', $channelUserId)
                ->first();

            if ($identity) {
                // Eagerly load; relationship may return null if customer was soft-deleted
                $customer = $identity->customer ?? Customer::withTrashed()->find($identity->customer_id);

                if (! $customer) {
                    // Orphaned identity — re-create the customer record
                    $customer = Customer::create([
                        'business_id' => $business->id,
                        'name' => $senderName,
                        'phone' => $phone,
                        'lead_status' => 'returning',
                        'last_contacted_at' => now(),
                    ]);
                    $identity->update(['customer_id' => $customer->id]);
                } elseif ($customer->trashed()) {
                    $customer->restore();
                }
            } else {
                $customer = Customer::create([
                    'business_id' => $business->id,
                    'name' => $senderName,
                    'phone' => $phone,
                    'lead_status' => 'new',
                    'last_contacted_at' => now(),
                ]);

                CustomerChannelIdentity::create([
                    'customer_id' => $customer->id,
                    'channel' => $channelType,
                    'channel_user_id' => $channelUserId,
                    'channel_username' => $username,
                ]);
            }

            // Find or create active conversation
            $conversation = Conversation::where('customer_id', $customer->id)
                ->where('business_id', $business->id)
                ->where('channel', $channelType)
                ->whereIn('status', [
                    ConversationState::New,
                    ConversationState::AiHandling,
                    ConversationState::HumanHandling,
                    ConversationState::WaitingForCustomer,
                    ConversationState::WaitingForBusiness,
                    ConversationState::Escalated,
                ])
                ->latest()
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'business_id' => $business->id,
                    'customer_id' => $customer->id,
                    'business_channel_id' => $channel->id,
                    'channel' => $channelType,
                    'status' => ConversationState::New,
                    'handler' => 'ai',
                    'priority' => 'normal',
                    'last_message_at' => now(),
                ]);
            } else {
                $conversation->update(['last_message_at' => now()]);
            }

            // Store message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'channel_message_id' => $platformMessageId,
                'content' => $content,
                'media' => $media,
                'status' => 'delivered',
            ]);

            $trimmed = strtolower(trim($content ?? ''));

            // Admin Command: Check Stock, Restock, or Upload Product via Chat
            $chatAdminOps = app(ChatAdminOpsService::class);
            if ($chatAdminOps->isCommand($content)) {
                $adminReply = $chatAdminOps->handle($business, $channelType, $channelUserId, $content, $media);
                if ($adminReply) {
                    $conversation->update([
                        'status' => ConversationState::Resolved,
                        'handler' => 'human',
                    ]);

                    return $message;
                }
            }

            // Command: Pause AI
            if ($trimmed === '/pause' || $trimmed === '/human') {
                $conversation->update(['handler' => 'human', 'status' => ConversationState::HumanHandling]);
                app(ChannelSender::class)->send($conversation, "⏸️ *AI Assistant Paused.*\nA human team member will assist you shortly.", null, 'ai');

                return $message;
            }

            // Command: Resume AI
            if ($trimmed === '/resume' || $trimmed === '/bot') {
                $conversation->update(['handler' => 'ai', 'status' => ConversationState::AiHandling]);
                app(ChannelSender::class)->send($conversation, "🤖 *AI Assistant Resumed.*\nHow can I help you today? You can browse our store catalog, add items to cart, or track your orders!", null, 'ai');

                return $message;
            }

            // Command: Start or Catalog Menu (Telegram & WhatsApp)
            if (in_array($trimmed, ['/start', '/catalog', '/menu', 'menu', 'hi', 'hello'])) {
                app(InteractiveMenuHandler::class)->showCatalog($conversation);

                return $message;
            }

            // Callback / Interactive Action (Telegram callback_query or WhatsApp interactive reply ID)
            if (str_starts_with($trimmed, 'action=')) {
                app(InteractiveMenuHandler::class)->handleCallback($conversation, $message, $trimmed);

                return $message;
            }

            // In-Chat Tracking Lookup: e.g. "track TRK-84920" or "TRK-84920"
            if (preg_match('/\b(TRK-[A-F0-9]{6})\b/i', $content ?? '', $matches)) {
                $orderService = new OrderFulfillmentService;
                $trackingResult = $orderService->lookupTracking($matches[1], $business);
                if ($trackingResult) {
                    app(ChannelSender::class)->send($conversation, $trackingResult, null, 'ai');

                    return $message;
                }
            }

            // Dispatch AI auto-response (ONLY if conversation is on AI autopilot)
            // Auto-resume AI after 3 minutes of human inactivity
            $humanLastReplied = $conversation->human_last_replied_at;
            $humanIsActive = $humanLastReplied && $humanLastReplied->gt(now()->subMinutes(3));

            if ($humanIsActive) {
                Log::info("[HumanTakeover] AI suppressed for conversation #{$conversation->id} — owner replied {$humanLastReplied->diffForHumans()}. AI will resume after 3 minutes of silence.");
            } else {
                if ($conversation->handler === 'human' && ! $humanIsActive && $humanLastReplied) {
                    // 3-minute window expired — quietly restore AI
                    $conversation->update([
                        'handler' => 'ai',
                        'status' => ConversationState::AiHandling,
                        'human_last_replied_at' => null,
                    ]);
                    $conversation->refresh();
                    Log::info("[HumanTakeover] 3-minute owner silence window expired for conversation #{$conversation->id}. AI resumed.");
                }

                if ($content && $conversation->handler === 'ai') {
                    $this->aiOrchestrator->handle($conversation, $message);
                }
            }

            // Fire automation workflows listening for new_message
            AutomationTriggerDispatcher::dispatch('new_message', [
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'message_id' => $message->id,
                'channel' => $channelType->value,
                'content' => $content,
            ], $business);

            return $message;
        });
    }
}
