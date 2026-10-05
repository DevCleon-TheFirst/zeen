<?php

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessChannel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Services\AiAgentOrchestrator;

beforeEach(function () {
    $this->businessOne = Business::create([
        'name' => 'Prime Realty Group',
        'slug' => 'prime-realty-'.uniqid(),
        'industry' => 'real_estate',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->businessTwo = Business::create([
        'name' => 'Apex Fashion Boutique',
        'slug' => 'apex-fashion-'.uniqid(),
        'industry' => 'retail',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->unauthorizedBusiness = Business::create([
        'name' => 'Secret Enterprise',
        'slug' => 'secret-biz-'.uniqid(),
        'industry' => 'services',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->user = User::create([
        'name' => 'Serial Entrepreneur',
        'email' => 'owner_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->businessOne->id,
        'role' => UserRole::Owner,
        'is_active' => true,
        'is_super_admin' => false,
    ]);

    // Attach user to both businessOne and businessTwo
    $this->user->businesses()->attach([
        $this->businessOne->id => ['role' => 'owner'],
        $this->businessTwo->id => ['role' => 'owner'],
    ]);

    $this->superAdmin = User::create([
        'name' => 'Super Administrator',
        'email' => 'super_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->businessOne->id,
        'role' => UserRole::Owner,
        'is_active' => true,
        'is_super_admin' => true,
    ]);
});

test('user can switch their active workspace between businesses they belong to', function () {
    expect($this->user->business_id)->toBe($this->businessOne->id);

    $response = $this->actingAs($this->user)
        ->post(route('businesses.switch', $this->businessTwo->id));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->user->refresh();
    expect($this->user->business_id)->toBe($this->businessTwo->id);
});

test('user cannot switch to a workspace they do not belong to', function () {
    $response = $this->actingAs($this->user)
        ->post(route('businesses.switch', $this->unauthorizedBusiness->id));

    $response->assertStatus(403);
    $this->user->refresh();
    expect($this->user->business_id)->toBe($this->businessOne->id);
});

test('super admin can switch to any business workspace', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post(route('businesses.switch', $this->unauthorizedBusiness->id));

    $response->assertRedirect();
    $this->superAdmin->refresh();
    expect($this->superAdmin->business_id)->toBe($this->unauthorizedBusiness->id);
});

test('user can create a new business workspace and becomes active owner', function () {
    $response = $this->actingAs($this->user)
        ->post(route('businesses.store'), [
            'name' => 'Lagos Grand Suites',
            'industry' => 'hospitality',
            'description' => 'Luxury serviced shortlets in Victoria Island',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $newBiz = Business::where('name', 'Lagos Grand Suites')->first();
    expect($newBiz)->not->toBeNull();
    expect($newBiz->industry)->toBe('hospitality');

    $this->user->refresh();
    expect($this->user->business_id)->toBe($newBiz->id);
    expect($this->user->businesses()->where('businesses.id', $newBiz->id)->exists())->toBeTrue();
});

test('user can update the dominant focus mode of their active business', function () {
    expect($this->businessOne->industry)->toBe('real_estate');

    $response = $this->actingAs($this->user)
        ->patch(route('businesses.dominant-mode'), [
            'industry' => 'retail',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->businessOne->refresh();
    expect($this->businessOne->industry)->toBe('retail');
});

test('ai system prompt dynamically adapts to dominant business focus', function () {
    $channel = BusinessChannel::create([
        'business_id' => $this->businessOne->id,
        'channel' => ChannelType::Telegram,
        'credentials' => ['bot_token' => 'mock_token'],
        'is_active' => true,
    ]);

    $customer = Customer::create([
        'business_id' => $this->businessOne->id,
        'name' => 'Ada Lovelace',
        'channel' => 'telegram',
    ]);

    $conversation = Conversation::create([
        'business_id' => $this->businessOne->id,
        'customer_id' => $customer->id,
        'business_channel_id' => $channel->id,
        'channel' => 'telegram',
        'channel_thread_id' => 'tg-'.uniqid(),
        'status' => ConversationState::AiHandling,
    ]);

    $orchestrator = app(AiAgentOrchestrator::class);
    $reflection = new ReflectionClass($orchestrator);
    $method = $reflection->getMethod('buildSystemPrompt');
    $method->setAccessible(true);

    // When dominant focus is real estate
    $this->businessOne->update(['industry' => 'real_estate']);
    $conversation->setRelation('business', $this->businessOne);
    $conversation->setRelation('customer', $customer);
    $promptRealEstate = $method->invoke($orchestrator, $conversation);
    expect($promptRealEstate)->toContain('Real Estate & Property Discovery');
    expect($promptRealEstate)->toContain('book_appointment');

    // When dominant focus is switched to retail
    $this->businessOne->update(['industry' => 'retail']);
    $conversation->setRelation('business', $this->businessOne);
    $conversation->setRelation('customer', $customer);
    $promptRetail = $method->invoke($orchestrator, $conversation);
    expect($promptRetail)->toContain('Online Store, Retail & Products');
    expect($promptRetail)->toContain('search_catalog');
    expect($promptRetail)->toContain('add_to_cart');
});
