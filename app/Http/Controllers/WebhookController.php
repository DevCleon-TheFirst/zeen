<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Jobs\ProcessInboundMessageJob;
use App\Models\Business;
use App\Models\BusinessChannel;
use App\Models\Message;
use App\Services\Channels\MessageIngestionService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected MessageIngestionService $ingestionService,
    ) {}

    /**
     * Handle incoming Telegram webhook.
     */
    public function telegram(Request $request, Business $business): JsonResponse
    {
        try {
            TenantContext::set($business);
            $payload = $request->all();
            ProcessInboundMessageJob::dispatch('telegram', $business, $payload);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error: '.$e->getMessage());

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Verify Meta Webhook (WhatsApp / Messenger).
     */
    public function verifyMeta(Request $request, Business $business, string $channel): Response
    {
        TenantContext::set($business);

        $mode = $request->input('hub.mode') ?? $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->input('hub.verify_token') ?? $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->input('hub.challenge') ?? $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $channelType = ChannelType::tryFrom($channel);
        if (! $channelType) {
            return response('Invalid channel', 400);
        }

        $businessChannel = BusinessChannel::withoutGlobalScope('tenant')
            ->where('business_id', $business->id)
            ->where('channel', $channelType)
            ->first();

        $configuredToken = $businessChannel?->credentials['verify_token'] ?? null;

        if ($mode === 'subscribe' && $token && ($token === $configuredToken || $token === $businessChannel?->webhook_secret)) {
            return response((string) $challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming WhatsApp webhook.
     */
    public function whatsapp(Request $request, Business $business): JsonResponse
    {
        try {
            TenantContext::set($business);
            $payload = $request->all();
            ProcessInboundMessageJob::dispatch('whatsapp', $business, $payload);

            return response()->json(['status' => 'EVENT_RECEIVED']);
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook error: '.$e->getMessage());

            return response()->json(['status' => 'ERROR', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle incoming WhatsApp Web QR webhook.
     */
    public function whatsappQr(Request $request, Business $business): JsonResponse
    {
        try {
            TenantContext::set($business);
            $payload = $request->all();
            ProcessInboundMessageJob::dispatch('whatsapp_web', $business, $payload);

            return response()->json(['status' => 'EVENT_RECEIVED']);
        } catch (\Throwable $e) {
            Log::error('WhatsApp Web webhook error: '.$e->getMessage());

            return response()->json(['status' => 'ERROR', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle delivery receipt (ACK) from the WhatsApp Web gateway.
     * The gateway calls this when a sent message is delivered or read.
     */
    public function whatsappQrAck(Request $request, Business $business): JsonResponse
    {
        $messageId = $request->input('message_id');
        $status = $request->input('status'); // 'delivered' | 'read'

        if (! $messageId || ! in_array($status, ['delivered', 'read'])) {
            return response()->json(['ok' => false], 400);
        }

        Message::withoutGlobalScope('tenant')
            ->where('channel_message_id', $messageId)
            ->where('status', '!=', 'read') // Don't downgrade read → delivered
            ->update(['status' => $status]);

        return response()->json(['ok' => true]);
    }

    /**
     * Handle incoming Messenger webhook.
     */
    public function messenger(Request $request, Business $business): JsonResponse
    {
        try {
            TenantContext::set($business);
            $payload = $request->all();
            ProcessInboundMessageJob::dispatch('messenger', $business, $payload);

            return response()->json(['status' => 'EVENT_RECEIVED']);
        } catch (\Throwable $e) {
            Log::error('Messenger webhook error: '.$e->getMessage());

            return response()->json(['status' => 'ERROR', 'error' => $e->getMessage()], 500);
        }
    }
}
