<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Models\Message;
use App\Services\Channels\ChannelSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOutboundMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Message $message
    ) {}

    public function handle(ChannelSender $sender): void
    {
        $conversation = $this->message->conversation;
        $channel = $conversation->businessChannel;
        $customer = $conversation->customer;
        $identity = $customer->identities()->where('channel', $conversation->channel)->first();
        $channelUserId = $identity?->channel_user_id ?? $customer->phone;

        if (! $channelUserId) {
            $this->message->update(['status' => 'failed']);

            return;
        }

        $channelMessageId = null;

        try {
            if ($conversation->channel === ChannelType::Telegram) {
                $channelMessageId = $sender->sendTelegram($channel, $channelUserId, $this->message->content, $this->message->media);
            } elseif ($conversation->channel === ChannelType::Whatsapp) {
                $channelMessageId = $sender->sendWhatsapp($channel, $channelUserId, $this->message->content, $this->message->media);
            } elseif ($conversation->channel === ChannelType::WhatsappWeb) {
                $channelMessageId = $sender->sendWhatsappQr($channel, $channelUserId, $this->message->content, $this->message->media);
            } elseif ($conversation->channel === ChannelType::Messenger) {
                $channelMessageId = $sender->sendMessenger($channel, $channelUserId, $this->message->content);
            } elseif ($conversation->channel === ChannelType::Email) {
                $recipientEmail = $customer->email ?: $channelUserId;
                $channelMessageId = $sender->sendEmail($channel, $recipientEmail, $this->message->content);
            }

            $this->message->update([
                'channel_message_id' => $channelMessageId ?: ('out_'.uniqid()),
                'status' => $channelMessageId ? 'sent' : 'failed',
            ]);
        } catch (\Throwable $e) {
            Log::error('SendOutboundMessageJob failed: '.$e->getMessage());
            $this->message->update(['status' => 'failed']);
            throw $e;
        }
    }
}
