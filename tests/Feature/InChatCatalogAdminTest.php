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

    expect($reply)->toContain('Product Added Successfully')
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

    expect($reply)->toContain('Current Inventory')
        ->and($reply)->toContain('Casual Loafers')
        ->and($reply)->toContain('4 left');
});
