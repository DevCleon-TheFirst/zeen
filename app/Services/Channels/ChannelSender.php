<?php

namespace App\Services\Channels;

use App\Enums\ConversationState;
use App\Jobs\SendOutboundMessageJob;
use App\Models\BusinessChannel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChannelSender
{
    /**
     * Send an outbound message to a conversation on its active channel.
     */
    public function send(Conversation $conversation, string $text, ?User $sender = null, string $senderType = 'ai', ?array $media = null): Message
    {
        $channel = $conversation->businessChannel;
        $customer = $conversation->customer;
        $identity = $customer->identities()->where('channel', $conversation->channel)->first();
        $channelUserId = $identity?->channel_user_id ?? $customer->phone;

        if (! $channelUserId) {
            throw new \RuntimeException("No recipient address or channel identity found for customer on {$conversation->channel->value}");
        }

        // Save outbound message to history first
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => $senderType,
            'sender_id' => $sender?->id,
            'channel_message_id' => 'pending_'.uniqid(),
            'content' => $text,
            'media' => $media,
            'status' => 'pending',
        ]);

        $updateData = ['last_message_at' => now()];
        if ($senderType === 'human') {
            $updateData['handler'] = 'human';
            $updateData['status'] = ConversationState::HumanHandling;
            $updateData['human_last_replied_at'] = now();
        }
        $conversation->update($updateData);

        SendOutboundMessageJob::dispatch($message);

        return $message;
    }

    public function sendTelegram(BusinessChannel $channel, string $chatId, string $text, ?array $media = null, ?array $replyMarkup = null): ?string
    {
        $token = $channel->credentials['bot_token'] ?? null;
        if (! $token) {
            Log::warning('Cannot send Telegram message: missing bot_token');

            return null;
        }

        $mediaUrl = $media['url'] ?? $media[0]['url'] ?? null;
        $mediaType = $media['type'] ?? $media[0]['type'] ?? 'image';

        try {
            if ($mediaUrl && in_array($mediaType, ['image', 'photo', 'picture'])) {
                // Check if media URL points to a local file in storage
                $localPath = null;
                if (str_contains($mediaUrl, '/storage/catalog/')) {
                    $filename = basename(parse_url($mediaUrl, PHP_URL_PATH));
                    $candidate = storage_path('app/public/catalog/'.$filename);
                    if (file_exists($candidate)) {
                        $localPath = $candidate;
                    }
                }

                if ($localPath) {
                    $res = Http::timeout(25)
                        ->attach('photo', file_get_contents($localPath), basename($localPath))
                        ->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                            'chat_id' => $chatId,
                            'caption' => $text,
                            'parse_mode' => 'Markdown',
                        ]);
                } else {
                    $res = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                        'chat_id' => $chatId,
                        'photo' => $mediaUrl,
                        'caption' => $text,
                        'parse_mode' => 'Markdown',
                    ]);
                }
            } elseif ($mediaUrl && in_array($mediaType, ['video'])) {
                $res = Http::timeout(25)->post("https://api.telegram.org/bot{$token}/sendVideo", [
                    'chat_id' => $chatId,
                    'video' => $mediaUrl,
                    'caption' => $text,
                    'parse_mode' => 'Markdown',
                ]);
            } else {
                $res = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);

                if ($res->failed()) {
                    // Retry without markdown if markdown parsing failed
                    $res = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $text,
                    ]);
                }
            }

            return (string) $res->json('result.message_id');
        } catch (\Throwable $e) {
            Log::error('Telegram send error: '.$e->getMessage());

            return null;
        }
    }

    public function sendTelegramWithMarkup(BusinessChannel $channel, string $chatId, string $text, ?array $replyMarkup = null): ?string
    {
        $token = $channel->credentials['bot_token'] ?? null;
        if (! $token) {
            return null;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            $res = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

            return (string) $res->json('result.message_id');
        } catch (\Throwable $e) {
            Log::error('Telegram sendWithMarkup error: '.$e->getMessage());

            return null;
        }
    }

    public function sendWhatsapp(BusinessChannel $channel, string $toPhone, string $text, ?array $media = null): ?string
    {
        $phoneId = $channel->credentials['phone_number_id'] ?? null;
        $token = $channel->credentials['access_token'] ?? null;

        if (! $phoneId || ! $token) {
            Log::warning('Cannot send WhatsApp message: missing phone_number_id or token');

            return null;
        }

        $mediaUrl = $media['url'] ?? $media[0]['url'] ?? null;
        $mediaType = $media['type'] ?? $media[0]['type'] ?? 'image';

        try {
            if ($mediaUrl && in_array($mediaType, ['image', 'photo', 'picture'])) {
                // Detect local server files (can't be fetched by WhatsApp servers)
                // Upload directly to WhatsApp Media API to get a media_id
                $mediaId = $this->uploadWhatsappMedia($channel, $mediaUrl, $phoneId, $token);

                if ($mediaId) {
                    $payload = [
                        'messaging_product' => 'whatsapp',
                        'recipient_type' => 'individual',
                        'to' => $toPhone,
                        'type' => 'image',
                        'image' => [
                            'id' => $mediaId,
                            'caption' => $text,
                        ],
                    ];
                } else {
                    // Fallback: send text only if upload failed
                    Log::warning("WhatsApp: media upload failed for {$mediaUrl}, falling back to text.");
                    $payload = [
                        'messaging_product' => 'whatsapp',
                        'recipient_type' => 'individual',
                        'to' => $toPhone,
                        'type' => 'text',
                        'text' => ['preview_url' => false, 'body' => $text],
                    ];
                }
            } elseif ($mediaUrl && in_array($mediaType, ['video'])) {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $toPhone,
                    'type' => 'video',
                    'video' => [
                        'link' => $mediaUrl,
                        'caption' => $text,
                    ],
                ];
            } else {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $toPhone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $text,
                    ],
                ];
            }

            $res = Http::withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                ->withToken($token)
                ->timeout(20)
                ->post("https://graph.facebook.com/v22.0/{$phoneId}/messages", $payload);

            return (string) ($res->json('messages.0.id'));
        } catch (\Throwable $e) {
            Log::error('WhatsApp send error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Upload a local or remote image to the WhatsApp Media API and return the media_id.
     * WhatsApp cannot fetch images from localhost/private URLs, so we upload the bytes directly.
     */
    private function uploadWhatsappMedia(BusinessChannel $channel, string $mediaUrl, string $phoneId, string $token): ?string
    {
        // Resolve local file path from storage URL
        $localPath = null;
        if (str_contains($mediaUrl, '/storage/catalog/')) {
            $filename = basename(parse_url($mediaUrl, PHP_URL_PATH));
            $candidate = storage_path('app/public/catalog/'.$filename);
            if (file_exists($candidate)) {
                $localPath = $candidate;
            }
        } elseif (str_contains($mediaUrl, '/storage/')) {
            // Generic storage path resolution
            $parsed = parse_url($mediaUrl, PHP_URL_PATH);
            $relative = ltrim(str_replace('/storage/', '', $parsed), '/');
            $candidate = storage_path('app/public/'.$relative);
            if (file_exists($candidate)) {
                $localPath = $candidate;
            }
        }

        if (! $localPath) {
            // Not a local file — return null so caller falls back to link (may work if URL is public)
            return null;
        }

        try {
            $mime = mime_content_type($localPath) ?: 'image/jpeg';
            $filename = basename($localPath);

            $res = Http::withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                ->withToken($token)
                ->timeout(30)
                ->attach('file', file_get_contents($localPath), $filename, ['Content-Type' => $mime])
                ->post("https://graph.facebook.com/v22.0/{$phoneId}/media", [
                    'messaging_product' => 'whatsapp',
                    'type' => $mime,
                ]);

            $mediaId = $res->json('id');

            if ($mediaId) {
                Log::info("WhatsApp media uploaded: {$filename} → media_id={$mediaId}");

                return (string) $mediaId;
            }

            Log::warning('WhatsApp media upload returned no id: '.$res->body());

            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp media upload error: '.$e->getMessage());

            return null;
        }
    }

    public function sendWhatsappQr(BusinessChannel $channel, string $toPhone, string $text, ?array $media = null): ?string
    {
        try {
            $mediaUrl = $media['url'] ?? $media[0]['url'] ?? null;

            $payload = [
                'to' => $toPhone,
                'text' => $text,
            ];
            if ($mediaUrl) {
                $payload['mediaUrl'] = $mediaUrl;
            }

            $res = Http::timeout(15)->post("http://127.0.0.1:3001/send/{$channel->business_id}", $payload);

            if ($res->successful() && $res->json('success')) {
                return (string) $res->json('message_id');
            }

            // 422 = number not registered on WhatsApp
            if ($res->status() === 422) {
                Log::warning("WhatsApp QR: number {$toPhone} is not on WhatsApp — message not sent.");

                return null;
            }

            Log::error('WhatsApp QR send error: '.$res->body());

            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp QR send exception: '.$e->getMessage());

            return null;
        }
    }

    public function sendMessenger(BusinessChannel $channel, string $recipientId, string $text): ?string
    {
        $token = $channel->credentials['page_access_token'] ?? null;

        if (! $token) {
            Log::warning('Cannot send Messenger message: missing page_access_token');

            return null;
        }

        try {
            $res = Http::withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                ->withToken($token)
                ->timeout(20)
                ->post('https://graph.facebook.com/v22.0/me/messages', [
                    'recipient' => ['id' => $recipientId],
                    'message' => ['text' => $text],
                ]);

            return (string) ($res->json('message_id'));
        } catch (\Throwable $e) {
            Log::error('Messenger send error: '.$e->getMessage());

            return null;
        }
    }

    public function sendEmail(BusinessChannel $channel, string $toEmail, string $text, ?string $subject = null): ?string
    {
        $subject = $subject ?: 'New Message from '.($channel->business?->name ?? config('app.name'));
        $success = app(EmailSenderService::class)->send($toEmail, $subject, $text, $channel);

        return $success ? 'email_'.uniqid() : null;
    }
}
