<?php

namespace App\Enums;

enum ChannelType: string
{
    case Telegram = 'telegram';
    case Whatsapp = 'whatsapp';
    case WhatsappWeb = 'whatsapp_web';
    case Messenger = 'messenger';
    case Sms = 'sms';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::Whatsapp => 'WhatsApp Cloud API',
            self::WhatsappWeb => 'WhatsApp Web (QR Scan)',
            self::Messenger => 'Messenger',
            self::Sms => 'SMS',
            self::Email => 'Email',
        };
    }
}
