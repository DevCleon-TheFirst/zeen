<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Automation\AutomationTriggerDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckAbandonedCheckoutJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment
    ) {}

    public function handle(): void
    {
        $payment = $this->payment->fresh();

        // Only fire if the payment is still pending and has not been completed or cancelled
        if (! $payment || $payment->status !== 'pending') {
            return;
        }

        $business = $payment->business;
        if (! $business) {
            return;
        }

        Log::info("Abandoned checkout detected for payment #{$payment->id}, dispatching trigger.");

        AutomationTriggerDispatcher::dispatch('abandoned_checkout', [
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'conversation_id' => $payment->conversation_id,
            'checkout_url' => $payment->checkout_url,
            'amount' => (float) ($payment->amount_kobo / 100),
            'currency' => $payment->currency,
            'description' => $payment->description,
        ], $business);
    }
}
