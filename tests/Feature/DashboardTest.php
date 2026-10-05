<?php

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessChannel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Dashboard Test Business',
        'slug' => 'dash-test-'.uniqid(),
        'industry' => 'services',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->user = User::create([
        'name' => 'Dashboard Admin',
        'email' => 'dash_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'Jane Doe',
    ]);

    $this->channel = BusinessChannel::create([
        'business_id' => $this->business->id,
        'channel' => ChannelType::Telegram,
        'credentials' => ['bot_token' => '12345:TEST_BOT_TOKEN'],
        'is_active' => true,
    ]);
});

test('unauthenticated user is redirected to login from dashboard', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated user can load dashboard with calculated stats', function () {
    // Create conversations with different statuses
    Conversation::create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'business_channel_id' => $this->channel->id,
        'channel' => 'telegram',
        'status' => ConversationState::AiHandling,
        'handler' => 'ai',
        'priority' => 'medium',
    ]);

    Conversation::create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'business_channel_id' => $this->channel->id,
        'channel' => 'whatsapp',
        'status' => ConversationState::HumanHandling,
        'handler' => 'human',
        'priority' => 'high',
    ]);

    Conversation::create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'business_channel_id' => $this->channel->id,
        'channel' => 'messenger',
        'status' => ConversationState::Resolved,
        'handler' => 'ai',
        'priority' => 'low',
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('stats')
        ->where('stats.conversations_open', 2)
        ->where('stats.conversations_ai', 1)
        ->where('stats.conversations_escalated', 1)
    );
});
