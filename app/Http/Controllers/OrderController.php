<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Automation\AutomationTriggerDispatcher;
use App\Services\Channels\ChannelSender;
use App\Services\Orders\OrderFulfillmentService;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $query = Order::where('business_id', $business->id)->with(['items', 'customer', 'payment']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest()->paginate(25)->withQueryString();

        $stats = [
            'total_orders' => Order::where('business_id', $business->id)->count(),
            'confirmed' => Order::where('business_id', $business->id)->where('status', 'confirmed')->count(),
            'dispatched' => Order::where('business_id', $business->id)->where('status', 'dispatched')->count(),
            'delivered' => Order::where('business_id', $business->id)->where('status', 'delivered')->count(),
            'total_sales' => (float) Order::where('business_id', $business->id)->where('status', '!=', 'cancelled')->sum('total_amount'),
        ];

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $order->business_id === $business->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:confirmed,processing,dispatched,delivered,cancelled'],
        ]);

        $order->status = $validated['status'];
        if ($validated['status'] === 'dispatched' && ! $order->dispatched_at) {
            $order->dispatched_at = now();
        }
        if ($validated['status'] === 'delivered' && ! $order->delivered_at) {
            $order->delivered_at = now();
        }
        $order->save();

        // Optionally notify customer via their conversation if status changed
        if (in_array($validated['status'], ['dispatched', 'delivered']) && $order->conversation) {
            try {
                $orderService = new OrderFulfillmentService;
                $updateMsg = $orderService->lookupTracking($order->tracking_code, $business);
                if ($updateMsg) {
                    $sender = new ChannelSender;
                    $sender->send($order->conversation, $updateMsg, auth()->user(), 'human');
                }
            } catch (\Throwable) {
                // Non-fatal
            }
        }

        // Dispatch order_status_updated automation trigger
        try {
            AutomationTriggerDispatcher::dispatch('order_status_updated', [
                'order_id' => $order->id,
                'tracking_code' => $order->tracking_code,
                'status' => $order->status,
                'customer_id' => $order->customer_id,
                'conversation_id' => $order->conversation_id,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
            ], $business);
        } catch (\Throwable) {
            // Non-fatal
        }

        return back()->with('success', "Order #{$order->tracking_code} status updated to ".strtoupper($validated['status']));
    }
}
