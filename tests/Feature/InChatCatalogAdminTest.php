<?php

namespace Tests\Feature;

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessChannel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Catalog\ChatAdminOpsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');

    $this->business = Business::create([
        'name' => 'Chat Admin Store',
        'slug' => 'chat-admin-'.uniqid(),
        'industry' => 'retail',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->user = User::create([
        'name' => 'Store Owner',
        'email' => 'owner_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'phone' => '123456789',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'Alice Customer',
        'phone' => '+2348011223344',
    ]);

    $this->channel = BusinessChannel::create([
        'business_id' => $this->business->id,
        'channel' => ChannelType::Telegram,
        'credentials' => ['bot_token' => '12345:TEST_BOT_TOKEN'],
        'is_active' => true,
    ]);

    $this->conversation = Conversation::create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'business_channel_id' => $this->channel->id,
        'channel' => 'telegram',
        'status' => ConversationState::HumanHandling,
        'handler' => 'human',
        'priority' => 'normal',
    ]);
});

test('inbox page loads with catalog items in inertia props', function () {
    BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Designer Handbag',
        'price' => 50000,
        'currency' => 'NGN',
        'stock_quantity' => 12,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('inbox.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Inbox/Index')
        ->has('catalogItems', 1)
        ->where('catalogItems.0.name', 'Designer Handbag')
        ->where('catalogItems.0.stock_quantity', 12)
    );
});

test('owner can quick add product with picture and stock from chat', function () {
    $file = UploadedFile::fake()->image('handbag.jpg', 600, 600);

    $response = $this->actingAs($this->user)->post(route('inbox.quick-product'), [
        'name' => 'Luxury Leather Wallet',
        'price' => 18500,
        'currency' => 'NGN',
        'stock_quantity' => 20,
        'category' => 'Accessories',
        'description' => 'Genuine cowhide leather bifold wallet.',
        'image_file' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('business_catalog_items', [
        'business_id' => $this->business->id,
        'name' => 'Luxury Leather Wallet',
        'price' => 18500.00,
        'stock_quantity' => 20,
        'category' => 'Accessories',
        'availability_status' => 'available',
    ]);

    $item = BusinessCatalogItem::where('name', 'Luxury Leather Wallet')->first();
    expect($item->images)->not->toBeEmpty();
});

test('owner can adjust stock on the fly with increment and decrement', function () {
    $item = BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Sneakers Pro',
        'price' => 35000,
        'currency' => 'NGN',
        'stock_quantity' => 5,
        'availability_status' => 'available',
        'is_active' => true,
    ]);

    // Increment by 3
    $this->actingAs($this->user)->patch(route('inbox.quick-stock', $item->id), [
        'delta' => 3,
    ])->assertRedirect();

    $item->refresh();
    expect($item->stock_quantity)->toBe(8)
        ->and($item->availability_status)->toBe('available');

    // Decrement by 8 to reach 0
    $this->actingAs($this->user)->patch(route('inbox.quick-stock', $item->id), [
        'delta' => -8,
    ])->assertRedirect();

    $item->refresh();
    expect($item->stock_quantity)->toBe(0)
        ->and($item->availability_status)->toBe('unavailable');
});

test('owner can share product card to customer in active conversation', function () {
    $item = BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Silk Evening Dress',
        'price' => 65000,
        'currency' => 'NGN',
        'stock_quantity' => 3,
        'images' => ['https://images.unsplash.com/photo-dress.jpg'],
        'description' => 'Burgundy pure silk evening gown.',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('inbox.send-product', [
        'conversation' => $this->conversation->id,
        'item' => $item->id,
    ]));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $this->conversation->id,
        'direction' => 'outbound',
        'sender_type' => 'human',
        'status' => 'pending',
    ]);

    $msg = $this->conversation->messages()->latest()->first();
    expect($msg->content)->toContain('Silk Evening Dress')
        ->and($msg->content)->toContain('65,000.00 NGN')
        ->and($msg->media)->not->toBeNull()
        ->and($msg->media[0]['url'])->toBe('https://images.unsplash.com/photo-dress.jpg');
});

test('chat admin ops service parses add product command and creates item', function () {
    $service = app(ChatAdminOpsService::class);

    $command = 'Add: Vintage Sunglasses, Price: 15000, Stock: 7, Category: Eyewear';
    $media = [['type' => 'image', 'url' => 'https://example.com/glasses.jpg']];

    $reply = $service->handle(
        $this->business,
        ChannelType::Telegram,
        '123456789',
        $command,
        $media
    );

    expect($reply)->toContain('[CONFIRMED] PRODUCT CREATED')
        ->and($reply)->toContain('Vintage Sunglasses');

    $this->assertDatabaseHas('business_catalog_items', [
        'business_id' => $this->business->id,
        'name' => 'Vintage Sunglasses',
        'price' => 15000.00,
        'stock_quantity' => 7,
        'category' => 'Eyewear',
    ]);
});

test('chat admin ops service returns stock report on stock command', function () {
    BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Casual Loafers',
        'price' => 28000,
        'currency' => 'NGN',
        'stock_quantity' => 4,
        'is_active' => true,
    ]);

    $service = app(ChatAdminOpsService::class);

    $reply = $service->handle(
        $this->business,
        ChannelType::Telegram,
        '123456789',
        '/stock'
    );

    expect($reply)->toContain('INVENTORY REPORT')
        ->and($reply)->toContain('Casual Loafers')
        ->and($reply)->toContain('4 units');
});

