<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Models\Business;
use App\Models\BusinessChannel;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Automation\AutomationTriggerDispatcher;
use App\Services\Channels\ChannelSender;
use App\Services\Orders\OrderFulfillmentService;
use App\Services\Payments\PaymentGatewayService;
use App\Services\Vouchers\DigitalVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Handles inbound payment webhook events, checkout redirects, and customer return flows.
 *
 * Routes:
 *   POST /api/webhooks/paystack            — Paystack webhook
 *   POST /api/webhooks/monnify             — Monnify webhook
 *   GET  /payments/callback                — Gateway redirect after checkout
 *   GET  /payments/{payment}/test-fulfill  — Instant sandbox fulfillment (dev only)
 *   GET  /payments/{payment:reference}/success — Public payment success page with WhatsApp deep-link
 */
class PaymentWebhookController extends Controller
{
    // ─── Paystack Webhook ──────────────────────────────────────────────────────

    public function paystackWebhook(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $reference = $payload['data']['reference'] ?? null;

        if (! $reference) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $payment = Payment::where('reference', $reference)->first();

        if (! $payment || $payment->isCompleted()) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $business = $payment->business;
        $gateway = new PaymentGatewayService($business);

        try {
            $gateway->verifyPaystackWebhook($request);
        } catch (\RuntimeException) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        if (($payload['event'] ?? '') === 'charge.success') {
            $this->fulfillPayment($payment, $business, $payload['data']['id'] ?? null);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    // ─── Monnify Webhook ───────────────────────────────────────────────────────

    public function monnifyWebhook(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $eventData = $payload['eventData'] ?? [];
        $reference = $eventData['paymentReference'] ?? null;

        if (! $reference) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $payment = Payment::where('reference', $reference)->first();

        if (! $payment || $payment->isCompleted()) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $business = $payment->business;
        $gateway = new PaymentGatewayService($business);

        try {
            $gateway->driver('monnify')->verifyWebhook($request);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'unauthorized', 'error' => $e->getMessage()], 401);
        }

        if (($payload['eventType'] ?? '') === 'SUCCESSFUL_TRANSACTION') {
            $this->fulfillPayment($payment, $business, $eventData['transactionReference'] ?? null);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    // ─── Gateway Redirect Callback ─────────────────────────────────────────────

    public function callback(Request $request): RedirectResponse
    {
        $reference = $request->query('reference') ?? $request->query('trxref');
        $payment = $reference ? Payment::where('reference', $reference)->first() : null;

        if (! $payment) {
            return redirect('/');
        }

        $business = $payment->business;

        // Actively verify payment with the gateway if not completed yet
        if (! $payment->isCompleted()) {
            $gateway = new PaymentGatewayService($business);
            $verified = $gateway->verifyPayment($payment);

            if ($verified) {
                $this->fulfillPayment($payment, $business, $payment->gateway_reference);
            }
        }

        return redirect()->route('payments.success', ['payment' => $payment->reference]);
    }

    // ─── Test / Sandbox Fulfillment (Dev Only) ─────────────────────────────────

    public function testFulfill(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless(app()->isLocal() || app()->environment('testing'), 403, 'Only available in development.');

        if (! $payment->isCompleted()) {
            $this->fulfillPayment($payment, $payment->business, 'test-ref-'.time());
        }

        return redirect()->route('payments.success', ['payment' => $payment->reference]);
    }

    // ─── Customer Success Page with WhatsApp Deep-Link ────────────────────────

    public function success(Request $request, Payment $payment): InertiaResponse
    {
        $business = $payment->business;
        $order = Order::where('payment_id', $payment->id)->with('items')->first();
        $returnInfo = $this->resolveCustomerReturnDestination($payment, $order);

        return Inertia::render('Payments/Success', [
            'payment' => [
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'description' => $payment->description,
            ],
            'order' => $order ? [
                'tracking_code' => $order->tracking_code,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
                'shipping_address' => $order->shipping_address,
                'customer_name' => $order->customer_name,
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'size' => $item->size,
                    'color' => $item->color,
                ]),
            ] : null,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'phone' => $business->phone,
            ],
            'return_info' => $returnInfo,
        ]);
    }

    // ─── Shared Fulfillment Logic ──────────────────────────────────────────────

    private function fulfillPayment(Payment $payment, Business $business, ?string $gatewayRef): void
    {
        $gateway = new PaymentGatewayService($business);
        $gateway->markCompleted($payment, $gatewayRef);

        $payment->refresh();

        $metadata = $payment->metadata ?? [];
        $codeCategory = $metadata['code_category'] ?? null;

        if ($codeCategory && $payment->customer) {
            $voucherService = new DigitalVoucherService;
            $code = $voucherService->claimFromPool($business, $codeCategory, $payment->customer, $payment);
            $message = $voucherService->formatDeliveryMessage($code, $payment);
            $this->sendToCustomer($payment, $business, $message);
        } elseif (! empty($metadata['cart']) || ! empty($metadata['shipping_address'])) {
            // E-commerce cart order fulfillment
            $orderService = new OrderFulfillmentService;
            $order = $orderService->fulfillOrderFromPayment($payment, $business);
        } else {
            // Generic payment confirmation
            $amount = number_format($payment->amount, 2).' '.$payment->currency;
            $message = "✅ *Payment Confirmed — {$amount}*\n\nThank you! Your payment has been received successfully.\nReference: `{$payment->reference}`";
            $this->sendToCustomer($payment, $business, $message);
        }

        // Reset conversation to active AI handling so AI is ready when customer returns
        if ($payment->conversation_id) {
            $payment->conversation()->update([
                'handler' => 'ai',
                'status' => ConversationState::AiHandling,
                'human_last_replied_at' => null,
            ]);
        }

        // Fire automation workflows listening for payment_received
        AutomationTriggerDispatcher::dispatch('payment_received', [
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'conversation_id' => $payment->conversation_id,
        ], $business);
    }

    private function sendToCustomer(Payment $payment, Business $business, string $message): void
    {
        if (! $payment->customer_id || ! $payment->conversation_id) {
            return;
        }

        $conversation = $payment->conversation()->with(['businessChannel', 'customer'])->first();

        if (! $conversation?->businessChannel) {
            return;
        }

        try {
            $sender = new ChannelSender;
            $sender->send($conversation, $message, null, 'ai');
        } catch (\Throwable $e) {
            Log::error("Failed to send payment confirmation to customer: {$e->getMessage()}");
        }
    }

    public function resolveCustomerReturnDestination(Payment $payment, ?Order $order = null): array
    {
        $conversation = $payment->conversation;
        $business = $payment->business;
        $trackingCode = $order?->tracking_code ?? $payment->reference;

        if ($conversation && $conversation->channel === ChannelType::Telegram) {
            $tgChannel = BusinessChannel::withoutGlobalScope('tenant')
                ->where('business_id', $business->id)
                ->where('channel', ChannelType::Telegram)
                ->first();
            $botUsername = $tgChannel?->credentials['bot_username'] ?? null;
            if ($botUsername) {
                return [
                    'channel' => 'telegram',
                    'channel_name' => 'Telegram',
                    'return_url' => "https://t.me/{$botUsername}",
                    'tracking_code' => $trackingCode,
                ];
            }
        }

        // WhatsApp Web QR or WhatsApp Cloud API
        $waChannel = BusinessChannel::withoutGlobalScope('tenant')
            ->where('business_id', $business->id)
            ->whereIn('channel', [ChannelType::WhatsappWeb, ChannelType::Whatsapp])
            ->where('is_active', true)
            ->first();

        $phone = $waChannel?->credentials['phone']
            ?? $business->phone
            ?? null;

        if ($phone) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $msg = "Hi! I just completed my payment for order #{$trackingCode}. 🎉";

            return [
                'channel' => 'whatsapp',
                'channel_name' => 'WhatsApp',
                'phone' => $cleanPhone,
                'return_url' => "https://wa.me/{$cleanPhone}?text=".urlencode($msg),
                'tracking_code' => $trackingCode,
            ];
        }

        return [
            'channel' => 'web',
            'channel_name' => 'Store',
            'return_url' => '/',
            'tracking_code' => $trackingCode,
        ];
    }
}
