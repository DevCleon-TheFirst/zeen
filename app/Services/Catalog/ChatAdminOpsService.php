<?php

namespace App\Services\Catalog;

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessChannel;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\User;
use App\Services\Channels\ChannelSender;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class ChatAdminOpsService
{
    public function __construct(
        protected ChannelSender $channelSender,
    ) {}

    /**
     * Check if the incoming message is an administrative command for the store owner.
     */
    public function isCommand(?string $content): bool
    {
        if (! $content) {
            return false;
        }

        $trimmed = trim(strtolower($content));

        return str_starts_with($trimmed, '/dashboard')
            || str_starts_with($trimmed, '/orders')
            || str_starts_with($trimmed, '/order')
            || str_starts_with($trimmed, '/dispatch')
            || str_starts_with($trimmed, '/stock')
            || str_starts_with($trimmed, '/inventory')
            || str_starts_with($trimmed, '/addproduct')
            || str_starts_with($trimmed, '/add')
            || str_starts_with($trimmed, 'add product:')
            || str_starts_with($trimmed, 'add:')
            || str_starts_with($trimmed, '/restock')
            || str_starts_with($trimmed, '/price')
            || str_starts_with($trimmed, '/admin_link');
    }

    /**
     * Verify if the sender is an authorized store administrator or manager.
     */
    public function isAuthorizedAdmin(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        bool $isFromMe = false
    ): bool {
        // 1. WhatsApp linked web device itself is always authorized
        if ($isFromMe) {
            return true;
        }

        // 2. Telegram specific authorization: Check channel credentials for admin_chat_id
        if ($channelType === ChannelType::Telegram) {
            $channel = BusinessChannel::withoutGlobalScopes()->where('business_id', $business->id)
                ->where('channel', ChannelType::Telegram)
                ->first();

            $adminChatId = $channel?->credentials['admin_chat_id'] ?? null;
            if ($adminChatId && (string) $adminChatId === (string) $channelUserId) {
                return true;
            }
        }

        // 3. Phone number matching for WhatsApp & SMS
        $cleanPhone = preg_replace('/[^0-9]/', '', $channelUserId);
        if (empty($cleanPhone)) {
            return false;
        }

        $suffix = strlen($cleanPhone) >= 10 ? substr($cleanPhone, -10) : $cleanPhone;

        return User::where('business_id', $business->id)
            ->where('is_active', true)
            ->where(function ($query) use ($cleanPhone, $suffix) {
                $query->where('phone', $cleanPhone)
                    ->orWhere('phone', 'like', "%{$suffix}");
            })
            ->whereIn('role', [UserRole::Owner, UserRole::Admin, UserRole::Manager])
            ->exists();
    }

    /**
     * Handle the admin command and send a response back to the channel.
     */
    public function handle(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        ?string $content,
        ?array $media = null,
        bool $isFromMe = false
    ): ?string {
        if (! $content) {
            return null;
        }

        $trimmed = trim($content);
        $lower = strtolower($trimmed);

        // Allow /admin_link to be processed even before authorization to establish the link
        if (str_starts_with($lower, '/admin_link')) {
            return $this->handleAdminLinkCommand($business, $channelType, $channelUserId, $trimmed);
        }

        // Security check: Must be authorized admin
        if (! $this->isAuthorizedAdmin($business, $channelType, $channelUserId, $isFromMe)) {
            return null;
        }

        if (str_starts_with($lower, '/dashboard')) {
            return $this->handleDashboardQuery($business, $channelType, $channelUserId);
        }

        if (str_starts_with($lower, '/orders')) {
            return $this->handleOrdersQuery($business, $channelType, $channelUserId);
        }

        if (str_starts_with($lower, '/dispatch')) {
            return $this->handleDispatchCommand($business, $channelType, $channelUserId, $trimmed);
        }

        if (str_starts_with($lower, '/order')) {
            return $this->handleSingleOrderQuery($business, $channelType, $channelUserId, $trimmed);
        }

        if (str_starts_with($lower, '/stock') || str_starts_with($lower, '/inventory')) {
            return $this->handleStockQuery($business, $channelType, $channelUserId);
        }

        if (str_starts_with($lower, '/addproduct')
            || str_starts_with($lower, '/add')
            || str_starts_with($lower, 'add product:')
            || str_starts_with($lower, 'add:')
        ) {
            return $this->handleProductUpload($business, $channelType, $channelUserId, $trimmed, $media);
        }

        if (str_starts_with($lower, '/restock')) {
            return $this->handleRestockCommand($business, $channelType, $channelUserId, $trimmed);
        }

        if (str_starts_with($lower, '/price')) {
            return $this->handlePriceCommand($business, $channelType, $channelUserId, $trimmed);
        }

        return null;
    }

    /**
     * Generate executive dashboard report with zero emojis.
     */
    protected function handleDashboardQuery(Business $business, ChannelType $channelType, string $channelUserId): string
    {
        $today = Carbon::today();

        // 1. Financial & Order Metrics
        $todayOrdersQuery = Order::where('business_id', $business->id)->whereDate('created_at', $today);
        $ordersTodayCount = (int) $todayOrdersQuery->count();
        $revenueToday = (float) $todayOrdersQuery->whereIn('status', ['paid', 'dispatched', 'delivered'])->sum('total_amount');

        $pendingFulfillmentCount = (int) Order::where('business_id', $business->id)
            ->whereIn('status', ['pending', 'paid'])
            ->count();

        $escalatedCount = (int) Conversation::where('business_id', $business->id)
            ->where('status', ConversationState::Escalated)
            ->count();

        // 2. Recent Orders (Top 3)
        $recentOrders = Order::with(['items', 'customer'])
            ->where('business_id', $business->id)
            ->latest()
            ->take(3)
            ->get();

        // 3. Catalog & Inventory Metrics
        $totalItems = BusinessCatalogItem::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->count();

        $lowStockItems = BusinessCatalogItem::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->where('stock_quantity', '<=', 3)
            ->take(4)
            ->get();

        $currency = 'NGN';
        $formattedRevenue = number_format($revenueToday, 2).' '.$currency;
        $timestamp = Carbon::now($business->timezone ?: 'UTC')->format('Y-m-d H:i T');

        $reply = "========================================\n";
        $reply .= "ZEEN BUSINESS EXECUTIVE SUMMARY\n";
        $reply .= "Store: {$business->name}\n";
        $reply .= "Date: {$timestamp}\n";
        $reply .= "========================================\n\n";

        $reply .= "[FINANCIAL & ORDERS TODAY]\n";
        $reply .= "- Orders Today: {$ordersTodayCount}\n";
        $reply .= "- Revenue Today: {$formattedRevenue}\n";
        $reply .= "- Pending Fulfillment: {$pendingFulfillmentCount} order(s)\n";
        $reply .= "- Unresolved Escalations: {$escalatedCount} conversation(s)\n\n";

        $reply .= "[RECENT ORDERS]\n";
        if ($recentOrders->isEmpty()) {
            $reply .= "No orders recorded yet.\n\n";
        } else {
            foreach ($recentOrders as $index => $order) {
                $num = $index + 1;
                $statusUpper = strtoupper($order->status);
                $orderTotal = number_format($order->total_amount, 2).' '.($order->currency ?: $currency);
                $custName = $order->customer_name ?: ($order->customer?->name ?: 'Customer');
                $custPhone = $order->customer_phone ?: ($order->customer?->phone ?: 'No Phone');
                $firstItem = $order->items->first();
                $itemSummary = $firstItem ? "{$firstItem->item_name} (Qty: {$firstItem->quantity})" : 'General Order';
                $timeAgo = $order->created_at->diffForHumans();

                $reply .= "{$num}. {$order->tracking_code} | {$orderTotal} | [{$statusUpper}]\n";
                $reply .= "   Customer: {$custName} ({$custPhone})\n";
                $reply .= "   Item: {$itemSummary}\n";
                $reply .= "   Placed: {$timeAgo}\n";
            }
            $reply .= "\n";
        }

        $reply .= "[CATALOG & INVENTORY]\n";
        $reply .= "- Active Catalog Items: {$totalItems} SKUs\n";
        if ($lowStockItems->isEmpty()) {
            $reply .= "- Inventory Health: All tracked products sufficiently stocked.\n";
        } else {
            $reply .= "- Low Stock / Out of Stock: {$lowStockItems->count()} item(s)\n";
            foreach ($lowStockItems as $lItem) {
                $statusLabel = $lItem->stock_quantity <= 0 ? 'OUT OF STOCK' : "{$lItem->stock_quantity} left";
                $reply .= "  * {$lItem->name} ([{$statusLabel}])\n";
            }
        }

        $reply .= "\n========================================\n";
        $reply .= "COMMAND INDEX:\n";
        $reply .= "- /orders : Full recent orders log\n";
        $reply .= "- /dispatch [TrackingCode] : Mark order as dispatched\n";
        $reply .= "- /stock : Full inventory list\n";
        $reply .= "- /restock [Item] [Qty] : Update stock count\n";
        $reply .= "- /price [Item] [NewPrice] : Update price\n";
        $reply .= "- /add [Name], Price: [Val], Stock: [Qty] : Add product\n";
        $reply .= '========================================';

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    /**
     * List recent orders with zero emojis.
     */
    protected function handleOrdersQuery(Business $business, ChannelType $channelType, string $channelUserId): string
    {
        $orders = Order::with(['items', 'customer'])
            ->where('business_id', $business->id)
            ->latest()
            ->take(6)
            ->get();

        if ($orders->isEmpty()) {
            $reply = "========================================\n";
            $reply .= "RECENT ORDERS LOG\n";
            $reply .= "Store: {$business->name}\n";
            $reply .= "========================================\n";
            $reply .= "No orders recorded in system.\n";
            $reply .= '========================================';
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $reply = "========================================\n";
        $reply .= "RECENT ORDERS LOG\n";
        $reply .= "Store: {$business->name}\n";
        $reply .= "========================================\n";

        foreach ($orders as $index => $order) {
            $num = $index + 1;
            $statusUpper = strtoupper($order->status);
            $total = number_format($order->total_amount, 2).' '.($order->currency ?: 'NGN');
            $custName = $order->customer_name ?: ($order->customer?->name ?: 'Customer');
            $custPhone = $order->customer_phone ?: ($order->customer?->phone ?: 'N/A');
            $timeAgo = $order->created_at->diffForHumans();

            $reply .= "{$num}. [{$statusUpper}] {$order->tracking_code}\n";
            $reply .= "   Customer: {$custName} ({$custPhone})\n";
            $reply .= "   Total: {$total}\n";
            if ($order->shipping_address) {
                $reply .= "   Shipping: {$order->shipping_address}\n";
            }
            if ($order->items->isNotEmpty()) {
                $itemList = $order->items->map(fn ($i) => "{$i->item_name} (x{$i->quantity})")->implode(', ');
                $reply .= "   Items: {$itemList}\n";
            }
            $reply .= "   Placed: {$timeAgo}\n\n";
        }

        $reply .= "Action: To mark an order as dispatched, send:\n";
        $reply .= "/dispatch [TrackingCode]\n";
        $reply .= '========================================';

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    /**
     * Mark an order as dispatched.
     */
    protected function handleDispatchCommand(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $rawContent
    ): string {
        $cleaned = trim(preg_replace('/^\/dispatch\s*/i', '', $rawContent));
        $trackingCode = strtoupper($cleaned);

        if (empty($trackingCode)) {
            $reply = "[ERROR] Invalid command format.\nUsage: /dispatch [TrackingCode]\nExample: /dispatch TRK-849201";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $order = Order::with('customer')
            ->where('business_id', $business->id)
            ->where('tracking_code', $trackingCode)
            ->first();

        if (! $order) {
            $reply = "[ERROR] Order with tracking code '{$trackingCode}' not found.";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $order->update([
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ]);

        $recipientName = $order->customer_name ?: ($order->customer?->name ?: 'Customer');
        $recipientPhone = $order->customer_phone ?: ($order->customer?->phone ?: 'N/A');
        $totalFormatted = number_format($order->total_amount, 2).' '.($order->currency ?: 'NGN');
        $dispatchedTime = now()->format('Y-m-d H:i T');

        // Trigger customer dispatch alert if conversation exists
        $notifiedCustomer = false;
        if ($order->conversation) {
            try {
                $buyerMsg = "Your order {$order->tracking_code} has been dispatched for delivery.\nTracking Code: {$order->tracking_code}\nThank you for shopping with {$business->name}!";
                $this->channelSender->send($order->conversation, $buyerMsg, null, 'ai');
                $notifiedCustomer = true;
            } catch (\Throwable $e) {
            }
        }

        $customerAlertStatus = $notifiedCustomer ? 'Notification delivered' : 'No active chat session';

        $reply = "========================================\n";
        $reply .= "[CONFIRMED] ORDER DISPATCHED\n";
        $reply .= "========================================\n";
        $reply .= "Tracking Code: {$order->tracking_code}\n";
        $reply .= "Recipient: {$recipientName} ({$recipientPhone})\n";
        $reply .= "Total: {$totalFormatted}\n";
        $reply .= "Status: DISPATCHED\n";
        $reply .= "Dispatched At: {$dispatchedTime}\n";
        $reply .= "Customer Alert: {$customerAlertStatus}\n";
        $reply .= '========================================';

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    /**
     * Inspect a single order.
     */
    protected function handleSingleOrderQuery(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $rawContent
    ): string {
        $cleaned = trim(preg_replace('/^\/order\s*/i', '', $rawContent));
        $trackingCode = strtoupper($cleaned);

        if (empty($trackingCode)) {
            $reply = "[ERROR] Invalid format.\nUsage: /order [TrackingCode]\nExample: /order TRK-849201";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $order = Order::with(['items', 'customer'])
            ->where('business_id', $business->id)
            ->where('tracking_code', $trackingCode)
            ->first();

        if (! $order) {
            $reply = "[ERROR] Order '{$trackingCode}' not found.";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $statusUpper = strtoupper($order->status);
        $totalFormatted = number_format($order->total_amount, 2).' '.($order->currency ?: 'NGN');
        $custName = $order->customer_name ?: ($order->customer?->name ?: 'Customer');
        $custPhone = $order->customer_phone ?: ($order->customer?->phone ?: 'N/A');

        $reply = "========================================\n";
        $reply .= "ORDER DETAILS: {$order->tracking_code}\n";
        $reply .= "========================================\n";
        $reply .= "Status: [{$statusUpper}]\n";
        $reply .= "Customer: {$custName} ({$custPhone})\n";
        $reply .= "Total: {$totalFormatted}\n";
        if ($order->shipping_address) {
            $reply .= "Address: {$order->shipping_address}\n";
        }
        $reply .= "Date Placed: {$order->created_at->format('Y-m-d H:i T')}\n";
        if ($order->dispatched_at) {
            $reply .= "Dispatched At: {$order->dispatched_at->format('Y-m-d H:i T')}\n";
        }
        $reply .= "\nItems:\n";
        foreach ($order->items as $item) {
            $itemTotal = number_format($item->total_price, 2).' '.($order->currency ?: 'NGN');
            $reply .= "- {$item->item_name} | Qty: {$item->quantity} | Total: {$itemTotal}\n";
        }
        $reply .= '========================================';

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    /**
     * Inventory list query with zero emojis.
     */
    protected function handleStockQuery(Business $business, ChannelType $channelType, string $channelUserId): string
    {
        $items = BusinessCatalogItem::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->take(15)
            ->get();

        if ($items->isEmpty()) {
            $reply = "========================================\n";
            $reply .= "INVENTORY REPORT\n";
            $reply .= "Store: {$business->name}\n";
            $reply .= "========================================\n";
            $reply .= "Your catalog is currently empty.\n\n";
            $reply .= "To add a product, upload a photo or send:\n";
            $reply .= "/add [Product Name], Price: [Amount], Stock: [Qty]\n";
            $reply .= '========================================';
        } else {
            $reply = "========================================\n";
            $reply .= "INVENTORY REPORT\n";
            $reply .= "Store: {$business->name}\n";
            $reply .= "Total Items: {$items->count()}\n";
            $reply .= "========================================\n";

            foreach ($items as $index => $item) {
                $num = $index + 1;
                $stock = $item->stock_quantity !== null ? "{$item->stock_quantity} units" : 'Tracked';
                $price = $item->price ? number_format($item->price, 2).' '.$item->currency : 'Free';
                $statusTag = ($item->stock_quantity !== null && $item->stock_quantity <= 0) ? '[OUT OF STOCK]' : '[IN STOCK]';

                $reply .= "{$num}. {$item->name}\n";
                $reply .= "   Price: {$price} | Stock: {$stock} | Status: {$statusTag}\n";
            }

            $reply .= "\n========================================\n";
            $reply .= "Actions:\n";
            $reply .= "- /restock [Item Name] [Quantity]\n";
            $reply .= "- /price [Item Name] [New Price]\n";
            $reply .= '========================================';
        }

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    /**
     * Add product with zero emojis.
     */
    protected function handleProductUpload(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $rawContent,
        ?array $media = null
    ): string {
        $cleaned = preg_replace('/^(\/addproduct|\/add|add product:|add:)\s*/i', '', $rawContent);

        $parts = array_map('trim', explode(',', $cleaned));
        $name = $parts[0] ?? 'New Product';
        $price = 0.0;
        $stock = null;
        $category = 'General';

        foreach ($parts as $part) {
            if (preg_match('/price\s*[:=]\s*([0-9.]+)/i', $part, $m)) {
                $price = (float) $m[1];
            } elseif (preg_match('/stock\s*[:=]\s*([0-9]+)/i', $part, $m)) {
                $stock = (int) $m[1];
            } elseif (preg_match('/category\s*[:=]\s*(.+)/i', $part, $m)) {
                $category = trim($m[1]);
            }
        }

        if ($price === 0.0 && isset($parts[1]) && is_numeric(preg_replace('/[^0-9.]/', '', $parts[1]))) {
            $price = (float) preg_replace('/[^0-9.]/', '', $parts[1]);
        }
        if ($stock === null && isset($parts[2]) && is_numeric(trim($parts[2]))) {
            $stock = (int) trim($parts[2]);
        }

        $images = [];
        if (! empty($media)) {
            foreach ($media as $item) {
                if (isset($item['url'])) {
                    $images[] = $item['url'];
                }
            }
        }

        $status = ($stock !== null && $stock === 0) ? 'unavailable' : 'available';

        $catalogItem = BusinessCatalogItem::create([
            'business_id' => $business->id,
            'name' => $name,
            'description' => 'Added via Chat Admin',
            'price' => $price,
            'currency' => 'NGN',
            'category' => $category,
            'availability_status' => $status,
            'stock_quantity' => $stock,
            'track_inventory' => true,
            'images' => ! empty($images) ? $images : null,
            'is_active' => true,
        ]);

        $formattedPrice = number_format($price, 2).' NGN';
        $stockDisplay = $stock !== null ? "{$stock} units" : 'Tracked';

        $reply = "========================================\n";
        $reply .= "[CONFIRMED] PRODUCT CREATED\n";
        $reply .= "========================================\n";
        $reply .= "Item: {$catalogItem->name}\n";
        $reply .= "Price: {$formattedPrice}\n";
        $reply .= "Stock: {$stockDisplay}\n";
        $reply .= "Category: {$category}\n";
        $reply .= "Status: [ACTIVE IN CATALOG]\n";
        $reply .= '========================================';

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    /**
     * Restock command with zero emojis.
     */
    protected function handleRestockCommand(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $rawContent
    ): string {
        $cleaned = trim(preg_replace('/^\/restock\s*/i', '', $rawContent));
        $tokens = explode(' ', $cleaned);
        $qty = array_pop($tokens);
        $identifier = implode(' ', $tokens);

        if (! is_numeric($qty) || empty($identifier)) {
            $reply = "[ERROR] Invalid format.\nUsage: /restock [Item Name or ID] [Quantity]\nExample: /restock Nike Shoes 15";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $qty = max(0, (int) $qty);
        $item = is_numeric($identifier)
            ? BusinessCatalogItem::withoutGlobalScopes()->where('business_id', $business->id)->find($identifier)
            : BusinessCatalogItem::withoutGlobalScopes()->where('business_id', $business->id)
                ->where('name', 'like', "%{$identifier}%")
                ->first();

        if (! $item) {
            $reply = "[ERROR] Item '{$identifier}' not found in catalog.";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $status = ($qty === 0) ? 'unavailable' : 'available';
        $item->update([
            'stock_quantity' => $qty,
            'availability_status' => $status,
        ]);

        $statusTag = $qty === 0 ? '[OUT OF STOCK]' : '[IN STOCK]';

        $reply = "========================================\n";
        $reply .= "[CONFIRMED] STOCK UPDATED\n";
        $reply .= "========================================\n";
        $reply .= "Item: {$item->name}\n";
        $reply .= "New Stock Level: {$qty} units\n";
        $reply .= "Status: {$statusTag}\n";
        $reply .= '========================================';

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    /**
     * Price update command with zero emojis.
     */
    protected function handlePriceCommand(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $rawContent
    ): string {
        $cleaned = trim(preg_replace('/^\/price\s*/i', '', $rawContent));
        $tokens = explode(' ', $cleaned);
        $price = array_pop($tokens);
        $identifier = implode(' ', $tokens);

        if (! is_numeric($price) || empty($identifier)) {
            $reply = "[ERROR] Invalid format.\nUsage: /price [Item Name or ID] [New Price]\nExample: /price Nike Shoes 45000";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $price = (float) $price;
        $item = is_numeric($identifier)
            ? BusinessCatalogItem::withoutGlobalScopes()->where('business_id', $business->id)->find($identifier)
            : BusinessCatalogItem::withoutGlobalScopes()->where('business_id', $business->id)
                ->where('name', 'like', "%{$identifier}%")
                ->first();

        if (! $item) {
            $reply = "[ERROR] Item '{$identifier}' not found in catalog.";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $item->update(['price' => $price]);
        $formattedPrice = number_format($price, 2).' '.$item->currency;

        $reply = "========================================\n";
        $reply .= "[CONFIRMED] PRICE UPDATED\n";
        $reply .= "========================================\n";
        $reply .= "Item: {$item->name}\n";
        $reply .= "New Price: {$formattedPrice}\n";
        $reply .= '========================================';

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    /**
     * Link Telegram or messaging chat to store administrator account.
     * Usage: /admin_link [Password]
     */
    protected function handleAdminLinkCommand(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $rawContent
    ): string {
        $password = trim(preg_replace('/^\/admin_link\s*/i', '', $rawContent));

        if (empty($password)) {
            $reply = "[ERROR] Password required.\nUsage: /admin_link [Owner Password]\nExample: /admin_link MyPassword123";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $owner = User::where('business_id', $business->id)
            ->where('role', UserRole::Owner)
            ->first();

        if (! $owner || ! Hash::check($password, $owner->password)) {
            $reply = '[ERROR] Authentication failed. Invalid owner password.';
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        if ($channelType === ChannelType::Telegram) {
            $channel = BusinessChannel::withoutGlobalScopes()->where('business_id', $business->id)
                ->where('channel', ChannelType::Telegram)
                ->first();

            if ($channel) {
                $creds = $channel->credentials;
                $creds['admin_chat_id'] = (string) $channelUserId;
                $channel->credentials = $creds;
                $channel->save();
            }
        }

        $reply = "========================================\n";
        $reply .= "[CONFIRMED] TELEGRAM ADMIN LINKED\n";
        $reply .= "========================================\n";
        $reply .= "Store: {$business->name}\n";
        $reply .= "Admin: {$owner->name}\n";
        $reply .= "Account ID: {$channelUserId}\n";
        $reply .= "Status: AUTHORIZED STORE ADMINISTRATOR\n\n";
        $reply .= "You can now use:\n";
        $reply .= "- /dashboard\n";
        $reply .= "- /orders\n";
        $reply .= "- /dispatch [TrackingCode]\n";
        $reply .= "- /stock\n";
        $reply .= "- /restock [Item] [Qty]\n";
        $reply .= "- /price [Item] [Price]\n";
        $reply .= "- /add [Name], Price: [Val], Stock: [Qty]\n";
        $reply .= '========================================';

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    protected function sendDirectReply(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $text
    ): void {
        $channel = BusinessChannel::withoutGlobalScopes()->where('business_id', $business->id)
            ->where('channel', $channelType)
            ->first();

        if (! $channel) {
            return;
        }

        if ($channelType === ChannelType::Telegram) {
            $this->channelSender->sendTelegram($channel, $channelUserId, $text);
        } elseif ($channelType === ChannelType::Whatsapp) {
            $this->channelSender->sendWhatsapp($channel, $channelUserId, $text);
        } elseif ($channelType === ChannelType::WhatsappWeb) {
            $this->channelSender->sendWhatsappWeb($channel, $channelUserId, $text);
        }
    }
}
