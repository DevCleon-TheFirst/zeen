<?php

namespace App\Services\Channels;

use App\Models\BusinessChannel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailSenderService
{
    /**
     * Send an email, optionally using custom SMTP credentials from a BusinessChannel.
     *
     * @param  array<string, string>  $options  Extra headers, cc, bcc, etc.
     */
    public function send(
        string $to,
        string $subject,
        string $body,
        ?BusinessChannel $channel = null,
        array $options = []
    ): bool {
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning("EmailSenderService: Invalid recipient email '{$to}'");

            return false;
        }

        try {
            if ($channel && ! empty($channel->credentials['smtp_host'])) {
                return $this->sendViaCustomSmtp($to, $subject, $body, $channel, $options);
            }

            // Fallback to Laravel default mailer
            Mail::raw($body, function ($message) use ($to, $subject, $options) {
                $message->to($to)->subject($subject);

                if (! empty($options['from_address'])) {
                    $message->from($options['from_address'], $options['from_name'] ?? null);
                }
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('EmailSenderService failed: '.$e->getMessage(), [
                'to' => $to,
                'subject' => $subject,
                'channel_id' => $channel?->id,
            ]);

            return false;
        }
    }

    /**
     * Dynamically configure and send using channel-specific SMTP credentials.
     */
    protected function sendViaCustomSmtp(
        string $to,
        string $subject,
        string $body,
        BusinessChannel $channel,
        array $options
    ): bool {
        $creds = $channel->credentials;
        $host = $creds['smtp_host'] ?? '';
        $port = (int) ($creds['smtp_port'] ?? 587);
        $username = $creds['smtp_username'] ?? '';
        $password = $creds['smtp_password'] ?? '';
        $encryption = $creds['smtp_encryption'] ?? ($port === 465 ? 'ssl' : 'tls');
        $fromAddress = $creds['from_address'] ?? config('mail.from.address', 'hello@example.com');
        $fromName = $creds['from_name'] ?? $channel->business?->name ?? config('mail.from.name', 'Support');

        $mailerName = 'smtp_channel_'.$channel->id;

        Config::set("mail.mailers.{$mailerName}", [
            'transport' => 'smtp',
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption === 'none' ? null : $encryption,
            'username' => $username,
            'password' => $password,
            'timeout' => 15,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ]);

        Mail::mailer($mailerName)->raw($body, function ($message) use ($to, $subject, $fromAddress, $fromName) {
            $message->to($to)
                ->subject($subject)
                ->from($fromAddress, $fromName);
        });

        return true;
    }
}
