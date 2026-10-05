<?php

namespace App\Jobs;

use App\Models\Business;
use App\Services\Channels\MessageIngestionService;
use App\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessInboundMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $channel,
        public Business $business,
        public array $payload
    ) {}

    public function handle(MessageIngestionService $ingestionService): void
    {
        TenantContext::set($this->business);

        try {
            if ($this->channel === 'telegram') {
                $ingestionService->ingestTelegram($this->business, $this->payload);
            } elseif ($this->channel === 'whatsapp') {
                $ingestionService->ingestWhatsapp($this->business, $this->payload);
            } elseif ($this->channel === 'whatsapp_web') {
                $ingestionService->ingestWhatsappQr($this->business, $this->payload);
            } elseif ($this->channel === 'messenger') {
                $ingestionService->ingestMessenger($this->business, $this->payload);
            }
        } catch (\Throwable $e) {
            Log::error("ProcessInboundMessageJob failed for {$this->channel}: ".$e->getMessage());
            throw $e;
        }
    }
}
