<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Models\BusinessChannel;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Response;

class SystemSetupController extends Controller
{
    public function index(): Response
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $appUrl = config('app.url');

        // Resolve all channels
        $channels = BusinessChannel::where('business_id', $business->id)
            ->get()
            ->keyBy(fn ($c) => $c->channel->value);

        // Health checks
        $health = $this->systemHealth();

        // Build channel webhook info
        $channelWebhooks = [
            [
                'type' => 'telegram',
                'label' => 'Telegram Bot',
                'icon' => 'telegram',
                'connected' => isset($channels['telegram']) && ! empty($channels['telegram']->credentials['bot_token']),
                'webhook_url' => "{$appUrl}/api/webhooks/telegram/{$business->id}",
                'registered' => $channels['telegram']?->credentials['webhook_registered'] ?? false,
                'bot_username' => $channels['telegram']?->credentials['bot_username'] ?? null,
            ],
            [
                'type' => 'whatsapp',
                'label' => 'WhatsApp Cloud',
                'icon' => 'whatsapp',
                'connected' => isset($channels['whatsapp']) && ! empty($channels['whatsapp']->credentials['access_token']),
                'webhook_url' => "{$appUrl}/api/webhooks/whatsapp/{$business->id}",
                'verify_token' => $channels['whatsapp']?->credentials['verify_token'] ?? null,
            ],
            [
                'type' => 'messenger',
                'label' => 'Facebook Messenger',
                'icon' => 'messenger',
                'connected' => isset($channels['messenger']) && ! empty($channels['messenger']->credentials['page_access_token']),
                'webhook_url' => "{$appUrl}/api/webhooks/messenger/{$business->id}",
                'verify_token' => $channels['messenger']?->credentials['verify_token'] ?? null,
            ],
        ];

        return Inertia::render('Settings/Setup', [
            'health' => $health,
            'app_url' => $appUrl,
            'channel_webhooks' => $channelWebhooks,
            'queue_connection' => config('queue.default'),
            'inbound_automation_url' => "{$appUrl}/api/webhooks/automation/{ID}/{SECRET}",
        ]);
    }

    /** One-click Telegram webhook registration. */
    public function registerTelegramWebhook(Request $request): JsonResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $request->validate(['app_url' => 'sometimes|url']);

        $appUrl = rtrim($request->input('app_url', config('app.url')), '/');

        $channel = BusinessChannel::where('business_id', $business->id)
            ->where('channel', ChannelType::Telegram)
            ->first();

        if (! $channel || empty($channel->credentials['bot_token'])) {
            return response()->json(['success' => false, 'message' => 'Telegram bot token not configured. Go to Connected Channels and add your token first.'], 422);
        }

        $token = $channel->credentials['bot_token'];
        $webhookUrl = "{$appUrl}/api/webhooks/telegram/{$business->id}";

        try {
            $res = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $webhookUrl,
                'allowed_updates' => ['message', 'callback_query'],
            ]);

            if ($res->successful() && $res->json('ok')) {
                // Mark as registered
                $creds = $channel->credentials;
                $creds['webhook_registered'] = true;
                $creds['webhook_url_set'] = $webhookUrl;
                $channel->credentials = $creds;
                $channel->save();

                return response()->json([
                    'success' => true,
                    'message' => "✅ Telegram webhook registered! Messages to @{$channel->credentials['bot_username']} will now arrive in real-time.",
                    'webhook_url' => $webhookUrl,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Telegram error: '.($res->json('description') ?? 'Unknown error'),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Connection error: '.$e->getMessage()], 500);
        }
    }

    /** Check Telegram webhook status. */
    public function telegramWebhookInfo(): JsonResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $channel = BusinessChannel::where('business_id', $business->id)
            ->where('channel', ChannelType::Telegram)
            ->first();

        if (! $channel || empty($channel->credentials['bot_token'])) {
            return response()->json(['success' => false, 'message' => 'No Telegram token configured.'], 422);
        }

        $token = $channel->credentials['bot_token'];
        $res = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getWebhookInfo");

        return response()->json([
            'success' => true,
            'info' => $res->json('result'),
            'bot_username' => $channel->credentials['bot_username'] ?? null,
        ]);
    }

    /** Update APP_URL in .env and config. */
    public function updateAppUrl(Request $request): JsonResponse
    {
        $request->validate(['url' => 'required|url']);
        $url = rtrim($request->input('url'), '/');

        $envPath = base_path('.env');
        $contents = file_get_contents($envPath);
        $contents = preg_replace('/^APP_URL=.*/m', "APP_URL={$url}", $contents);
        file_put_contents($envPath, $contents);

        Artisan::call('config:clear');

        return response()->json(['success' => true, 'message' => "App URL updated to {$url}. You can now register webhooks."]);
    }

    /** Live system health check. */
    public function health(): JsonResponse
    {
        return response()->json($this->systemHealth());
    }

    /** Run pending migrations. */
    public function migrate(): JsonResponse
    {
        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();

        return response()->json(['success' => true, 'output' => trim($output) ?: 'Nothing to migrate.']);
    }

    /** Clear all Laravel caches. */
    public function clearCaches(): JsonResponse
    {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        return response()->json(['success' => true, 'message' => 'All caches cleared.']);
    }

    private function systemHealth(): array
    {
        // DB
        try {
            DB::connection()->getPdo();
            $dbOk = true;
            $dbMsg = 'Connected';
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbMsg = $e->getMessage();
        }

        // Queue (check for pending jobs)
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $queueOk = true;
        } catch (\Throwable) {
            $pendingJobs = null;
            $failedJobs = null;
            $queueOk = false;
        }

        // Reverb / Broadcast
        $reverbHost = config('reverb.servers.reverb.host', 'localhost');
        $reverbPort = (int) config('reverb.servers.reverb.port', 8080);
        $reverbOk = false;
        try {
            $sock = @fsockopen($reverbHost, $reverbPort, $errno, $errstr, 1);
            if ($sock) {
                fclose($sock);
                $reverbOk = true;
            }
        } catch (\Throwable) {
        }

        // Storage link
        $storageLinked = file_exists(public_path('storage'));

        // Queue driver
        $queueDriver = config('queue.default');

        return [
            'database' => ['ok' => $dbOk, 'message' => $dbMsg],
            'queue' => [
                'ok' => $queueOk,
                'driver' => $queueDriver,
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
            ],
            'reverb' => ['ok' => $reverbOk, 'host' => $reverbHost, 'port' => $reverbPort],
            'storage_link' => ['ok' => $storageLinked],
            'app_url' => config('app.url'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
        ];
    }
}
