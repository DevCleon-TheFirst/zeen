<?php

namespace App\Services\Payments\Drivers;

use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentDriverInterface
{
    /**
     * Initialize payment and return checkout URL.
     */
    public function initialize(Payment $payment, ?Customer $customer): string;

    /**
     * Verify incoming webhook request and return parsed event payload.
     *
     * @throws \RuntimeException
     */
    public function verifyWebhook(Request $request): array;

    /**
     * Query gateway directly to verify payment status by reference.
     */
    public function verifyTransaction(string $reference): ?array;
}
