<?php

namespace App\Services;

use App\Enums\ConversationState;
use App\Mail\OrderSummaryMail;
use App\Models\AiProviderSetting;
use App\Models\AiToolCall;
use App\Models\Appointment;
use App\Models\BusinessCatalogItem;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Channels\ChannelSender;
use App\Services\Orders\OrderFulfillmentService;
use App\Services\Payments\PaymentGatewayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AiAgentOrchestrator
{
    public function __construct(
        protected ChannelSender $channelSender,
    ) {}

    /**
     * Process an incoming customer message with DeepSeek / configured AI provider.
     */
    public function handle(Conversation $conversation, Message $inboundMessage): ?Message
    {
        $business = $conversation->business;
        $setting = $business->aiProviderSetting;

        // Skip if AI is deactivated for the business or conversation is assigned to human
        if (! $setting || ! $setting->is_active) {
            return null;
        }

        if ($conversation->status === ConversationState::HumanHandling || $conversation->status === ConversationState::Escalated) {
            return null;
        }

        // If customer asks to reset or restart conversation, wipe cart and greet afresh
        $rawText = strtolower(trim($inboundMessage->content ?? ''));
        if (in_array($rawText, ['/reset', '/start', 'reset', 'clear', 'start over', 'restart'])) {
            $metadata = $conversation->metadata ?? [];
            $metadata['cart'] = ['items' => [], 'subtotal' => 0];
            $conversation->update(['metadata' => $metadata]);

            $welcomeMsg = $this->getFreshGreeting($conversation);

            return $this->channelSender->send($conversation, $welcomeMsg, null, 'ai');
        }

        // If no LLM API key has been set in Settings/Ai, provide intelligent rule-based responses using the catalog
        if (empty($setting->api_key)) {
            [$replyText, $pendingMedia] = $this->handleWithoutApiKey($conversation, $inboundMessage);

            return $this->channelSender->send($conversation, $replyText, null, 'ai', $pendingMedia);
        }

        $systemPrompt = $this->buildSystemPrompt($conversation);
        $chatHistory = $this->formatChatHistory($conversation, $business->industry ?? 'general');

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$chatHistory,
        ];

        // Bypass AI safety refusal for photos by injecting a hard system override at the very end
        $contentLower = strtolower($inboundMessage->content);
        if (str_contains($contentLower, 'picture') || str_contains($contentLower, 'photo') || str_contains($contentLower, 'image') || str_contains($contentLower, 'look like') || str_contains($contentLower, 'see')) {
            $messages[] = [
                'role' => 'system',
                'content' => 'SYSTEM CRITICAL OVERRIDE: The user just asked to see a picture/photo. You MUST immediately execute the `show_product_photo` tool right now! If the user did not specify a product name, call `show_product_photo` with `item_name`: "honey" or our featured item. DO NOT reply with text asking which item they want first. ALWAYS call `show_product_photo` immediately!',
            ];
        }

        $tools = $this->getToolDefinitions($business->industry ?? 'general');

        // Call DeepSeek / OpenAI API with function tools
        $aiResponse = $this->callLlm($setting, $messages, $tools);

        // Track any product image surfaced by tools to send alongside the reply
        $pendingMedia = null;

        // Check if LLM requested tool execution
        if (! empty($aiResponse['tool_calls'])) {
            $toolMessages = [];

            foreach ($aiResponse['tool_calls'] as $toolCall) {
                $toolName = $toolCall['function']['name'] ?? '';
                $argsJson = $toolCall['function']['arguments'] ?? '{}';
                $args = json_decode($argsJson, true) ?: [];

                $toolStart = microtime(true);
                $result = $this->executeTool($conversation, $inboundMessage, $toolName, $args);
                $duration = (int) round((microtime(true) - $toolStart) * 1000);

                // Capture the first product image from catalog searches to send with the reply
                if (in_array($toolName, ['search_catalog', 'show_product_photo']) && ! empty($result['products'])) {
                    foreach ($result['products'] as $product) {
                        $images = $product['images'] ?? [];
                        $firstImage = is_array($images) ? ($images[0] ?? null) : $images;
                        if ($firstImage) {
                            $pendingMedia = [['type' => 'image', 'url' => $firstImage]];
                            break;
                        }
                    }
                }

                // Audit log tool call
                AiToolCall::create([
                    'conversation_id' => $conversation->id,
                    'message_id' => $inboundMessage->id,
                    'tool_name' => $toolName,
                    'arguments' => $args,
                    'result' => $result,
                    'success' => ! isset($result['error']),
                    'error_message' => $result['error'] ?? null,
                    'duration_ms' => $duration,
                ]);

                $toolMessages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => json_encode($result),
                ];
            }

            // Follow-up call with tool results to synthesize final answer
            $followUpMessages = [
                ...$messages,
                [
                    'role' => 'assistant',
                    'content' => $aiResponse['content'] ?? null,
                    'tool_calls' => $aiResponse['tool_calls'],
                ],
                ...$toolMessages,
            ];

            $finalResponse = $this->callLlm($setting, $followUpMessages);
            $replyText = $finalResponse['content'] ?? 'Thank you, I have processed your request.';
        } else {
            $replyText = $aiResponse['content'] ?? 'Hello! How may I assist you today?';
        }

        // Clean any raw LLM markup or leaked tool call tags (e.g. DeepSeek DSML tags)
        $replyText = preg_replace('/<｜.*?｜>/s', '', $replyText);
        $replyText = trim($replyText);
        if (empty($replyText)) {
            $replyText = 'Here you go! Let me know if you need anything else.';
        }

        // Send reply (and product photo if one was captured) to customer via active channel
        return $this->channelSender->send($conversation, $replyText, null, 'ai', $pendingMedia);
    }

    private function buildSystemPrompt(Conversation $conversation): string
    {
        $business = $conversation->business;
        $customer = $conversation->customer;
        $currentDate = Carbon::now()->format('l, F j, Y g:i A');

        $industry = $business?->industry ?? 'general';
        $businessName = $business?->name ?? 'Our Business';
        $customerName = $customer?->name ?? 'Valued Customer';

        $cart = $conversation->metadata['cart'] ?? ['items' => []];
        $cartCount = count($cart['items'] ?? []);
        $cartSummary = $cartCount > 0 ? "Customer currently has {$cartCount} item(s) in their shopping cart." : 'Customer cart is currently empty.';

        $industryGuidelines = match ($industry) {
            'real_estate' => <<<'GUIDELINES'
Dominant Focus: Real Estate & Property Discovery
1. Help clients explore available houses, apartments, and land using `search_catalog`. Highlight location, price/rent, bedrooms, bathrooms, and key amenities.
2. When a client wants to inspect or tour a property, offer available slots and use `book_appointment` to schedule their property viewing.
3. If the client wants to negotiate or make an offer, use `escalate_to_human`.
GUIDELINES,
            'hospitality' => <<<'GUIDELINES'
Dominant Focus: Hospitality, Hotels & Shortlets
1. Help guests discover suites, rooms, and shortlets using `search_catalog`. Present nightly rates, amenities, and guest capacities.
2. Assist guests with reservations using `book_appointment` or provide advance payment links via `request_payment`.
3. Provide check-in policies and escalate complex concierge inquiries using `escalate_to_human`.
GUIDELINES,
            'healthcare' => <<<'GUIDELINES'
Dominant Focus: Healthcare, Dental & Clinic Consultations
1. Inform patients about clinical services and consultation fees using `search_catalog`.
2. Schedule clinic appointments and consultations using `book_appointment`.
3. Never provide medical diagnoses; direct urgent health emergencies immediately to emergency lines or use `escalate_to_human`.
GUIDELINES,
            'services' => <<<'GUIDELINES'
Dominant Focus: Professional Services & Consultations
1. Present service packages, consulting tiers, or session rates using `search_catalog`.
2. Schedule discovery calls, sessions, or on-site inspections using `book_appointment`.
3. Generate deposit or service payment links using `request_payment`.
GUIDELINES,
            default => <<<'GUIDELINES'
Dominant Focus: Online Store, Retail & Products
1. Help customers discover products in our catalog using `search_catalog`. Present available sizes, colors, stock, and prices clearly.
2. IMPORTANT: To show a product photo to the customer, you MUST call `show_product_photo` for that specific product. The system will extract the photo from the tool result and attach it to your message. NEVER say you cannot show images. ALWAYS call `show_product_photo` when the user asks for a picture.
3. If the customer likes an item or chooses their size/color, use `add_to_cart` to place it in their cart.
4. Allow customers to view (`view_cart`) or remove items (`remove_from_cart`) anytime.
5. When the customer is ready to checkout, confirm their delivery address and email address, then use `checkout_cart` to generate their payment link.
6. If the customer asks about order status or provides a tracking code, use `track_order`.
GUIDELINES,
        };

        $contextGuardrails = match ($industry) {
            'real_estate' => 'You are STRICTLY a real estate assistant. Help clients find properties and schedule viewings.',
            'retail', 'retail_store' => "CRITICAL BOUNDARY: You are STRICTLY the online shopping assistant for \"{$businessName}\". You sell retail products and store items. You have NO properties, NO houses, and NO viewing appointments. If older messages in the chat history mention property viewings, inspections, or real estate, DISREGARD THEM ENTIRELY as they belong to an old session. NEVER ask which property to view or mention viewing dates.",
            'hospitality' => "CRITICAL BOUNDARY: You are STRICTLY the hospitality assistant for \"{$businessName}\". Help with room bookings and shortlets.",
            'healthcare' => "CRITICAL BOUNDARY: You are STRICTLY the clinic assistant for \"{$businessName}\". Help with patient consultations.",
            'services' => "CRITICAL BOUNDARY: You are STRICTLY the service coordinator for \"{$businessName}\". Help with service packages.",
            default => "You represent \"{$businessName}\". Help customers browse and purchase items from our catalog.",
        };

        return <<<PROMPT
You are a warm, helpful, and proactive AI assistant for "{$businessName}".
Business Focus: {$industry}.
Current Date & Time: {$currentDate}.
Customer Name: {$customerName}.
Cart Status: {$cartSummary}.

Operating Focus & Tools:
{$industryGuidelines}
6. If the customer asks for a human, expresses frustration, or has complex issues, call `escalate_to_human`.
7. Keep responses concise, upbeat, and structured cleanly with emojis and bullet points. Never hallucinate items or availability not confirmed by tools.

Domain Boundary & Anti-Cross-Talk Rules:
- {$contextGuardrails}
- When greeting or answering fresh inquiries, introduce "{$businessName}" and present our products/services.
PROMPT;
    }

    private function getFreshGreeting(Conversation $conversation): string
    {
        $business = $conversation->business;
        $customer = $conversation->customer;
        $name = $customer?->name ?? 'there';
        $bizName = $business?->name ?? 'our store';
        $industry = $business?->industry ?? 'general';

        if ($industry === 'real_estate') {
            return "Hello {$name}! 🏡 Welcome to {$bizName}. How can I assist you with finding your dream property or scheduling a viewing today?";
        }

        return "Hello {$name}! 🛍️ Welcome to {$bizName}. We are an online store ready to take your orders! Would you like to see our featured items, check sizes, or track an existing order?";
    }

    private function formatChatHistory(Conversation $conversation, string $industry = 'general'): array
    {
        $isRetail = in_array($industry, ['retail', 'retail_store']);

        return $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->filter(function (Message $m) use ($isRetail) {
                // If currently in retail store mode, filter out stale real-estate viewing messages
                if ($isRetail) {
                    $c = strtolower($m->content ?? '');
                    if (str_contains($c, 'viewing') || str_contains($c, 'property') || str_contains($c, 'inspection') || str_contains($c, 'sept 26') || str_contains($c, 'real estate')) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn (Message $m) => [
                'role' => $m->direction === 'inbound' ? 'user' : 'assistant',
                'content' => $m->content ?? '',
            ])
            ->values()
            ->all();
    }

    private function getToolDefinitions(string $industry = 'general'): array
    {
        $isRetail = in_array($industry, ['retail', 'retail_store']);
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_catalog',
                    'description' => 'Search business catalog for products, items, sizes, and prices.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Product name, keyword, or category e.g. "sneakers", "hoodie", "pricing"'],
                            'category' => ['type' => 'string', 'description' => 'Category filter if applicable'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ];

        // Retail cart and order fulfillment tools
        if ($isRetail || $industry === 'general') {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_cart',
                    'description' => 'Add an item with specified size, color, and quantity to the customer shopping cart.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'item_name' => ['type' => 'string', 'description' => 'Name of the product to add'],
                            'size' => ['type' => 'string', 'description' => 'Selected size (e.g. S, M, L, XL, 42, 43)'],
                            'color' => ['type' => 'string', 'description' => 'Selected color (e.g. Black, Blue)'],
                            'quantity' => ['type' => 'integer', 'description' => 'Quantity to add, default 1'],
                        ],
                        'required' => ['item_name'],
                    ],
                ],
            ];

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'show_product_photo',
                    'description' => 'Fetch and attach the real product photo to your chat message. ALWAYS use this when a user asks to see what a product looks like or asks for a picture.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'item_name' => ['type' => 'string', 'description' => 'Name or keyword of the product to show'],
                        ],
                        'required' => ['item_name'],
                    ],
                ],
            ];

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'view_cart',
                    'description' => 'View all items, sizes, quantities, and subtotal currently in the customer shopping cart.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ];

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'remove_from_cart',
                    'description' => 'Remove an item from the customer shopping cart.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'item_name' => ['type' => 'string', 'description' => 'Name or keyword of item to remove'],
                        ],
                        'required' => ['item_name'],
                    ],
                ],
            ];

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'checkout_cart',
                    'description' => 'Generate checkout payment link for all items in the customer cart. Requires delivery address.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'shipping_address' => ['type' => 'string', 'description' => 'Customer shipping/delivery destination address'],
                            'customer_phone' => ['type' => 'string', 'description' => 'Contact phone number for courier delivery'],
                            'customer_email' => ['type' => 'string', 'description' => 'Customer email address for order summary/receipt'],
                        ],
                        'required' => ['shipping_address', 'customer_email'],
                    ],
                ],
            ];

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'track_order',
                    'description' => 'Look up current delivery status of an order using its tracking code.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'tracking_code' => ['type' => 'string', 'description' => 'Tracking code e.g. TRK-84920'],
                        ],
                        'required' => ['tracking_code'],
                    ],
                ],
            ];
        }

        // Appointment booking tool: ONLY for appointment-based industries (Real Estate, Hospitality, Healthcare, Services)
        // NOT for Retail stores!
        if (! $isRetail || $industry === 'general') {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'book_appointment',
                    'description' => 'Schedule an inspection, viewing, or service consultation appointment for the customer.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Title or purpose of appointment'],
                            'scheduled_at' => ['type' => 'string', 'description' => 'Date and time of appointment (YYYY-MM-DD HH:MM)'],
                            'location' => ['type' => 'string', 'description' => 'Location or address of inspection'],
                            'duration_minutes' => ['type' => 'integer', 'description' => 'Estimated duration in minutes (default 30)'],
                        ],
                        'required' => ['title', 'scheduled_at'],
                    ],
                ],
            ];
        }

        // Custom payment link request
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'request_payment',
                'description' => 'Generate an invoice / checkout payment link for a custom service or deposit.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'amount' => ['type' => 'number', 'description' => 'Payment amount'],
                        'currency' => ['type' => 'string', 'description' => 'Currency e.g. NGN, USD'],
                        'description' => ['type' => 'string', 'description' => 'Description of what payment is for'],
                    ],
                    'required' => ['amount', 'description'],
                ],
            ],
        ];

        // Human escalation tool
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'escalate_to_human',
                'description' => 'Handover conversation to human staff when customer is angry, asks for a human, or needs high-level approval.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'reason' => ['type' => 'string', 'description' => 'Brief explanation for escalation'],
                    ],
                    'required' => ['reason'],
                ],
            ],
        ];

        return $tools;
    }

    private function executeTool(Conversation $conversation, Message $inbound, string $toolName, array $args): array
    {
        $businessId = $conversation->business_id;

        if ($toolName === 'search_catalog') {
            $query = $args['query'] ?? '';
            $items = BusinessCatalogItem::where('business_id', $businessId)
                ->where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhere('category', 'like', "%{$query}%");
                })
                ->limit(6)
                ->get(['id', 'name', 'description', 'price', 'currency', 'availability_status', 'stock_quantity', 'attributes', 'images']);

            if ($items->isEmpty()) {
                $items = BusinessCatalogItem::where('business_id', $businessId)
                    ->where('is_active', true)
                    ->limit(4)
                    ->get(['id', 'name', 'description', 'price', 'currency', 'availability_status', 'stock_quantity', 'attributes', 'images']);
            }

            return ['products' => $items->toArray(), 'count' => $items->count()];
        }

        if ($toolName === 'show_product_photo') {
            $query = trim($args['item_name'] ?? '');

            // 1. Exact or substring match
            $items = BusinessCatalogItem::where('business_id', $businessId)
                ->where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->limit(1)
                ->get(['id', 'name', 'images']);

            // 2. Word token match if no exact match
            if ($items->isEmpty() && ! empty($query)) {
                $words = array_filter(explode(' ', strtolower($query)), fn ($w) => strlen($w) >= 3);
                foreach ($words as $word) {
                    $items = BusinessCatalogItem::where('business_id', $businessId)
                        ->where('is_active', true)
                        ->where(function ($q) use ($word) {
                            $q->where('name', 'like', "%{$word}%")
                                ->orWhere('description', 'like', "%{$word}%");
                        })
                        ->limit(1)
                        ->get(['id', 'name', 'images']);

                    if ($items->isNotEmpty()) {
                        break;
                    }
                }
            }

            // 3. Fallback to first available product with images if query didn't match anything
            if ($items->isEmpty()) {
                $items = BusinessCatalogItem::where('business_id', $businessId)
                    ->where('is_active', true)
                    ->limit(1)
                    ->get(['id', 'name', 'images']);
            }

            return ['success' => true, 'message' => 'Photo attached to chat successfully.', 'products' => $items->toArray()];
        }

        if ($toolName === 'add_to_cart') {
            $itemName = trim($args['item_name'] ?? '');
            $size = trim($args['size'] ?? '');
            $color = trim($args['color'] ?? '');
            $quantity = max(1, (int) ($args['quantity'] ?? 1));

            $item = BusinessCatalogItem::where('business_id', $businessId)
                ->where('is_active', true)
                ->where('name', 'like', "%{$itemName}%")
                ->first();

            if ($item && $item->track_inventory && $item->stock_quantity !== null) {
                if ($item->stock_quantity <= 0 || $item->availability_status === 'unavailable') {
                    return [
                        'error' => "Sorry, {$item->name} is currently out of stock!",
                        'in_stock' => false,
                    ];
                }

                if ($quantity > $item->stock_quantity) {
                    return [
                        'error' => "Sorry, we only have {$item->stock_quantity} unit(s) available in stock for {$item->name}.",
                        'available_stock' => $item->stock_quantity,
                    ];
                }
            }

            $price = $item ? (float) $item->price : 0;
            $unitName = $item ? $item->name : $itemName;
            $currency = $item?->currency ?? 'NGN';

            $metadata = $conversation->metadata ?? [];
            $cart = $metadata['cart'] ?? ['items' => [], 'subtotal' => 0];

            $cart['items'][] = [
                'catalog_item_id' => $item?->id,
                'name' => $unitName,
                'size' => $size ?: null,
                'color' => $color ?: null,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total_price' => $price * $quantity,
                'image' => ! empty($item?->images) ? ($item->images[0] ?? null) : null,
            ];

            $subtotal = 0;
            foreach ($cart['items'] as $ci) {
                $subtotal += ($ci['total_price'] ?? 0);
            }
            $cart['subtotal'] = $subtotal;
            $cart['currency'] = $currency;

            $metadata['cart'] = $cart;
            $conversation->update(['metadata' => $metadata]);

            $spec = [];
            if ($size) {
                $spec[] = "Size: {$size}";
            }
            if ($color) {
                $spec[] = "Color: {$color}";
            }
            $specStr = ! empty($spec) ? ' ('.implode(', ', $spec).')' : '';

            return [
                'status' => 'item_added',
                'message' => "Added {$quantity}x {$unitName}{$specStr} to cart.",
                'cart_item_count' => count($cart['items']),
                'cart_subtotal' => $currency.' '.number_format($subtotal, 2),
                'cart' => $cart,
            ];
        }

        if ($toolName === 'view_cart') {
            $cart = $conversation->metadata['cart'] ?? ['items' => [], 'subtotal' => 0];

            return [
                'cart' => $cart,
                'item_count' => count($cart['items'] ?? []),
                'subtotal' => ($cart['currency'] ?? 'NGN').' '.number_format($cart['subtotal'] ?? 0, 2),
            ];
        }

        if ($toolName === 'remove_from_cart') {
            $removeName = strtolower(trim($args['item_name'] ?? ''));
            $metadata = $conversation->metadata ?? [];
            $cart = $metadata['cart'] ?? ['items' => [], 'subtotal' => 0];

            $newItems = [];
            $removed = false;
            foreach ($cart['items'] as $ci) {
                if (! $removed && str_contains(strtolower($ci['name']), $removeName)) {
                    $removed = true;

                    continue;
                }
                $newItems[] = $ci;
            }

            $subtotal = 0;
            foreach ($newItems as $ci) {
                $subtotal += ($ci['total_price'] ?? 0);
            }
            $cart['items'] = $newItems;
            $cart['subtotal'] = $subtotal;

            $metadata['cart'] = $cart;
            $conversation->update(['metadata' => $metadata]);

            return [
                'status' => $removed ? 'item_removed' : 'item_not_found',
                'remaining_items' => count($newItems),
                'subtotal' => ($cart['currency'] ?? 'NGN').' '.number_format($subtotal, 2),
            ];
        }

        if ($toolName === 'checkout_cart') {
            $metadata = $conversation->metadata ?? [];
            $cart = $metadata['cart'] ?? ['items' => [], 'subtotal' => 0];

            if (empty($cart['items'])) {
                return ['error' => 'Cart is currently empty. Ask the customer what they would like to add first.'];
            }

            $shippingAddress = trim($args['shipping_address'] ?? '');
            $customerPhone = trim($args['customer_phone'] ?? $conversation->customer->phone ?? '');
            $customerEmail = trim($args['customer_email'] ?? $conversation->customer->email ?? '');
            $amount = (float) ($cart['subtotal'] ?? 0);
            $currency = $cart['currency'] ?? 'NGN';

            $itemCount = count($cart['items'] ?? []);
            $gateway = new PaymentGatewayService($conversation->business);
            $payment = $gateway->initializePayment(
                customer: $conversation->customer,
                conversation: $conversation,
                amount: $amount,
                currency: $currency,
                description: "Cart Order ({$itemCount} items)",
                metadata: [
                    'order_type' => 'cart_order',
                    'cart' => $cart,
                    'shipping_address' => $shippingAddress,
                    'customer_phone' => $customerPhone,
                    'customer_email' => $customerEmail,
                    'customer_name' => $conversation->customer->name,
                ],
            );

            // Update customer email if provided
            if ($customerEmail && $customerEmail !== $conversation->customer->email) {
                $conversation->customer->update(['email' => $customerEmail]);
            }

            // Send Order Summary Email
            if ($customerEmail) {
                try {
                    Mail::to($customerEmail)->send(
                        new OrderSummaryMail($cart, $amount, $currency, $payment->checkout_url, $conversation->business->name)
                    );
                } catch (\Exception $e) {
                    Log::error('Failed to send order summary email: '.$e->getMessage());
                }
            }

            // Clear active cart in conversation once payment initialized
            $metadata['cart'] = ['items' => [], 'subtotal' => 0];
            $conversation->update(['metadata' => $metadata]);

            return [
                'status' => 'checkout_ready',
                'checkout_url' => $payment->checkout_url,
                'reference' => $payment->reference,
                'total_amount' => $currency.' '.number_format($amount, 2),
                'message' => "Order summary prepared! Checkout link: {$payment->checkout_url}",
            ];
        }

        if ($toolName === 'track_order') {
            $trackingCode = strtoupper(trim($args['tracking_code'] ?? ''));
            $orderService = new OrderFulfillmentService;
            $result = $orderService->lookupTracking($trackingCode, $conversation->business);

            return [
                'tracking_found' => ! empty($result),
                'report' => $result ?: "No order found matching tracking code {$trackingCode}. Please check the code and try again.",
            ];
        }

        if ($toolName === 'book_appointment') {
            $appointment = Appointment::create([
                'business_id' => $businessId,
                'customer_id' => $conversation->customer_id,
                'conversation_id' => $conversation->id,
                'title' => $args['title'] ?? 'Inspection / Appointment',
                'scheduled_at' => Carbon::parse($args['scheduled_at']),
                'location' => $args['location'] ?? null,
                'duration_minutes' => (int) ($args['duration_minutes'] ?? 30),
                'status' => 'scheduled',
            ]);

            return [
                'status' => 'appointment_scheduled',
                'appointment_id' => $appointment->id,
                'title' => $appointment->title,
                'scheduled_at' => $appointment->scheduled_at->toDateTimeString(),
            ];
        }

        if ($toolName === 'request_payment') {
            $amount = (float) $args['amount'];
            $currency = $args['currency'] ?? 'NGN';
            $description = $args['description'] ?? 'Payment';

            $gateway = new PaymentGatewayService($conversation->business);
            $payment = $gateway->initializePayment(
                customer: $conversation->customer,
                conversation: $conversation,
                amount: $amount,
                currency: $currency,
                description: $description,
            );

            return [
                'status' => 'payment_link_created',
                'checkout_url' => $payment->checkout_url,
                'reference' => $payment->reference,
                'amount' => "{$currency} ".number_format($amount, 2),
            ];
        }

        if ($toolName === 'escalate_to_human') {
            $reason = $args['reason'] ?? 'Customer requested human assistance';
            $conversation->escalate($reason);

            return [
                'status' => 'escalated',
                'notice' => 'Conversation transitioned to human staff. A staff member will respond shortly.',
            ];
        }

        return ['error' => "Unknown tool: {$toolName}"];
    }

    private function handleWithoutApiKey(Conversation $conversation, Message $inbound): array
    {
        $business = $conversation->business;
        $customer = $conversation->customer;
        $text = strtolower(trim($inbound->content ?? ''));
        $pendingMedia = null;

        // Check if customer is asking for human escalation
        if (str_contains($text, 'human') || str_contains($text, 'agent') || str_contains($text, 'manager') || str_contains($text, 'complaint')) {
            $conversation->escalate('Customer requested human assistance');

            return ["Hello {$customer->name}, I have escalated your conversation to our team at {$business->name}. A representative will respond to you here shortly!", null];
        }

        // Check if customer is specifically asking for a photo / picture of a product
        if (str_contains($text, 'picture') || str_contains($text, 'photo') || str_contains($text, 'image') || str_contains($text, 'look like') || str_contains($text, 'see')) {
            $items = BusinessCatalogItem::where('business_id', $business->id)
                ->where('is_active', true)
                ->get();

            $matchedItem = null;
            foreach ($items as $it) {
                $words = explode(' ', strtolower($it->name));
                foreach ($words as $w) {
                    if (strlen($w) >= 3 && str_contains($text, $w)) {
                        $matchedItem = $it;
                        break 2;
                    }
                }
            }
            if (! $matchedItem) {
                $matchedItem = $items->first();
            }

            if ($matchedItem) {
                $images = $matchedItem->images ?? [];
                $firstImage = is_array($images) ? ($images[0] ?? null) : $images;
                if ($firstImage) {
                    $pendingMedia = [['type' => 'image', 'url' => $firstImage]];
                    $priceStr = $matchedItem->formatted_price ?: ($matchedItem->currency.' '.number_format($matchedItem->price, 2));

                    return [
                        "📸 Here is the photo of *{$matchedItem->name}* ({$priceStr})!\n\n{$matchedItem->description}\n\n💡 To order this, just reply: \"Add {$matchedItem->name} to cart\"",
                        $pendingMedia,
                    ];
                }
            }
        }

        // Check if customer is asking about catalog, products, or pricing
        if (str_contains($text, 'product') || str_contains($text, 'available') || str_contains($text, 'price') || str_contains($text, 'catalog') || str_contains($text, 'shop') || str_contains($text, 'item') || $text === '/start') {
            $items = BusinessCatalogItem::where('business_id', $business->id)
                ->where('is_active', true)
                ->limit(5)
                ->get();

            if ($items->isNotEmpty()) {
                $lines = ["🛍️ *Welcome to {$business->name}!* Here are our featured items in stock:\n"];
                foreach ($items as $item) {
                    $priceStr = $item->formatted_price ?: ($item->currency.' '.number_format($item->price, 2));
                    $sizeStr = '';
                    if (! empty($item->attributes['sizes'])) {
                        $sizeStr = ' | Sizes: '.implode(', ', (array) $item->attributes['sizes']);
                    }
                    $lines[] = "• *{$item->name}* ({$priceStr}){$sizeStr}\n  _{$item->description}_";
                }
                $lines[] = "\n💡 _To order: tell me which item and size you want (e.g. \"Add {$items->first()->name} to cart\"). You can also ask \"Send picture of {$items->first()->name}\"!_";

                return [implode("\n", $lines), null];
            }
        }

        // Check if customer wants to view cart
        if (str_contains($text, 'cart') || str_contains($text, 'basket')) {
            $cart = $conversation->metadata['cart'] ?? ['items' => []];
            if (empty($cart['items'])) {
                return ['🛒 Your shopping cart is currently empty. Ask me about our available products to start shopping!', null];
            }

            $currency = $cart['currency'] ?? 'NGN';
            $lines = ["🛒 *YOUR SHOPPING CART:*\n"];
            foreach ($cart['items'] as $i) {
                $spec = $i['size'] ? " (Size: {$i['size']})" : '';
                $itemTotal = $currency.' '.number_format($i['total_price'], 2);
                $lines[] = "• {$i['quantity']}x *{$i['name']}*{$spec} — {$itemTotal}";
            }
            $lines[] = "\n*Subtotal:* {$currency} ".number_format($cart['subtotal'], 2);
            $lines[] = "\nReady to order? Send me your delivery address to proceed to checkout!";

            return [implode("\n", $lines), null];
        }

        // Check if customer wants to checkout / pay
        if (str_contains($text, 'checkout') || str_contains($text, 'pay') || str_contains($text, 'buy') || str_contains($text, 'order')) {
            $cart = $conversation->metadata['cart'] ?? ['items' => []];

            if (! empty($cart['items'])) {
                // If address is mentioned in text
                $itemCount = count($cart['items'] ?? []);
                $gateway = new PaymentGatewayService($business);
                $payment = $gateway->initializePayment(
                    customer: $conversation->customer,
                    conversation: $conversation,
                    amount: (float) $cart['subtotal'],
                    currency: $cart['currency'] ?? 'NGN',
                    description: "Cart Order ({$itemCount} items)",
                    metadata: [
                        'order_type' => 'cart_order',
                        'cart' => $cart,
                        'shipping_address' => $inbound->content,
                        'customer_name' => $conversation->customer->name,
                        'customer_phone' => $conversation->customer->phone,
                    ],
                );

                $currency = $cart['currency'] ?? 'NGN';
                $priceStr = $currency.' '.number_format($cart['subtotal'], 2);

                return ["🎉 *Checkout Ready! Total: {$priceStr}*\n\nClick below to complete your secure payment:\n{$payment->checkout_url}\n\n_Once paid, your order tracking code and receipt will be delivered here instantly!_", null];
            }

            // Fallback for single item direct purchase
            $item = BusinessCatalogItem::where('business_id', $business->id)->where('is_active', true)->first();
            if ($item && $item->price > 0) {
                $gateway = new PaymentGatewayService($business);
                $payment = $gateway->initializePayment(
                    customer: $conversation->customer,
                    conversation: $conversation,
                    amount: (float) $item->price,
                    currency: $item->currency ?? 'NGN',
                    description: $item->name,
                    metadata: ['item_name' => $item->name],
                );

                $priceStr = ($item->currency ?? 'NGN').' '.number_format($item->price, 2);

                return ["Here is your payment link for *{$item->name}* ({$priceStr}):\n\n{$payment->checkout_url}\n\n_Payment is secure. Your receipt and tracking code will be delivered immediately after payment._", null];
            }
        }

        return ["Hello {$customer->name}! Welcome to {$business->name}. How can I assist you today? You can ask to view our products, check prices, see pictures, or track an existing order.", null];
    }

    private function callLlm(AiProviderSetting $setting, array $messages, array $tools = []): array
    {
        $provider = $setting->provider;
        $apiKey = $setting->api_key;
        $baseUrl = match ($provider) {
            'deepseek' => 'https://api.deepseek.com/v1',
            'openai' => 'https://api.openai.com/v1',
            'custom' => rtrim($setting->base_url ?: 'https://api.deepseek.com/v1', '/'),
            default => 'https://api.deepseek.com/v1',
        };

        $payload = [
            'model' => $setting->model ?: 'deepseek-chat',
            'messages' => $messages,
            'temperature' => (float) $setting->temperature,
            'max_tokens' => (int) $setting->max_tokens,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(45)
                ->post("{$baseUrl}/chat/completions", $payload);

            if ($response->failed()) {
                Log::error('LLM API failed: '.$response->body());

                return ['content' => 'I am reviewing your inquiry. Please hold on a moment.'];
            }

            $json = $response->json();
            $choice = $json['choices'][0]['message'] ?? [];

            return [
                'content' => $choice['content'] ?? null,
                'tool_calls' => $choice['tool_calls'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('LLM Exception: '.$e->getMessage());

            return ['content' => 'Thank you for reaching out! A representative will connect with you shortly.'];
        }
    }
}
