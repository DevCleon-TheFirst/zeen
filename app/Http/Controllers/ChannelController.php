<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Models\BusinessChannel;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function index(): Response
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $channels = BusinessChannel::where('business_id', $business->id)->get()->keyBy(fn ($c) => $c->channel->value);

        $appUrl = config('app.url');

        $channelDefinitions = [
            [
                'type' => 'whatsapp_web',
                'name' => 'WhatsApp Web (QR Scan)',
                'description' => 'Link any WhatsApp account by scanning a QR Code. 100% Free unlimited automated messaging.',
                'icon' => 'qr',
                'color' => '#128C7E',
                'webhook_url' => "{$appUrl}/api/webhooks/whatsapp-qr/{$business->id}",
                'fields' => [],
                'is_qr_mode' => true,
                'channel_data' => $channels->get('whatsapp_web') ? [
                    'id' => $channels['whatsapp_web']->id,
                    'is_active' => $channels['whatsapp_web']->is_active,
                    'connected_at' => $channels['whatsapp_web']->connected_at?->toIso8601String(),
                    'phone' => $channels['whatsapp_web']->credentials['phone'] ?? null,
                ] : null,
            ],
            [
                'type' => 'whatsapp',
                'name' => 'WhatsApp Cloud API',
                'description' => 'Official Meta Cloud API for automated WhatsApp 2-way business messaging.',
                'icon' => 'whatsapp',
                'color' => '#25D366',
                'webhook_url' => "{$appUrl}/api/webhooks/whatsapp/{$business->id}",
                'fields' => [
                    ['key' => 'phone_number_id', 'label' => 'Phone Number ID', 'type' => 'text', 'placeholder' => 'e.g. 1048291049281'],
                    ['key' => 'access_token', 'label' => 'System User Access Token', 'type' => 'password', 'placeholder' => 'EAAG...'],
                    ['key' => 'verify_token', 'label' => 'Webhook Verify Token', 'type' => 'text', 'placeholder' => 'Custom secure token'],
                ],
                'channel_data' => $channels->get('whatsapp') ? [
                    'id' => $channels['whatsapp']->id,
                    'is_active' => $channels['whatsapp']->is_active,
                    'connected_at' => $channels['whatsapp']->connected_at?->toIso8601String(),
                    'phone_number_id' => $channels['whatsapp']->credentials['phone_number_id'] ?? '',
                    'has_token' => ! empty($channels['whatsapp']->credentials['access_token']),
                    'verify_token' => $channels['whatsapp']->credentials['verify_token'] ?? '',
                ] : null,
            ],
            [
                'type' => 'telegram',
                'name' => 'Telegram Bot',
                'description' => 'Connect via BotFather token for instant Telegram messaging & automation.',
                'icon' => 'telegram',
                'color' => '#229ED9',
                'webhook_url' => "{$appUrl}/api/webhooks/telegram/{$business->id}",
                'fields' => [
                    ['key' => 'bot_token', 'label' => 'Bot Token (from @BotFather)', 'type' => 'password', 'placeholder' => '123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ'],
                ],
                'channel_data' => $channels->get('telegram') ? [
                    'id' => $channels['telegram']->id,
                    'is_active' => $channels['telegram']->is_active,
                    'connected_at' => $channels['telegram']->connected_at?->toIso8601String(),
                    'has_token' => ! empty($channels['telegram']->credentials['bot_token']),
                    'bot_username' => $channels['telegram']->credentials['bot_username'] ?? null,
                ] : null,
            ],
            [
                'type' => 'messenger',
                'name' => 'Facebook Messenger',
                'description' => 'Connect Facebook Page to automate Messenger inquiries.',
                'icon' => 'messenger',
                'color' => '#006AFF',
                'webhook_url' => "{$appUrl}/api/webhooks/messenger/{$business->id}",
                'fields' => [
                    ['key' => 'page_id', 'label' => 'Facebook Page ID', 'type' => 'text', 'placeholder' => 'e.g. 1092837461928'],
                    ['key' => 'page_access_token', 'label' => 'Page Access Token', 'type' => 'password', 'placeholder' => 'EAA...'],
                    ['key' => 'verify_token', 'label' => 'Webhook Verify Token', 'type' => 'text', 'placeholder' => 'Custom secure token'],
                ],
                'channel_data' => $channels->get('messenger') ? [
                    'id' => $channels['messenger']->id,
                    'is_active' => $channels['messenger']->is_active,
                    'connected_at' => $channels['messenger']->connected_at?->toIso8601String(),
                    'page_id' => $channels['messenger']->credentials['page_id'] ?? '',
                    'has_token' => ! empty($channels['messenger']->credentials['page_access_token']),
                    'verify_token' => $channels['messenger']->credentials['verify_token'] ?? '',
                ] : null,
            ],
            [
                'type' => 'email',
                'name' => 'Email (SMTP Outbound)',
                'description' => 'Send transactional and automated emails via your custom SMTP server (SendGrid, Mailgun, AWS SES, Gmail).',
                'icon' => 'email',
                'color' => '#EA4335',
                'webhook_url' => "{$appUrl}/api/webhooks/automation/{$business->id}/email",
                'fields' => [
                    ['key' => 'smtp_host', 'label' => 'SMTP Host', 'type' => 'text', 'placeholder' => 'smtp.mailgun.org'],
                    ['key' => 'smtp_port', 'label' => 'SMTP Port', 'type' => 'text', 'placeholder' => '587'],
                    ['key' => 'smtp_username', 'label' => 'SMTP Username', 'type' => 'text', 'placeholder' => 'postmaster@yourdomain.com'],
                    ['key' => 'smtp_password', 'label' => 'SMTP Password', 'type' => 'password', 'placeholder' => '••••••••'],
                    ['key' => 'smtp_encryption', 'label' => 'Encryption (tls/ssl/none)', 'type' => 'text', 'placeholder' => 'tls'],
                    ['key' => 'from_address', 'label' => 'From Email Address', 'type' => 'text', 'placeholder' => 'hello@yourdomain.com'],
                    ['key' => 'from_name', 'label' => 'From Name', 'type' => 'text', 'placeholder' => 'Your Business Name'],
                ],
                'channel_data' => $channels->get('email') ? [
                    'id' => $channels['email']->id,
                    'is_active' => $channels['email']->is_active,
                    'connected_at' => $channels['email']->connected_at?->toIso8601String(),
                    'smtp_host' => $channels['email']->credentials['smtp_host'] ?? '',
                    'smtp_port' => $channels['email']->credentials['smtp_port'] ?? '587',
                    'smtp_username' => $channels['email']->credentials['smtp_username'] ?? '',
                    'has_token' => ! empty($channels['email']->credentials['smtp_password']),
                    'smtp_encryption' => $channels['email']->credentials['smtp_encryption'] ?? 'tls',
                    'from_address' => $channels['email']->credentials['from_address'] ?? '',
                    'from_name' => $channels['email']->credentials['from_name'] ?? '',
                ] : null,
            ],
        ];

        return Inertia::render('Channels/Index', [
            'channels' => $channelDefinitions,
        ]);
    }

    public function update(Request $request, string $channelType): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $type = ChannelType::tryFrom($channelType);
        abort_unless($type, 400, 'Invalid channel type');

        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'credentials' => 'required|array',
        ]);

        $channel = BusinessChannel::firstOrNew([
            'business_id' => $business->id,
            'channel' => $type,
        ]);

        $existingCreds = $channel->credentials ?? [];
        $newCreds = $validated['credentials'];

        // Preserve secrets if empty input passed
        foreach ($newCreds as $key => $val) {
            if (empty($val) && ! empty($existingCreds[$key])) {
                $newCreds[$key] = $existingCreds[$key];
            }
        }

        $channel->credentials = $newCreds;
        $channel->is_active = $validated['is_active'];
        if ($validated['is_active'] && ! $channel->connected_at) {
            $channel->connected_at = now();
        }
        $channel->webhook_secret = $channel->webhook_secret ?: Str::random(32);
        $channel->webhook_url = config('app.url')."/api/webhooks/{$type->value}/{$business->id}";
        $channel->save();

        return redirect()->back()->with('success', ucfirst($channelType).' settings updated.');
    }

    public function testConnection(string $channelType): JsonResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $type = ChannelType::tryFrom($channelType);
        abort_unless($type, 400, 'Invalid channel type');

        $channel = BusinessChannel::where('business_id', $business->id)
            ->where('channel', $type)
            ->first();

        if (! $channel || empty($channel->credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Channel credentials not configured yet.',
            ], 422);
        }

        if ($type === ChannelType::Telegram) {
            $token = $channel->credentials['bot_token'] ?? null;
            if (! $token) {
                return response()->json(['success' => false, 'message' => 'No Telegram bot token found.'], 422);
            }

            try {
                $res = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");
                if ($res->successful() && $res->json('ok')) {
                    $botName = $res->json('result.username');
                    $creds = $channel->credentials;
                    $creds['bot_username'] = $botName;
                    $channel->credentials = $creds;
                    $channel->save();

                    return response()->json([
                        'success' => true,
                        'message' => "Successfully connected to Telegram Bot @{$botName}!",
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Telegram Bot error: '.($res->json('description') ?? 'Invalid token'),
                ], 400);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        if ($type === ChannelType::Whatsapp) {
            $phoneId = $channel->credentials['phone_number_id'] ?? null;
            $token = $channel->credentials['access_token'] ?? null;
            if (! $phoneId || ! $token) {
                return response()->json(['success' => false, 'message' => 'Phone ID and Access Token required.'], 422);
            }

            try {
                $res = Http::withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                    ->withToken($token)
                    ->timeout(20)
                    ->get("https://graph.facebook.com/v22.0/{$phoneId}");
                if ($res->successful()) {
                    $phone = $res->json('display_phone_number') ?? $phoneId;

                    return response()->json([
                        'success' => true,
                        'message' => "Successfully verified WhatsApp Phone: {$phone}!",
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Meta API Error: '.($res->json('error.message') ?? 'Verification failed'),
                ], 400);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        if ($type === ChannelType::Messenger) {
            $pageId = $channel->credentials['page_id'] ?? null;
            $token = $channel->credentials['page_access_token'] ?? null;
            if (! $pageId || ! $token) {
                return response()->json(['success' => false, 'message' => 'Page ID and Token required.'], 422);
            }

            try {
                $res = Http::withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                    ->withToken($token)
                    ->timeout(20)
                    ->get("https://graph.facebook.com/v22.0/{$pageId}");
                if ($res->successful()) {
                    $name = $res->json('name') ?? $pageId;

                    return response()->json([
                        'success' => true,
                        'message' => "Successfully verified Facebook Page: {$name}!",
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Messenger API Error: '.($res->json('error.message') ?? 'Verification failed'),
                ], 400);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        if ($type === ChannelType::Email) {
            $host = $channel->credentials['smtp_host'] ?? null;
            $port = (int) ($channel->credentials['smtp_port'] ?? 587);
            if (! $host) {
                return response()->json(['success' => false, 'message' => 'SMTP Host is required.'], 422);
            }

            try {
                $connection = @fsockopen($host, $port, $errno, $errstr, 5);
                if (is_resource($connection)) {
                    fclose($connection);

                    return response()->json([
                        'success' => true,
                        'message' => "Successfully reached SMTP server {$host}:{$port}!",
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => "Could not connect to {$host}:{$port} ({$errstr})",
                ], 400);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        return response()->json(['success' => true, 'message' => 'Channel configured.']);
    }

    public function getQrStatus(): JsonResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $res = null;
        try {
            $res = Http::timeout(3)->get("http://127.0.0.1:3001/status/{$business->id}");
        } catch (\Throwable $e) {
            // Auto-start node gateway if down
            @exec('node '.base_path('whatsapp-gateway/server.js').' > /dev/null 2>&1 &');
            sleep(1);
            try {
                $res = Http::timeout(5)->get("http://127.0.0.1:3001/status/{$business->id}");
            } catch (\Throwable $e2) {
                return response()->json([
                    'success' => false,
                    'status' => 'OFFLINE',
                    'message' => 'WhatsApp Gateway is initializing...',
                ]);
            }
        }

        if ($res && $res->successful()) {
            $data = $res->json();

            // If status is CONNECTED, update or save BusinessChannel
            if (($data['status'] ?? '') === 'CONNECTED' && ! empty($data['user'])) {
                $channel = BusinessChannel::firstOrNew([
                    'business_id' => $business->id,
                    'channel' => ChannelType::WhatsappWeb,
                ]);
                $channel->is_active = true;
                $channel->connected_at = $channel->connected_at ?: now();
                $channel->credentials = [
                    'phone' => $data['user'],
                    'mode' => 'qr',
                ];
                $channel->webhook_url = config('app.url')."/api/webhooks/whatsapp-qr/{$business->id}";
                $channel->save();
            }

            return response()->json([
                'success' => true,
                'status' => $data['status'] ?? 'DISCONNECTED',
                'qr' => $data['qr'] ?? null,
                'user' => $data['user'] ?? null,
            ]);
        }

        return response()->json(['status' => 'DISCONNECTED']);
    }

    public function disconnectQr(): JsonResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        try {
            Http::timeout(5)->post("http://127.0.0.1:3001/disconnect/{$business->id}");
        } catch (\Throwable $e) {
        }

        BusinessChannel::where('business_id', $business->id)
            ->where('channel', ChannelType::WhatsappWeb)
            ->delete();

        return response()->json(['success' => true, 'message' => 'WhatsApp Web disconnected.']);
    }

    public function requestPairingCode(Request $request): JsonResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $validated = $request->validate([
            'phone' => 'required|string',
        ]);

        try {
            $res = null;
            try {
                $res = Http::timeout(5)->post("http://127.0.0.1:3001/pairing-code/{$business->id}", [
                    'phone' => $validated['phone'],
                ]);
            } catch (\Throwable $e) {
                @exec('node '.base_path('whatsapp-gateway/server.js').' > /dev/null 2>&1 &');
                sleep(2);
                $res = Http::timeout(10)->post("http://127.0.0.1:3001/pairing-code/{$business->id}", [
                    'phone' => $validated['phone'],
                ]);
            }

            if ($res->successful() && $res->json('success')) {
                return response()->json([
                    'success' => true,
                    'code' => $res->json('code'),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $res->json('error') ?? 'Could not generate pairing code.',
            ], 400);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
