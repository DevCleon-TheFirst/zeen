<?php

namespace App\Services\Payments\Drivers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonnifyDriver implements PaymentDriverInterface
{
    protected string $baseUrl;

    public function __construct(protected Business $business)
    {
        // Support sandbox vs live via config or business setting
        $isLive = ($this->business->settings['monnify_mode'] ?? 'live') === 'live';
        $this->baseUrl = $isLive ? 'https://api.monnify.com' : 'https://sandbox.monnify.com';
    }

    public function initialize(Payment $payment, ?Customer $customer): string
    {
        $apiKey = $this->business->settings['monnify_api_key'] ?? config('services.monnify.api_key');
        $secretKey = $this->business->settings['monnify_secret_key'] ?? config('services.monnify.secret_key');
        $contractCode = $this->business->settings['monnify_contract_code'] ?? config('services.monnify.contract_code');

        if (! $apiKey || ! $secretKey || ! $contractCode) {
            throw new \RuntimeException('Monnify credentials (API Key, Secret Key, Contract Code) not configured.');
        }

        // Authenticate with Monnify to get access token
        $authRes = Http::withBasicAuth($apiKey, $secretKey)
            ->timeout(15)
            ->post("{$this->baseUrl}/api/v1/auth/login");

        if (! $authRes->successful() || ! $authRes->json('requestSuccessful')) {
            throw new \RuntimeException('Monnify authentication failed: '.$authRes->body());
        }

        $accessToken = $authRes->json('responseBody.accessToken');

        $customerEmail = $customer?->email ?: ($payment->customer_email ?: 'customer@noreply.com');
        $customerName = $customer?->name ?: ($payment->customer_name ?: 'Valued Customer');

        $payload = [
            'amount' => (float) ($payment->amount_kobo / 100),
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
            'paymentReference' => $payment->reference,
            'paymentDescription' => $payment->description ?: 'Online Store Purchase',
            'currencyCode' => $payment->currency ?: 'NGN',
            'contractCode' => $contractCode,
            'redirectUrl' => route('payments.callback'),
            'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER'],
        ];

        $initRes = Http::withToken($accessToken)
            ->timeout(20)
            ->post("{$this->baseUrl}/api/v1/merchant/transactions/init-transaction", $payload);

        if (! $initRes->successful() || ! $initRes->json('requestSuccessful')) {
            throw new \RuntimeException('Monnify initialization failed: '.$initRes->body());
        }

        return (string) $initRes->json('responseBody.checkoutUrl');
    }

    public function verifyWebhook(Request $request): array
    {
        $secretKey = $this->business->settings['monnify_secret_key'] ?? config('services.monnify.secret_key');

        if (! $secretKey) {
            throw new \RuntimeException('Monnify secret key not configured.');
        }

        $signature = $request->header('monnify-signature');
        $computed = hash_hmac('sha512', $request->getContent(), $secretKey);

        if (! hash_equals($computed, (string) $signature)) {
            Log::warning('Monnify webhook signature mismatch.');
            throw new \RuntimeException('Invalid Monnify webhook signature.');
        }

        return $request->json()->all();
    }

    public function verifyTransaction(string $reference): ?array
    {
        $apiKey = $this->business->settings['monnify_api_key'] ?? config('services.monnify.api_key');
        $secretKey = $this->business->settings['monnify_secret_key'] ?? config('services.monnify.secret_key');

        if (! $apiKey || ! $secretKey) {
            return null;
        }

        try {
            $authRes = Http::withBasicAuth($apiKey, $secretKey)
                ->timeout(15)
                ->post("{$this->baseUrl}/api/v1/auth/login");

            if (! $authRes->successful() || ! $authRes->json('requestSuccessful')) {
                return null;
            }

            $accessToken = $authRes->json('responseBody.accessToken');
            $encodedRef = urlencode($reference);

            $res = Http::withToken($accessToken)
                ->timeout(15)
                ->get("{$this->baseUrl}/api/v2/transactions/{$encodedRef}");

            if ($res->successful() && $res->json('requestSuccessful') && ($res->json('responseBody.paymentStatus') === 'PAID')) {
                return $res->json('responseBody');
            }
        } catch (\Throwable $e) {
            Log::error('Monnify verification error: '.$e->getMessage());
        }

        return null;
    }
}
