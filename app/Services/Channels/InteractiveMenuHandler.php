<?php

namespace App\Services\Channels;

use App\Enums\ChannelType;
use App\Models\BusinessCatalogItem;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Http;

class InteractiveMenuHandler
{
    public function __construct(protected ChannelSender $sender) {}

    public function showCatalog(Conversation $conversation): void
    {
        $items = BusinessCatalogItem::where('business_id', $conversation->business_id)
            ->where('is_active', true)
            ->limit(8)
            ->get();

        if ($items->isEmpty()) {
            $this->sendText($conversation, 'Sorry, our catalog is currently empty. Please check back soon! 🙏');

            return;
        }

        $channel = $conversation->channel;

        if ($channel === ChannelType::Telegram) {
            $this->showCatalogTelegram($conversation, $items);
        } elseif (in_array($channel, [ChannelType::Whatsapp, ChannelType::WhatsappWeb])) {
            $this->showCatalogWhatsapp($conversation, $items);
        }
    }

    public function handleCallback(Conversation $conversation, Message $message, string $callbackData): void
    {
        parse_str($callbackData, $data);
        $action = $data['action'] ?? '';

        match ($action) {
            'add_cart' => $this->handleAddCart($conversation, $data['id'] ?? null),
            'checkout' => $this->handleCheckout($conversation),
            'catalog' => $this->showCatalog($conversation),
            'view_cart' => $this->handleViewCart($conversation),
            'ai_chat' => $this->sendText($conversation, "🤖 *AI Chat Mode*\nType anything and our assistant will help you!"),
            default => null,
        };
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function handleAddCart(Conversation $conversation, ?int $itemId): void
    {
        $item = BusinessCatalogItem::find($itemId);
        if (! $item) {
            $this->sendText($conversation, 'Sorry, that item is no longer available.');

            return;
        }

        $metadata = $conversation->metadata ?? [];
        $cart = $metadata['cart'] ?? ['items' => [], 'subtotal' => 0];
        $existingKey = null;

        foreach ($cart['items'] as $k => $cartItem) {
            if ($cartItem['id'] === $item->id) {
                $existingKey = $k;
                break;
            }
        }

        if ($existingKey !== null) {
            $cart['items'][$existingKey]['quantity']++;
        } else {
            $cart['items'][] = [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => 1,
            ];
        }

        $cart['subtotal'] += $item->price;
        $metadata['cart'] = $cart;
        $conversation->update(['metadata' => $metadata]);

        $text = "✅ *{$item->name}* added to cart!\n\n🛒 *Cart Total:* NGN ".number_format($cart['subtotal'], 2);

        $channel = $conversation->channel;

        if ($channel === ChannelType::Telegram) {
            $keyboard = ['inline_keyboard' => [[
                ['text' => '🛍️ Browse More', 'callback_data' => 'action=catalog'],
                ['text' => '🛒 View Cart',   'callback_data' => 'action=view_cart'],
                ['text' => '💳 Checkout',     'callback_data' => 'action=checkout'],
            ]]];
            $this->sender->sendTelegramWithMarkup($conversation->businessChannel, $this->getChatId($conversation), $text, $keyboard);
        } elseif (in_array($channel, [ChannelType::Whatsapp, ChannelType::WhatsappWeb])) {
            $this->sendWhatsappButtons($conversation, $text, [
                ['id' => 'action=catalog',   'title' => '🛍️ Browse More'],
                ['id' => 'action=view_cart', 'title' => '🛒 View Cart'],
                ['id' => 'action=checkout',  'title' => '💳 Checkout'],
            ]);
        }
    }

    private function handleViewCart(Conversation $conversation): void
    {
        $cart = ($conversation->metadata ?? [])['cart'] ?? ['items' => [], 'subtotal' => 0];

        if (empty($cart['items'])) {
            $this->sendText($conversation, "🛒 Your cart is empty!\n\nType /catalog to browse products.");

            return;
        }

        $lines = ["🛒 *Your Cart*\n"];
        foreach ($cart['items'] as $cartItem) {
            $subtotal = $cartItem['price'] * $cartItem['quantity'];
            $lines[] = "• {$cartItem['quantity']}x {$cartItem['name']} — NGN ".number_format($subtotal, 2);
        }
        $lines[] = "\n*Total: NGN ".number_format($cart['subtotal'], 2).'*';

        $text = implode("\n", $lines);
        $channel = $conversation->channel;

        if ($channel === ChannelType::Telegram) {
            $keyboard = ['inline_keyboard' => [[
                ['text' => '💳 Checkout',    'callback_data' => 'action=checkout'],
                ['text' => '🛍️ Keep Shopping', 'callback_data' => 'action=catalog'],
            ]]];
            $this->sender->sendTelegramWithMarkup($conversation->businessChannel, $this->getChatId($conversation), $text, $keyboard);
        } elseif (in_array($channel, [ChannelType::Whatsapp, ChannelType::WhatsappWeb])) {
            $this->sendWhatsappButtons($conversation, $text, [
                ['id' => 'action=checkout', 'title' => '💳 Checkout'],
                ['id' => 'action=catalog',  'title' => '🛍️ Keep Shopping'],
            ]);
        }
    }

    private function handleCheckout(Conversation $conversation): void
    {
        $cart = ($conversation->metadata ?? [])['cart'] ?? ['items' => [], 'subtotal' => 0];

        if (empty($cart['items'])) {
            $this->sendText($conversation, '🛒 Your cart is empty! Type /catalog to browse products.');

            return;
        }

        $text = "💳 *Ready to Checkout!*\nYour total is *NGN ".number_format($cart['subtotal'], 2)."*.\n\nPlease type your 📍 *Delivery Address* and our AI assistant will finalize your order.";
        $this->sendText($conversation, $text);
    }

    // ─── Channel-Specific Senders ─────────────────────────────────────────────

    private function showCatalogTelegram(Conversation $conversation, $items): void
    {
        $text = "🛍️ *Our Catalog*\n\nTap any item below to add it to your cart:";
        $keyboard = [];

        foreach ($items as $item) {
            $price = number_format($item->price, 2);
            $shortName = mb_substr($item->name, 0, 30);
            $keyboard[] = [[
                'text' => "🛒 {$shortName} — NGN {$price}",
                'callback_data' => "action=add_cart&id={$item->id}",
            ]];
        }

        $keyboard[] = [
            ['text' => '🛒 View Cart',  'callback_data' => 'action=view_cart'],
            ['text' => '🤖 Ask AI',     'callback_data' => 'action=ai_chat'],
        ];

        $this->sender->sendTelegramWithMarkup(
            $conversation->businessChannel,
            $this->getChatId($conversation),
            $text,
            ['inline_keyboard' => $keyboard]
        );
    }

    private function showCatalogWhatsapp(Conversation $conversation, $items): void
    {
        $rows = [];
        foreach ($items as $item) {
            $price = number_format($item->price, 2);
            $rows[] = [
                'id' => "action=add_cart&id={$item->id}",
                'title' => mb_substr($item->name, 0, 24),
                'description' => "NGN {$price}",
            ];
        }

        $this->sendWhatsappList(
            $conversation,
            "🛍️ *Our Catalog*\n\nTap the button to browse and add items to your cart.",
            'View Products 🛍️',
            'Available Products',
            $rows
        );
    }

    private function sendWhatsappList(Conversation $conversation, string $bodyText, string $buttonText, string $sectionTitle, array $rows): void
    {
        $channel = $conversation->businessChannel;
        $to = $this->getChatId($conversation);

        if (! $to) {
            return;
        }

        $phoneId = $channel->credentials['phone_number_id'] ?? null;
        $token = $channel->credentials['access_token'] ?? null;

        if (! $phoneId || ! $token) {
            // Fallback for WhatsApp Web / QR channels — send plain text
            $text = $bodyText."\n\n".collect($rows)->map(fn ($r) => "• {$r['title']} — {$r['description']}")->implode("\n")."\n\nType the product name to add it to your cart.";
            $this->sender->sendWhatsappQr($channel, $to, $text);

            return;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $bodyText],
                'action' => [
                    'button' => $buttonText,
                    'sections' => [[
                        'title' => $sectionTitle,
                        'rows' => $rows,
                    ]],
                ],
            ],
        ];

