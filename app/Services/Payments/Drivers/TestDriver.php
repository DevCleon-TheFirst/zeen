<?php

namespace App\Services\Payments\Drivers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\Request;

class TestDriver implements PaymentDriverInterface
{
    public function __construct(protected Business $business) {}

    public function initialize(Payment $payment, ?Customer $customer): string
    {
        return route('payments.test-fulfill', ['payment' => $payment->id]);
    }

    public function verifyWebhook(Request $request): array
    {
        return $request->json()->all();
    }

    public function verifyTransaction(string $reference): ?array
    {
        return ['status' => 'success', 'reference' => $reference];
    }
}
