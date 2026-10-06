<?php

namespace App\Services\Orders;

use App\Mail\CustomerOrderReceipt;
use App\Mail\OwnerSaleNotification;
use App\Models\Business;
use App\Models\BusinessCatalogItem;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\Automation\AutomationTriggerDispatcher;
use App\Services\Channels\ChannelSender;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderFulfillmentService
{
    public function __construct(
        protected ChannelSender $channelSender = new ChannelSender,
    ) {}

    /**
     * Create an order from payment and cart metadata upon successful checkout.
     */
    public function fulfillOrderFromPayment(Payment $payment, Business $business): Order
    {
        $metadata = $payment->metadata ?? [];
        $cart = $metadata['cart'] ?? [];
        $shippingAddress = $metadata['shipping_address'] ?? null;
        $customerName = $metadata['customer_name'] ?? $payment->customer?->name;
        $customerPhone = $metadata['customer_phone'] ?? $payment->customer?->phone;

        $trackingCode = Order::generateTrackingCode();

        $subtotal = (float) ($payment->amount_kobo / 100);
        $shippingFee = (float) ($metadata['shipping_fee'] ?? 0);
        $totalAmount = $subtotal;

        $order = Order::create([
            'business_id' => $business->id,
            'customer_id' => $payment->customer_id,
            'conversation_id' => $payment->conversation_id,
            'payment_id' => $payment->id,
            'tracking_code' => $trackingCode,
            'status' => 'confirmed',
            'subtotal' => $subtotal - $shippingFee,
            'shipping_fee' => $shippingFee,
            'total_amount' => $totalAmount,
            'currency' => $payment->currency ?? 'NGN',
            'shipping_address' => $shippingAddress,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'metadata' => [
                'gateway' => $payment->gateway,
                'gateway_reference' => $payment->gateway_reference,
            ],
        ]);

        // Attach order items and update inventory
        $items = $cart['items'] ?? [];
        if (! empty($items)) {
            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 1);
                OrderItem::create([
                    'order_id' => $order->id,
                    'catalog_item_id' => $item['catalog_item_id'] ?? null,
                    'item_name' => $item['name'] ?? 'Item',
                    'size' => $item['size'] ?? null,
                    'color' => $item['color'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'total_price' => (float) (($item['unit_price'] ?? 0) * $qty),
                ]);

                // Deplete inventory
                if (! empty($item['catalog_item_id'])) {
                    $catalogItem = BusinessCatalogItem::find($item['catalog_item_id']);
                    if ($catalogItem && $catalogItem->track_inventory && $catalogItem->stock_quantity !== null) {
                        $newStock = max(0, $catalogItem->stock_quantity - $qty);
                        $updates = ['stock_quantity' => $newStock];
                        if ($newStock === 0) {
                            $updates['availability_status'] = 'unavailable';
                        }
                        $catalogItem->update($updates);
                        Log::info("Inventory depleted for \"{$catalogItem->name}\": new stock {$newStock}");

                        $this->dispatchInventoryAlerts($catalogItem, $newStock, $business);
                    }
                }
            }
        } else {
            // Fallback for single item purchases without full cart structure
            OrderItem::create([
                'order_id' => $order->id,
                'catalog_item_id' => $payment->catalog_item_id,
                'item_name' => $payment->description ?: 'Purchased Item',
                'size' => $metadata['size'] ?? null,
                'color' => $metadata['color'] ?? null,
                'quantity' => 1,
                'unit_price' => $subtotal,
                'total_price' => $subtotal,
            ]);

            if ($payment->catalog_item_id) {
                $catalogItem = BusinessCatalogItem::find($payment->catalog_item_id);
                if ($catalogItem && $catalogItem->track_inventory && $catalogItem->stock_quantity !== null) {
                    $newStock = max(0, $catalogItem->stock_quantity - 1);
                    $updates = ['stock_quantity' => $newStock];
                    if ($newStock === 0) {
                        $updates['availability_status'] = 'unavailable';
                    }
                    $catalogItem->update($updates);
                    Log::info("Inventory depleted for \"{$catalogItem->name}\": new stock {$newStock}");

                    $this->dispatchInventoryAlerts($catalogItem, $newStock, $business);
                }
            }
        }

        // Send formatted itemized receipt to customer
        $receipt = $this->formatReceipt($order, $business);
        $this->notifyCustomer($order, $business, $receipt);

        // Notify Store Owner via Telegram
        $this->notifyStoreOwner($order, $business);

        // Send customer email receipt
        $this->sendCustomerReceiptEmail($order, $business, $payment);

        // Send owner sale notification email
        $this->sendOwnerSaleEmail($order, $business);

        // Dispatch order_created automation trigger
        try {
            AutomationTriggerDispatcher::dispatch('order_created', [
                'order_id' => $order->id,
                'tracking_code' => $order->tracking_code,
                'customer_id' => $order->customer_id,
                'conversation_id' => $order->conversation_id,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
            ], $business);
        } catch (\Throwable $e) {
            Log::warning('Automation order_created dispatch failed: '.$e->getMessage());
        }

        return $order;
    }

    /**
     * Dispatch inventory triggers when stock hits critical thresholds.
     */
    protected function dispatchInventoryAlerts(BusinessCatalogItem $item, int $newStock, Business $business): void
    {
        try {
            if ($newStock === 0) {
                AutomationTriggerDispatcher::dispatch('inventory_out_of_stock', [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'price' => (float) $item->price,
                    'currency' => $item->currency,
                    'stock_quantity' => 0,
                ], $business);
            } elseif ($newStock <= 3) {
                AutomationTriggerDispatcher::dispatch('inventory_low', [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'price' => (float) $item->price,
                    'currency' => $item->currency,
                    'stock_quantity' => $newStock,
                ], $business);
            }
        } catch (\Throwable $e) {
            Log::warning('Inventory alert trigger dispatch failed: '.$e->getMessage());
        }
    }

    /**
     * Build clean, beautiful itemized receipt for customer.
     */
    public function formatReceipt(Order $order, Business $business): string
    {
        $currency = $order->currency ?: 'NGN';
        $formattedTotal = $currency.' '.number_format($order->total_amount, 2);
        $date = Carbon::parse($order->created_at)->format('d M Y, g:i A');

        $lines = [];
        $lines[] = "🧾 *OFFICIAL RECEIPT — {$business->name}*";
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = "Order Number: `{$order->tracking_code}`";
        $lines[] = "Date: {$date}";
        $lines[] = "Customer: {$order->customer_name} (".($order->customer_phone ?: 'Phone on file').')';
        if ($order->shipping_address) {
            $lines[] = "Delivery To: {$order->shipping_address}";
        }
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = '*ITEMS ORDERED:*';

        foreach ($order->items as $item) {
            $spec = [];
            if ($item->size) {
                $spec[] = "Size: {$item->size}";
            }
            if ($item->color) {
                $spec[] = "Color: {$item->color}";
            }
            $specStr = ! empty($spec) ? ' ('.implode(', ', $spec).')' : '';
            $itemTotal = $currency.' '.number_format($item->total_price, 2);
            $lines[] = "• {$item->quantity}x *{$item->item_name}*{$specStr} — {$itemTotal}";
        }

        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━';
        if ($order->shipping_fee > 0) {
            $lines[] = 'Subtotal: '.$currency.' '.number_format($order->subtotal, 2);
            $lines[] = 'Delivery Fee: '.$currency.' '.number_format($order->shipping_fee, 2);
        }
        $lines[] = "*TOTAL PAID: {$formattedTotal} (PAID ✅)*";
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = "🚚 *TRACKING CODE: `{$order->tracking_code}`*";
        $lines[] = 'Status: *CONFIRMED (Preparing for Dispatch)*';
        $lines[] = "\n_💡 Tip: You can type \"track {$order->tracking_code}\" anytime in this chat to see delivery updates._";

        return implode("\n", $lines);
    }

    /**
     * Look up order status by tracking code.
     */
    public function lookupTracking(string $trackingCode, Business $business): ?string
    {
        $order = Order::where('business_id', $business->id)
            ->where('tracking_code', strtoupper(trim($trackingCode)))
            ->with('items')
            ->first();

        if (! $order) {
            return null;
        }

        $statusEmoji = match ($order->status) {
            'confirmed' => '📦',
            'processing' => '⚙️',
            'dispatched' => '🚚',
            'delivered' => '✅',
            default => '📋',
        };

        $statusText = strtoupper($order->status);
        $date = Carbon::parse($order->created_at)->format('d M Y');

        $lines = [];
        $lines[] = "{$statusEmoji} *ORDER STATUS: {$statusText}*";
        $lines[] = "Tracking Code: `{$order->tracking_code}`";
        $lines[] = "Order Date: {$date}";
        $lines[] = "Total: {$order->currency} ".number_format($order->total_amount, 2);
        if ($order->shipping_address) {
            $lines[] = "Destination: {$order->shipping_address}";
        }

        if ($order->status === 'dispatched') {
            $lines[] = "\n🚀 _Your order has been dispatched and is currently on the way with our courier!_";
        } elseif ($order->status === 'delivered') {
            $lines[] = "\n🎉 _This package has been successfully delivered. Thank you for shopping with us!_";
        } else {
            $lines[] = "\n🕒 _Your order is confirmed and our team is currently preparing your package._";
        }

        return implode("\n", $lines);
    }

    protected function notifyCustomer(Order $order, Business $business, string $message): void
    {
        if (! $order->conversation_id) {
            return;
        }

        $conversation = Conversation::find($order->conversation_id);
        if ($conversation) {
            try {
                $this->channelSender->send($conversation, $message, null, 'ai');
            } catch (\Throwable $e) {
                Log::error("Failed to send customer order receipt: {$e->getMessage()}");
            }
        }
    }

    protected function notifyStoreOwner(Order $order, Business $business): void
    {
        // Check if Telegram owner chat ID is set in business settings
        $ownerChatId = $business->settings['owner_telegram_chat_id'] ?? null;
        $telegramChannel = $business->channels()->where('channel', 'telegram')->first();

        if ($ownerChatId && $telegramChannel) {
            $alert = "🚨 *NEW ORDER RECEIVED!*\n\n";
            $alert .= "Order: `{$order->tracking_code}`\n";
            $alert .= "Customer: {$order->customer_name} ({$order->customer_phone})\n";
            $alert .= "Total Paid: {$order->currency} ".number_format($order->total_amount, 2)." ✅\n";
            $alert .= "Address: {$order->shipping_address}\n\n";
            $alert .= "*Items:*\n";
            foreach ($order->items as $item) {
                $size = $item->size ? " [Size: {$item->size}]" : '';
                $alert .= "• {$item->quantity}x {$item->item_name}{$size}\n";
            }
            $alert .= "\n_Check your dashboard to manage fulfillment._";

            try {
                $this->channelSender->sendTelegram($telegramChannel, (string) $ownerChatId, $alert);
            } catch (\Throwable $e) {
                Log::warning("Failed to ping owner Telegram: {$e->getMessage()}");
            }
        }
    }

    /**
     * Send HTML receipt email to the customer.
     */
    protected function sendCustomerReceiptEmail(Order $order, Business $business, Payment $payment): void
    {
        $customerEmail = $order->customer_email
            ?? $payment->metadata['customer_email']
            ?? $payment->customer?->email
            ?? null;

        if (! $customerEmail) {
            Log::info("No customer email available for order {$order->tracking_code} — skipping receipt email.");

            return;
        }

        try {
            $order->loadMissing('items');
            Mail::to($customerEmail)->send(new CustomerOrderReceipt($order, $business, $payment));
            Log::info("Customer receipt email sent to {$customerEmail} for order {$order->tracking_code}.");
        } catch (\Throwable $e) {
            Log::error("Failed to send customer receipt email for order {$order->tracking_code}: {$e->getMessage()}");
        }
    }

    /**
     * Send sale notification email to the business owner.
     */
    protected function sendOwnerSaleEmail(Order $order, Business $business): void
    {
        $ownerEmail = $business->email
            ?? $business->users()->wherePivot('role', 'owner')->first()?->email
            ?? null;

        if (! $ownerEmail) {
            Log::info("No owner email found for business {$business->id} — skipping sale notification email.");

            return;
        }

        try {
            $order->loadMissing(['items', 'items.catalogItem']);
            Mail::to($ownerEmail)->send(new OwnerSaleNotification($order, $business));
            Log::info("Owner sale notification email sent to {$ownerEmail} for order {$order->tracking_code}.");
        } catch (\Throwable $e) {
            Log::error("Failed to send owner sale notification email for order {$order->tracking_code}: {$e->getMessage()}");
        }
    }
}
