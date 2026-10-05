<?php

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Enums\UserRole;
use App\Models\AiProviderSetting;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessChannel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerChannelIdentity;
use App\Models\Message;
use App\Models\User;
use App\Services\AiAgentOrchestrator;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Royal Palms Real Estate',
        'slug' => 'royal-palms-'.uniqid(),
        'industry' => 'real_estate',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->owner = User::create([
        'name' => 'General Manager',
        'email' => 'gm_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);

    $this->aiSetting = AiProviderSetting::create([
        'business_id' => $this->business->id,
        'provider' => 'deepseek',
        'api_key' => 'sk-test-deepseek-key',
        'model' => 'deepseek-chat',
        'temperature' => 0.7,
        'max_tokens' => 2000,
        'is_active' => true,
    ]);

    $this->channel = BusinessChannel::create([
        'business_id' => $this->business->id,
        'channel' => ChannelType::Telegram,
        'credentials' => ['bot_token' => 'mock_bot_token'],
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'Michael Scott',
        'phone' => '+1234567890',
        'lead_status' => 'new',
    ]);

    CustomerChannelIdentity::create([
        'customer_id' => $this->customer->id,
        'channel' => ChannelType::Telegram,
        'channel_user_id' => '1234567',
    ]);

    $this->conversation = Conversation::create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'business_channel_id' => $this->channel->id,
        'channel' => ChannelType::Telegram,
        'status' => ConversationState::New,
        'handler' => 'ai',
    ]);

    // Create a property in the catalog
    BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => '4-Bedroom Penthouse in Victoria Island',
        'description' => 'Panoramic ocean view, private elevator, swimming pool',
        'price' => 120000000.00,
        'currency' => 'NGN',
        'category' => 'penthouses',
        'availability_status' => 'available',
        'is_active' => true,
    ]);
});

test('orchestrator triggers catalog search tool and returns response', function () {
    // Fake DeepSeek API response: first call returns tool_call for search_catalog, second call returns text
    Http::fake([
        'https://api.deepseek.com/v1/chat/completions' => Http::sequence()
            ->push([
                'id' => 'chatcmpl-test-1',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'tool_calls' => [
                                [
                                    'id' => 'call_search_1',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'search_catalog',
                                        'arguments' => json_encode(['query' => 'Penthouse in Victoria Island']),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->push([
                'id' => 'chatcmpl-test-2',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'We have a stunning 4-Bedroom Penthouse in Victoria Island available for 120,000,000 NGN featuring ocean views and a private elevator!',
                        ],
                    ],
                ],
            ]),
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 888]]),
    ]);

    $inbound = Message::create([
        'conversation_id' => $this->conversation->id,
        'direction' => 'inbound',
        'sender_type' => 'customer',
        'channel_message_id' => 'in_'.uniqid(),
        'content' => 'Do you have any penthouses in VI?',
    ]);

    $orchestrator = app(AiAgentOrchestrator::class);
    $reply = $orchestrator->handle($this->conversation, $inbound);

    expect($reply)->not->toBeNull();
    expect($reply->direction)->toBe('outbound');
    expect($reply->sender_type)->toBe('ai');
    expect($reply->content)->toContain('4-Bedroom Penthouse in Victoria Island');

    // Verify tool call was recorded in database
    $toolCall = $this->conversation->toolCalls()->first();
    expect($toolCall)->not->toBeNull();
    expect($toolCall->tool_name)->toBe('search_catalog');
    expect($toolCall->success)->toBeTrue();
    expect($toolCall->result['count'])->toBe(1);
});

test('orchestrator triggers appointment booking tool', function () {
    Http::fake([
        'https://api.deepseek.com/v1/chat/completions' => Http::sequence()
            ->push([
                'id' => 'chatcmpl-apt-1',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'tool_calls' => [
                                [
                                    'id' => 'call_book_1',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'book_appointment',
                                        'arguments' => json_encode([
                                            'title' => 'Penthouse Inspection',
                                            'scheduled_at' => '2026-10-01 11:00',
                                            'location' => 'Victoria Island',
                                            'duration_minutes' => 45,
                                        ]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->push([
                'id' => 'chatcmpl-apt-2',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Your inspection for the Penthouse has been scheduled for Thursday, Oct 1 at 11:00 AM!',
                        ],
                    ],
                ],
            ]),
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 889]]),
    ]);

    $inbound = Message::create([
        'conversation_id' => $this->conversation->id,
        'direction' => 'inbound',
        'sender_type' => 'customer',
        'channel_message_id' => 'in_'.uniqid(),
        'content' => 'Can I schedule a viewing for Oct 1 at 11am?',
    ]);

    $orchestrator = app(AiAgentOrchestrator::class);
    $reply = $orchestrator->handle($this->conversation, $inbound);

    expect($reply)->not->toBeNull();

    // Verify appointment was created in database
    $appointment = Appointment::where('conversation_id', $this->conversation->id)->first();
    expect($appointment)->not->toBeNull();
    expect($appointment->title)->toBe('Penthouse Inspection');
    expect($appointment->duration_minutes)->toBe(45);
});
