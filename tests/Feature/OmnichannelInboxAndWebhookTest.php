<?php

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessChannel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Services\TenantContext;

beforeEach(function () {
    TenantContext::clear();

    $this->business = Business::create([
        'name' => 'Grand Apex Hotel & Real Estate',
        'slug' => 'grand-apex-'.uniqid(),
        'industry' => 'real_estate',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->owner = User::create([
        'name' => 'Chief Operator',
        'email' => 'operator_'.uniqid().'@test.com',
        'password' => bcrypt('secret12345'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);

    // Setup WhatsApp Channel
    $this->whatsappChannel = BusinessChannel::create([
        'business_id' => $this->business->id,
        'channel' => ChannelType::Whatsapp,
        'credentials' => [
            'phone_number_id' => '1029384756',
            'access_token' => 'EAAB_test_token',
            'verify_token' => 'meta_verify_secret_123',
        ],
        'is_active' => true,
    ]);

    // Setup Telegram Channel
    $this->telegramChannel = BusinessChannel::create([
        'business_id' => $this->business->id,
        'channel' => ChannelType::Telegram,
        'credentials' => [
            'bot_token' => '987654:TEST_BOT_TOKEN',
        ],
        'is_active' => true,
    ]);

    // Add a Catalog item
    BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => '3-Bedroom Luxury Apartment in Lekki',
        'description' => 'Fully furnished with swimming pool and 24/7 power',
        'price' => 75000000.00,
        'currency' => 'NGN',
        'category' => 'apartments',
        'availability_status' => 'available',
        'is_active' => true,
    ]);
});

test('meta webhook challenge verification succeeds with valid token', function () {
    $response = $this->get(route('webhooks.whatsapp.verify', [
        'business' => $this->business->id,
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'meta_verify_secret_123',
        'hub_challenge' => 'test_challenge_code_999',
    ]));

    $response->assertStatus(200);
    expect($response->getContent())->toBe('test_challenge_code_999');
});

test('meta webhook challenge fails with invalid token', function () {
    $response = $this->get(route('webhooks.whatsapp.verify', [
        'business' => $this->business->id,
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'wrong_token',
        'hub_challenge' => 'test_challenge_code_999',
    ]));

    $response->assertStatus(403);
});

test('telegram webhook ingests incoming message and creates customer and conversation', function () {
    $payload = [
        'update_id' => 10001,
        'message' => [
            'message_id' => 54321,
            'from' => [
                'id' => 888777,
                'first_name' => 'David',
                'last_name' => 'Mark',
                'username' => 'davidmark',
            ],
            'chat' => [
                'id' => 888777,
                'type' => 'private',
            ],
            'date' => time(),
            'text' => 'Hello! Do you have any 3-bedroom apartments available?',
        ],
    ];

    $response = $this->postJson(route('webhooks.telegram', $this->business->id), $payload);
    $response->assertStatus(200)->assertJson(['ok' => true]);

    $customer = Customer::where('business_id', $this->business->id)->first();
    expect($customer)->not->toBeNull();
    expect($customer->name)->toBe('David Mark');

    $conversation = Conversation::where('business_id', $this->business->id)->first();
    expect($conversation)->not->toBeNull();
    expect($conversation->channel)->toBe(ChannelType::Telegram);

    $message = Message::where('conversation_id', $conversation->id)->first();
    expect($message)->not->toBeNull();
    expect($message->direction)->toBe('inbound');
    expect($message->content)->toContain('3-bedroom apartments');
});

test('live inbox displays conversation and allows staff manual reply and takeover', function () {
    // Setup customer and conversation
    $customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'Sarah Connor',
        'phone' => '+2348011223344',
        'lead_status' => 'qualified',
    ]);

    $conversation = Conversation::create([
        'business_id' => $this->business->id,
        'customer_id' => $customer->id,
        'business_channel_id' => $this->whatsappChannel->id,
        'channel' => ChannelType::Whatsapp,
        'status' => ConversationState::AiHandling,
        'handler' => 'ai',
        'last_message_at' => now(),
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'sender_type' => 'customer',
        'channel_message_id' => 'wa_inbound_1',
        'content' => 'I would like to inspect the Lekki property tomorrow.',
    ]);

    // Test Inbox Page load
    $pageResponse = $this->actingAs($this->owner)->get(route('inbox.index', ['selected' => $conversation->id]));
    $pageResponse->assertStatus(200);

    // Test Staff Handover toggle
    $handoverResponse = $this->actingAs($this->owner)->post(route('inbox.handover', $conversation->id));
    $handoverResponse->assertRedirect();
    $conversation->refresh();
    expect($conversation->handler)->toBe('human');
    expect($conversation->status)->toBe(ConversationState::HumanHandling);

    // Test Staff manual reply
    $replyResponse = $this->actingAs($this->owner)->post(route('inbox.reply', $conversation->id), [
        'content' => 'Hello Sarah! I can schedule an agent to meet you at 10 AM.',
    ]);
    $replyResponse->assertRedirect();

    $staffMsg = Message::where('conversation_id', $conversation->id)
        ->where('direction', 'outbound')
        ->where('sender_type', 'human')
        ->first();
    expect($staffMsg)->not->toBeNull();
    expect($staffMsg->content)->toContain('meet you at 10 AM');

    // Test Mark Resolved
    $resolveResponse = $this->actingAs($this->owner)->post(route('inbox.resolve', $conversation->id));
    $resolveResponse->assertRedirect();
    $conversation->refresh();
    expect($conversation->status)->toBe(ConversationState::Resolved);
});
