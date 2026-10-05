<?php

namespace App\Services\Vouchers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\DigitalCode;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Generates, manages, and delivers digital codes.
 *
 * Supports two modes:
 *  1. Dynamic generation — creates a secure code on the fly.
 *  2. Pool claiming     — claims the next available pre-loaded code.
 */
class DigitalVoucherService
{
    // ─── Code Generation ───────────────────────────────────────────────────────

    /**
     * Dynamically generate a new digital code and persist it.
     */
    public function generateCode(
        Business $business,
        string $category = 'wifi_voucher',
        ?int $validityMinutes = 1440,
        string $prefix = '',
        array $metadata = [],
    ): DigitalCode {
        $prefix = strtoupper($prefix ?: $this->defaultPrefix($category));
        $code = $prefix.'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        return DigitalCode::create([
            'business_id' => $business->id,
            'category' => $category,
            'code' => $code,
            'prefix' => $prefix,
            'status' => 'available',
            'valid_duration_minutes' => $validityMinutes,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Batch generate multiple codes at once.
     *
     * @return DigitalCode[]
     */
    public function batchGenerate(
        Business $business,
        int $quantity,
        string $category = 'wifi_voucher',
        ?int $validityMinutes = 1440,
        string $prefix = '',
        array $metadata = [],
    ): array {
        $codes = [];

        for ($i = 0; $i < $quantity; $i++) {
            $codes[] = $this->generateCode($business, $category, $validityMinutes, $prefix, $metadata);
        }

        return $codes;
    }

    // ─── Pool Claiming ─────────────────────────────────────────────────────────

    /**
     * Claim the next available code from the pool and assign it to a customer.
     *
     * @throws \RuntimeException when no codes are available.
     */
    public function claimFromPool(
        Business $business,
        string $category,
        Customer $customer,
        Payment $payment,
    ): DigitalCode {
        /** @var DigitalCode|null $code */
        $code = DigitalCode::where('business_id', $business->id)
            ->where('category', $category)
            ->where('status', 'available')
            ->lockForUpdate()
            ->first();

        if (! $code) {
            // Auto-generate if pool is empty (dynamic fallback)
            $code = $this->generateCode($business, $category, metadata: $payment->metadata ?? []);
        }

        $now = now();
        $expiresAt = $code->valid_duration_minutes
            ? $now->copy()->addMinutes($code->valid_duration_minutes)
            : null;

        $code->update([
            'status' => 'assigned',
            'customer_id' => $customer->id,
            'payment_id' => $payment->id,
            'assigned_at' => $now,
            'expires_at' => $expiresAt,
        ]);

        return $code->fresh();
    }

    // ─── Message Formatting ────────────────────────────────────────────────────

    /**
     * Build the delivery message to send the customer after payment.
     */
    public function formatDeliveryMessage(DigitalCode $code, Payment $payment): string
    {
        $categoryLabel = $this->categoryLabel($code->category);
        $amount = number_format($payment->amount, 2).' '.$payment->currency;
        $expiry = $code->expires_at
            ? $code->expires_at->format('D, d M Y \a\t g:ia')
            : 'No expiry';

        return implode("\n", [
            "✅ *Payment Confirmed — {$amount}*",
            '',
            "Here is your {$categoryLabel}:",
            '',
            "```{$code->code}```",
            '',
            "⏱ Valid for: *{$code->human_validity}*",
            "📅 Expires: *{$expiry}*",
            '',
            ...$this->additionalInstructions($code),
            '',
            '_Thank you for your purchase! Reply if you need help._',
        ]);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function defaultPrefix(string $category): string
    {
        return match ($category) {
            'wifi_voucher' => 'WIFI',
            'ticket' => 'TKT',
            'booking_pin' => 'BKG',
            'license_key' => 'LIC',
            'gift_card' => 'GIFT',
            'event_code' => 'EVT',
            default => 'CODE',
        };
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'wifi_voucher' => 'WiFi Voucher Code',
            'ticket' => 'Event Ticket',
            'booking_pin' => 'Booking PIN',
            'license_key' => 'License Key',
            'gift_card' => 'Gift Card',
            'event_code' => 'Event Code',
            default => 'Access Code',
        };
    }

    /** @return string[] */
    private function additionalInstructions(DigitalCode $code): array
    {
        if ($code->category !== 'wifi_voucher') {
            return [];
        }

        $networkName = $code->metadata['network_name'] ?? null;

        return array_filter([
            $networkName ? "📶 Network: *{$networkName}*" : null,
            '📱 Connect to the WiFi, enter this code when prompted.',
        ]);
    }
}
