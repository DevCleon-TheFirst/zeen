<?php

namespace App\Services\Channels;

use App\Models\BusinessChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaDownloadService
{
    /**
     * Download media from WhatsApp Cloud API.
     */
    public function downloadWhatsappMedia(BusinessChannel $channel, string $mediaId, string $mimeType): ?string
    {
        $token = $channel->credentials['access_token'] ?? null;
        if (! $token) {
            return null;
        }

        try {
            // Step 1: Get media URL
            $response = Http::withToken($token)->get("https://graph.facebook.com/v19.0/{$mediaId}");
            if (! $response->successful()) {
                Log::error("Failed to get WhatsApp media URL for $mediaId", $response->json());

                return null;
            }

            $mediaUrl = $response->json('url');
            if (! $mediaUrl) {
                return null;
            }

            // Step 2: Download binary
            $mediaResponse = Http::withToken($token)->get($mediaUrl);
            if (! $mediaResponse->successful()) {
                Log::error("Failed to download WhatsApp media from $mediaUrl");

                return null;
            }

            return $this->storeMedia($mediaResponse->body(), $mimeType);
        } catch (\Throwable $e) {
            Log::error('Exception downloading WhatsApp media: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Download media from Telegram.
     */
    public function downloadTelegramMedia(BusinessChannel $channel, string $fileId, string $mimeType): ?string
    {
        $token = $channel->credentials['bot_token'] ?? null;
        if (! $token) {
            return null;
        }

        try {
            // Step 1: Get file path
            $response = Http::get("https://api.telegram.org/bot{$token}/getFile", [
                'file_id' => $fileId,
            ]);
            if (! $response->successful()) {
                Log::error("Failed to get Telegram file path for $fileId", $response->json());

                return null;
            }

            $filePath = $response->json('result.file_path');
            if (! $filePath) {
                return null;
            }

            // Step 2: Download binary
            $fileUrl = "https://api.telegram.org/file/bot{$token}/{$filePath}";
            $mediaResponse = Http::get($fileUrl);
            if (! $mediaResponse->successful()) {
                Log::error("Failed to download Telegram media from $fileUrl");

                return null;
            }

            return $this->storeMedia($mediaResponse->body(), $mimeType);
        } catch (\Throwable $e) {
            Log::error('Exception downloading Telegram media: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Store the media securely and return a public URL.
     */
    protected function storeMedia(string $content, string $mimeType): string
    {
        $extension = explode('/', $mimeType)[1] ?? 'bin';
        // Handle common variations
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (str_contains($extension, 'audio')) {
            $extension = 'oga';
        }

        $filename = 'media/'.date('Y/m/d').'/'.Str::random(40).'.'.$extension;

        Storage::disk('public')->put($filename, $content);

        return Storage::disk('public')->url($filename);
    }
}