        Http::withToken($token)
            ->timeout(20)
            ->post("https://graph.facebook.com/v22.0/{$phoneId}/messages", $payload);
    }

    private function sendWhatsappButtons(Conversation $conversation, string $bodyText, array $buttons): void
    {
        $channel = $conversation->businessChannel;
        $to = $this->getChatId($conversation);

        if (! $to) {
            return;
        }

        $phoneId = $channel->credentials['phone_number_id'] ?? null;
        $token = $channel->credentials['access_token'] ?? null;

        if (! $phoneId || ! $token) {
            // Fallback for WhatsApp Web/QR
            $btns = collect($buttons)->pluck('title')->implode(' | ');
            $this->sender->sendWhatsappQr($channel, $to, $bodyText."\n\n_Options: {$btns}_");

            return;
        }

        // WhatsApp only supports 3 buttons max
        $waButtons = collect(array_slice($buttons, 0, 3))->map(fn ($b) => [
            'type' => 'reply',
            'reply' => ['id' => $b['id'], 'title' => mb_substr($b['title'], 0, 20)],
        ])->values()->all();

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'action' => ['buttons' => $waButtons],
            ],
        ];

        Http::withToken($token)
            ->timeout(20)
            ->post("https://graph.facebook.com/v22.0/{$phoneId}/messages", $payload);
    }

    // ─── Utility ──────────────────────────────────────────────────────────────

    private function sendText(Conversation $conversation, string $text): void
    {
        $this->sender->send($conversation, $text, null, 'ai');
    }

    private function getChatId(Conversation $conversation): ?string
    {
        $identity = $conversation->customer->identities()
            ->where('channel', $conversation->channel)
            ->first();

        return $identity?->channel_user_id ?? $conversation->customer->phone;
    }
}
