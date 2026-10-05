<?php

namespace App\Console\Commands;

use App\Enums\ChannelType;
use App\Jobs\ProcessInboundMessageJob;
use App\Models\Business;
use App\Models\BusinessChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {--once : Run once and exit} {--business= : Explicit business ID to poll for} {--all : Poll all active Telegram channels}';

    protected $description = 'Poll Telegram API for incoming messages (ideal for local development without webhooks)';

    public function handle(): int
    {
        // Collect which channels to poll
        $channelsQuery = BusinessChannel::where('channel', ChannelType::Telegram)
            ->where('is_active', true);

        if ($explicitId = $this->option('business')) {
            $channelsQuery->where('business_id', $explicitId);
        } elseif (! $this->option('all')) {
            // Default: only poll the first (or only) active Telegram channel
            $channelsQuery->limit(1);
        }

        $channels = $channelsQuery->with('business')->get();

        if ($channels->isEmpty()) {
            $this->error('No active Telegram channels found.');

            return self::FAILURE;
        }

        $this->info('🤖 Polling '.($channels->count() > 1 ? $channels->count().' Telegram channels' : '@'.($channels->first()->credentials['bot_username'] ?? 'Bot')).'...');
        if (! $this->option('once')) {
            $this->comment('Press Ctrl+C to stop.');
        }

        // Per-channel offset tracking
        $offsets = $channels->mapWithKeys(fn ($c) => [$c->id => 0])->toArray();

        do {
            foreach ($channels as $channel) {
                $token = $channel->credentials['bot_token'] ?? null;
                if (! $token) {
                    continue;
                }

                // Each channel always routes to its own business — no owner override
                $business = $channel->business ?? Business::find($channel->business_id);
                if (! $business) {
                    continue;
                }

                try {
                    $params = [
                        'timeout' => 5,
                        'allowed_updates' => json_encode(['message', 'callback_query']),
                    ];

                    if ($offsets[$channel->id] > 0) {
                        $params['offset'] = $offsets[$channel->id];
                    }

                    $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getUpdates", $params);

                    if ($response->successful() && $response->json('ok')) {
                        $updates = $response->json('result', []);

                        foreach ($updates as $update) {
                            $updateId = $update['update_id'];
                            $offsets[$channel->id] = $updateId + 1;

                            $sender = $update['message']['from']['first_name'] ?? 'User';
                            $text = $update['message']['text'] ?? '[Media/Other]';

                            $this->line("<info>[Telegram Inbound -> {$business->name} (#{$business->id})]</info> From: <comment>{$sender}</comment> - Message: <comment>{$text}</comment>");

                            ProcessInboundMessageJob::dispatch('telegram', $business, $update);
                        }
                    }
                } catch (\Throwable $e) {
                    $this->warn("Polling error [{$business->name}]: ".$e->getMessage());
                    sleep(2);
                }
            }

            if ($this->option('once')) {
                break;
            }

            usleep(500000); // 0.5s pause between polls
        } while (true);

        return self::SUCCESS;
    }
}