test('chat admin ops service generates professional executive dashboard with zero emojis', function () {
    // 1. Create catalog items (1 in stock, 1 out of stock)
    BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Wireless Keyboard',
        'price' => 18000,
        'currency' => 'NGN',
        'stock_quantity' => 0,
        'track_inventory' => true,
        'is_active' => true,
    ]);

    // 2. Create today's order
    $order = Order::create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'tracking_code' => 'TRK-990011',
        'status' => 'paid',
        'subtotal' => 45000,
        'total_amount' => 45000,
        'currency' => 'NGN',
        'customer_name' => 'Alice Customer',
        'customer_phone' => '+2348011223344',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Designer Leather Bag',
        'quantity' => 1,
        'unit_price' => 45000,
        'total_price' => 45000,
    ]);

    $service = app(ChatAdminOpsService::class);

    $reply = $service->handle(
        $this->business,
        ChannelType::WhatsappWeb,
        '123456789',
        '/dashboard'
    );

    expect($reply)->not->toBeNull()
        ->and($reply)->toContain('ZEEN BUSINESS EXECUTIVE SUMMARY')
        ->and($reply)->toContain('Orders Today: 1')
        ->and($reply)->toContain('Revenue Today: 45,000.00 NGN')
        ->and($reply)->toContain('TRK-990011')
        ->and($reply)->toContain('[PAID]')
        ->and($reply)->toContain('Wireless Keyboard ([OUT OF STOCK])')
        // Strictly zero emojis check
        ->and(preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $reply))->toBe(0);
});

test('chat admin ops service returns recent orders log on /orders', function () {
    $order = Order::create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'tracking_code' => 'TRK-881122',
        'status' => 'paid',
        'subtotal' => 30000,
        'total_amount' => 30000,
        'currency' => 'NGN',
        'customer_name' => 'Alice Customer',
        'customer_phone' => '+2348011223344',
        'shipping_address' => '12 Marina Road, Lagos',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Sneakers',
        'quantity' => 1,
        'unit_price' => 30000,
        'total_price' => 30000,
    ]);

    $service = app(ChatAdminOpsService::class);

    $reply = $service->handle(
        $this->business,
        ChannelType::WhatsappWeb,
        '123456789',
        '/orders'
    );

    expect($reply)->not->toBeNull()
        ->and($reply)->toContain('RECENT ORDERS LOG')
        ->and($reply)->toContain('TRK-881122')
        ->and($reply)->toContain('[PAID]')
        ->and($reply)->toContain('12 Marina Road, Lagos');
});

test('chat admin ops service marks order as dispatched on /dispatch', function () {
    $order = Order::create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'tracking_code' => 'TRK-776655',
        'status' => 'paid',
        'subtotal' => 20000,
        'total_amount' => 20000,
        'currency' => 'NGN',
        'customer_name' => 'Alice Customer',
        'customer_phone' => '+2348011223344',
    ]);

    $service = app(ChatAdminOpsService::class);

    $reply = $service->handle(
        $this->business,
        ChannelType::WhatsappWeb,
        '123456789',
        '/dispatch TRK-776655'
    );

    expect($reply)->not->toBeNull()
        ->and($reply)->toContain('[CONFIRMED] ORDER DISPATCHED')
        ->and($reply)->toContain('TRK-776655')
        ->and($order->fresh()->status)->toBe('dispatched')
        ->and($order->fresh()->dispatched_at)->not->toBeNull();
});

test('chat admin ops service rejects unauthorized non-admin phone numbers', function () {
    $service = app(ChatAdminOpsService::class);

    // Random unauthorized phone number
    $reply = $service->handle(
        $this->business,
        ChannelType::WhatsappWeb,
        '999888777666',
        '/dashboard'
    );

    expect($reply)->toBeNull();
});

test('store owner can link telegram account via /admin_link and access /dashboard on telegram', function () {
    $service = app(ChatAdminOpsService::class);
    $telegramChatId = '987654321';

    // 1. Initial attempt before linking should be rejected
    $unauthReply = $service->handle(
        $this->business,
        ChannelType::Telegram,
        $telegramChatId,
        '/dashboard'
    );
    expect($unauthReply)->toBeNull();

    // 2. Link using correct password
    $linkReply = $service->handle(
        $this->business,
        ChannelType::Telegram,
        $telegramChatId,
        '/admin_link password'
    );
    expect($linkReply)->not->toBeNull()
        ->and($linkReply)->toContain('[CONFIRMED] TELEGRAM ADMIN LINKED')
        ->and($linkReply)->toContain($telegramChatId);

    // Verify channel credentials updated
    $channel = BusinessChannel::withoutGlobalScopes()->where('business_id', $this->business->id)
        ->where('channel', ChannelType::Telegram)
        ->first();
    expect($channel->credentials['admin_chat_id'])->toBe($telegramChatId);

    // 3. Now accessing /dashboard on Telegram should succeed
    $dashReply = $service->handle(
        $this->business,
        ChannelType::Telegram,
        $telegramChatId,
        '/dashboard'
    );
    expect($dashReply)->not->toBeNull()
        ->and($dashReply)->toContain('ZEEN BUSINESS EXECUTIVE SUMMARY')
        ->and($dashReply)->toContain('Store: '.$this->business->name);
});
