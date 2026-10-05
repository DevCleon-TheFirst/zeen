<?php

namespace App\Services\Catalog;

use App\Enums\ChannelType;
use App\Models\Business;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessChannel;
use App\Services\Channels\ChannelSender;

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

        return str_starts_with($trimmed, '/stock')
            || str_starts_with($trimmed, '/inventory')
            || str_starts_with($trimmed, '/addproduct')
            || str_starts_with($trimmed, '/add')
            || str_starts_with($trimmed, 'add product:')
            || str_starts_with($trimmed, 'add:')
            || str_starts_with($trimmed, '/restock')
            || str_starts_with($trimmed, '/price');
    }

    /**
     * Handle the admin command and send a response back to the channel.
     */
    public function handle(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        ?string $content,
        ?array $media = null
    ): ?string {
        if (! $content) {
            return null;
        }

        $trimmed = trim($content);
        $lower = strtolower($trimmed);

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

    protected function handleStockQuery(Business $business, ChannelType $channelType, string $channelUserId): string
    {
        $items = BusinessCatalogItem::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->take(15)
            ->get();

        if ($items->isEmpty()) {
            $reply = "📦 *Inventory Status*\nYour catalog is currently empty.\n\n💡 To add a product, upload a photo with caption:\n`Add: Product Name, Price: 5000, Stock: 10`";
        } else {
            $reply = "📦 *Current Inventory (Top {$items->count()} Items)*:\n\n";
            foreach ($items as $index => $item) {
                $num = $index + 1;
                $stock = $item->stock_quantity !== null ? "{$item->stock_quantity} left" : 'Uncounted';
                $price = $item->price ? number_format($item->price, 2).' '.$item->currency : 'Free';
                $statusIcon = ($item->stock_quantity !== null && $item->stock_quantity <= 0) ? '🔴' : '🟢';

                $reply .= "{$statusIcon} *{$num}. {$item->name}*\n";
                $reply .= "   💰 {$price} | 📦 {$stock}\n";
            }
            $reply .= "\n💡 *Quick Commands*:\n• `/restock [Item Name] [New Stock]`\n• `/price [Item Name] [New Price]`\n• Send photo with caption `Add: [Name], Price: [Amount]`";
        }

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    protected function handleProductUpload(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $rawContent,
        ?array $media = null
    ): string {
        // Strip command prefix
        $cleaned = preg_replace('/^(\/addproduct|\/add|add product:|add:)\s*/i', '', $rawContent);

        // Expected format: Name, Price: 5000, Stock: 10, Category: Shoes
        // Or simple comma separation: Nike Shoes, 5000, 10
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

        // Fallback positional parsing if keywords were not used
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

        $reply = "✅ *Product Added Successfully!*\n\n";
        $reply .= "🛍️ *Item*: {$catalogItem->name}\n";
        $reply .= "💰 *Price*: {$formattedPrice}\n";
        $reply .= "📦 *Stock*: {$stockDisplay}\n";
        $reply .= "📂 *Category*: {$category}\n";
        if (! empty($images)) {
            $reply .= "📸 *Photo Attached*: Yes (1 image)\n";
        }
        $reply .= "\n✨ This item is now live in your catalog and can be purchased by customers or recommended by AI!";

        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    protected function handleRestockCommand(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $rawContent
    ): string {
        $cleaned = trim(preg_replace('/^\/restock\s*/i', '', $rawContent));
        // format: /restock [Name or ID] [Qty]
        $tokens = explode(' ', $cleaned);
        $qty = array_pop($tokens);
        $identifier = implode(' ', $tokens);

        if (! is_numeric($qty) || empty($identifier)) {
            $reply = "⚠️ *Invalid Format*\nUse: `/restock [Item Name or ID] [Quantity]`\nExample: `/restock Nike Shoes 15`";
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
            $reply = "❌ Item '{$identifier}' not found in your catalog.";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $status = ($qty === 0) ? 'unavailable' : 'available';
        $item->update([
            'stock_quantity' => $qty,
            'availability_status' => $status,
        ]);

        $reply = "✅ *Stock Updated!*\n🛍️ *{$item->name}* is now set to *{$qty} units* in stock.";
        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

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
            $reply = "⚠️ *Invalid Format*\nUse: `/price [Item Name or ID] [New Price]`\nExample: `/price Nike Shoes 45000`";
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
            $reply = "❌ Item '{$identifier}' not found in your catalog.";
            $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

            return $reply;
        }

        $item->update(['price' => $price]);
        $formattedPrice = number_format($price, 2).' '.$item->currency;

        $reply = "✅ *Price Updated!*\n🛍️ *{$item->name}* price is now set to *{$formattedPrice}*.";
        $this->sendDirectReply($business, $channelType, $channelUserId, $reply);

        return $reply;
    }

    protected function sendDirectReply(
        Business $business,
        ChannelType $channelType,
        string $channelUserId,
        string $text
    ): void {
        $channel = BusinessChannel::where('business_id', $business->id)
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
