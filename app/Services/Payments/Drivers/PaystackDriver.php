<?php

namespace App\Services\Payments\Drivers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaystackDriver implements PaymentDriverInterface
{
    public function __construct(protected Business $business) {}

    public function initialize(Payment $payment, ?Customer $customer): string
    {
        $secretKey = $this->business->settings['paystack_secret_key'] ?? config('services.paystack.secret_key');

        if (! $secretKey) {
            throw new \RuntimeException('Paystack Secret Key is not configured in Settings.');
        }

        $payload = [
            'email' => $customer?->email ?: ($payment->customer_email ?: 'customer@noreply.com'),
            'amount' => $payment->amount_kobo,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'callback_url' => route('payments.callback'),
            'metadata' => array_merge($payment->metadata ?? [], [
                'payment_id' => $payment->id,
                'business_id' => $payment->business_id,
                'customer_name' => $customer?->name ?: $payment->customer_name,
            ]),
        ];

        $response = Http::withToken($secretKey)
            ->timeout(20)
            ->post('https://api.paystack.co/transaction/initialize', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Paystack initialization failed: '.$response->body());
        }

        return (string) $response->json('data.authorization_url');
    }

    public function verifyWebhook(Request $request): array
    {
        $secretKey = $this->business->settings['paystack_secret_key'] ?? config('services.paystack.secret_key');

        if (! $secretKey) {
            throw new \RuntimeException('Paystack secret key not configured.');
        }

        $signature = $request->header('x-paystack-signature');
        $expectedHash = hash_hmac('sha512', $request->getContent(), $secretKey);

        if (! hash_equals($expectedHash, (string) $signature)) {
            throw new \RuntimeException('Invalid Paystack webhook signature.');
        }

        return $request->json()->all();
    }

    public function verifyTransaction(string $reference): ?array
    {
        $secretKey = $this->business->settings['paystack_secret_key'] ?? config('services.paystack.secret_key');

        if (! $secretKey) {
            return null;
        }

        try {
            $response = Http::withToken($secretKey)
                ->timeout(15)
                ->get("https://api.paystack.co/transaction/verify/{$reference}");

            if ($response->successful() && $response->json('status') && ($response->json('data.status') === 'success')) {
                return $response->json('data');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Paystack verification error: '.$e->getMessage());
        }

        return null;
    }
}
