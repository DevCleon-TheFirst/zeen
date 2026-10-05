<?php

namespace App\Services\Payments;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Abstraction layer over payment gateways.
 *
 * Supported drivers: paystack | test
 *
 * The `test` driver creates a simulated payment that can be instantly
 * fulfilled via the test-fulfill endpoint — no real bank credentials needed.
 */
class PaymentGatewayService
{
    public function __construct(private readonly Business $business) {}

    // ─── Public API ────────────────────────────────────────────────────────────

    /**
     * Initialize a payment and return the Payment record (including checkout_url).
     */
    public function initializePayment(
        ?Customer $customer,
        ?Conversation $conversation,
        float $amount,
        string $currency = 'NGN',
        string $description = '',
        array $metadata = [],
    ): Payment {
        $reference = 'PAY-'.strtoupper(Str::random(12));
        $amountKobo = (int) round($amount * 100);

        $driverKey = $this->resolveDriverKey();

        $payment = Payment::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer?->id,
            'conversation_id' => $conversation?->id,
            'gateway' => $driverKey,
            'currency' => strtoupper($currency),
            'amount_kobo' => $amountKobo,
            'description' => $description,
            'reference' => $reference,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        $checkoutUrl = $this->driver($driverKey)->initialize($payment, $customer);

        $payment->update(['checkout_url' => $checkoutUrl]);

        // Schedule abandoned checkout detection check after 45 minutes
        try {
            \App\Jobs\CheckAbandonedCheckoutJob::dispatch($payment)->delay(now()->addMinutes(45));
        } catch (\Throwable) {
            // Non-fatal if queue is down
        }

        return $payment->fresh();
    }

    /**
     * Mark a payment completed (called from webhook or test-fulfill route).
     */
    public function markCompleted(Payment $payment, ?string $gatewayReference = null): Payment
    {
        $payment->update([
            'status' => 'completed',
            'gateway_reference' => $gatewayReference,
            'paid_at' => now(),
        ]);

        return $payment->fresh();
    }

    /**
     * Actively verify payment with the gateway and mark completed if paid.
     */
    public function verifyPayment(Payment $payment): bool
    {
        $driver = $this->driver($payment->gateway);
        $data = $driver->verifyTransaction($payment->reference);

        if ($data) {
            $gatewayRef = $data['id'] ?? $data['reference'] ?? $data['transactionReference'] ?? null;
            $this->markCompleted($payment, $gatewayRef ? (string) $gatewayRef : null);

            return true;
        }

        return false;
    }

    /**
     * Verify Paystack webhook signature and return the event payload.
     *
     * @throws \RuntimeException when signature is invalid.
     */
    public function verifyPaystackWebhook(Request $request): array
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

    /**
     * Resolve the active payment driver instance for this business.
     */
    public function driver(?string $gateway = null): Drivers\PaymentDriverInterface
    {
        $driverKey = $gateway ?: ($this->business->settings['active_payment_gateway'] ?? $this->resolveDefaultDriver());

        return match ($driverKey) {
            'monnify' => new Drivers\MonnifyDriver($this->business),
            'paystack' => new Drivers\PaystackDriver($this->business),
            default => new Drivers\TestDriver($this->business),
        };
    }

    private function resolveDriverKey(): string
    {
        return $this->business->settings['active_payment_gateway'] ?? $this->resolveDefaultDriver();
    }

    private function resolveDefaultDriver(): string
    {
        if (! empty($this->business->settings['paystack_secret_key'] ?? config('services.paystack.secret_key'))) {
            return 'paystack';
        }

        if (! empty($this->business->settings['monnify_secret_key'] ?? config('services.monnify.secret_key'))) {
            return 'monnify';
        }

        return 'test';
    }
}
