<?php

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Models\Business;
use App\Models\BusinessChannel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerChannelIdentity;
use App\Models\Message;
use App\Services\Channels\MessageIngestionService;
use App\Services\TenantContext;

beforeEach(function () {
    TenantContext::clear();

    $this->business = Business::create([
        'name' => 'Test Store',
        'slug' => 'test-store-'.uniqid(),
        'industry' => 'retail',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'John Doe',
        'phone' => '+2348011223344',
    ]);

    CustomerChannelIdentity::create([
        'customer_id' => $this->customer->id,
        'channel' => ChannelType::WhatsappWeb,
        'channel_user_id' => '2348011223344',
    ]);

    $this->channel = BusinessChannel::create([
        'business_id' => $this->business->id,
        'channel' => ChannelType::WhatsappWeb,
        'credentials' => [],
        'is_active' => true,
    ]);
});

test('scheduled command resumes ai for conversations idle for more than 3 minutes', function () {
    $idleConversation = Conversation::create([
        'business_id' => $this->business->id,
        'business_channel_id' => $this->channel->id,
        'customer_id' => $this->customer->id,
        'channel' => ChannelType::WhatsappWeb,
        'handler' => 'human',
        'status' => ConversationState::HumanHandling,
        'human_last_replied_at' => now()->subMinutes(4),
        'last_message_at' => now()->subMinutes(4),
    ]);

    $activeConversation = Conversation::create([
        'business_id' => $this->business->id,
        'business_channel_id' => $this->channel->id,
        'customer_id' => $this->customer->id,
        'channel' => ChannelType::WhatsappWeb,
        'handler' => 'human',
        'status' => ConversationState::HumanHandling,
        'human_last_replied_at' => now()->subMinute(),
        'last_message_at' => now()->subMinute(),
    ]);

    $resolvedConversation = Conversation::create([
        'business_id' => $this->business->id,
        'business_channel_id' => $this->channel->id,
        'customer_id' => $this->customer->id,
        'channel' => ChannelType::WhatsappWeb,
        'handler' => 'human',
        'status' => ConversationState::Resolved,
        'human_last_replied_at' => now()->subMinutes(10),
        'last_message_at' => now()->subMinutes(10),
    ]);

    $this->artisan('conversation:resume-ai')
        ->assertSuccessful();

    $idleConversation->refresh();
    $activeConversation->refresh();
    $resolvedConversation->refresh();

    expect($idleConversation->handler)->toBe('ai')
        ->and($idleConversation->status)->toBe(ConversationState::AiHandling)
        ->and($idleConversation->human_last_replied_at)->toBeNull();

    expect($activeConversation->handler)->toBe('human')
        ->and($activeConversation->status)->toBe(ConversationState::HumanHandling)
        ->and($activeConversation->human_last_replied_at)->not->toBeNull();

    expect($resolvedConversation->handler)->toBe('human')
        ->and($resolvedConversation->status)->toBe(ConversationState::Resolved);
});

test('ingestWhatsappQr ignores duplicate owner messages idempotently', function () {
    $conversation = Conversation::create([
        'business_id' => $this->business->id,
        'business_channel_id' => $this->channel->id,
        'customer_id' => $this->customer->id,
        'channel' => ChannelType::WhatsappWeb,
        'handler' => 'ai',
        'status' => ConversationState::AiHandling,
        'last_message_at' => now(),
    ]);

    $service = app(MessageIngestionService::class);
    $payload = [
        'event' => 'owner_message',
        'to' => '2348011223344',
        'text' => 'Hello from owner',
        'message_id' => 'msg_unique_123',
    ];

    $msg1 = $service->ingestWhatsappQr($this->business, $payload);
    expect($msg1)->not->toBeNull();
    expect($msg1->content)->toBe('Hello from owner');

    $conversation->refresh();
    expect($conversation->handler)->toBe('human')
        ->and($conversation->status)->toBe(ConversationState::HumanHandling);

    // Second call with same message_id should be ignored
    $msg2 = $service->ingestWhatsappQr($this->business, $payload);
    expect($msg2)->toBeNull();

    // Total messages created should only be 1
    expect(Message::where('channel_message_id', 'msg_unique_123')->count())->toBe(1);
});

test('inbound customer message suppresses AI during human active window and auto-resumes after 3 minutes silence', function () {
    $conversation = Conversation::create([
        'business_id' => $this->business->id,
        'business_channel_id' => $this->channel->id,
        'customer_id' => $this->customer->id,
        'channel' => ChannelType::WhatsappWeb,
        'handler' => 'human',
        'status' => ConversationState::HumanHandling,
        'human_last_replied_at' => now()->subMinute(), // Active (<3 min)
        'last_message_at' => now()->subMinute(),
    ]);

    $service = app(MessageIngestionService::class);

    // Customer message 1 while human is active — AI remains suppressed, handler stays human
    $payload1 = [
        'event' => 'message',
        'business_id' => $this->business->id,
        'from' => '2348011223344',
        'text' => 'Customer reply while owner is active',
        'message_id' => 'cust_msg_1',
    ];
    $service->ingestWhatsappQr($this->business, $payload1);

    $conversation->refresh();
    expect($conversation->handler)->toBe('human')
        ->and($conversation->status)->toBe(ConversationState::HumanHandling);

    // Simulate 4 minutes of owner inactivity
    $conversation->update(['human_last_replied_at' => now()->subMinutes(4)]);

    // Customer message 2 after 3 minutes silence — AI resumes
    $payload2 = [
        'event' => 'message',
        'business_id' => $this->business->id,
        'from' => '2348011223344',
        'text' => 'Customer message after owner went silent',
        'message_id' => 'cust_msg_2',
    ];
    $service->ingestWhatsappQr($this->business, $payload2);

    $conversation->refresh();
    expect($conversation->handler)->toBe('ai')
        ->and($conversation->status)->toBe(ConversationState::AiHandling)
        ->and($conversation->human_last_replied_at)->toBeNull();
});
