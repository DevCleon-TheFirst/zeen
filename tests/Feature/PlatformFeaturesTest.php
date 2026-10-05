<?php

use App\Enums\UserRole;
use App\Models\AutomationTemplate;
use App\Models\AutomationWorkflow;
use App\Models\Business;
use App\Models\BusinessChannel;
use App\Models\User;

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Test Real Estate',
        'slug' => 'test-realty-'.uniqid(),
        'industry' => 'real_estate',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->owner = User::create([
        'name' => 'Owner Person',
        'email' => 'owner_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);
});

test('authenticated user can view and update AI settings', function () {
    $response = $this->actingAs($this->owner)->get(route('settings.ai'));
    $response->assertStatus(200);

    $updateResponse = $this->actingAs($this->owner)->post(route('settings.ai.update'), [
        'provider' => 'deepseek',
        'api_key' => 'sk-deepseek-test-key-12345',
        'base_url' => 'https://api.deepseek.com',
        'model' => 'deepseek-chat',
        'temperature' => 0.5,
        'max_tokens' => 1500,
        'is_active' => true,
    ]);

    $updateResponse->assertRedirect();

    $setting = $this->business->aiProviderSetting()->first();
    expect($setting)->not->toBeNull();
    expect($setting->provider)->toBe('deepseek');
    expect($setting->api_key)->toBe('sk-deepseek-test-key-12345');
    expect($setting->temperature)->toEqual(0.5);
});

test('authenticated user can configure messaging channels', function () {
    $response = $this->actingAs($this->owner)->get(route('channels.index'));
    $response->assertStatus(200);

    $telegramUpdate = $this->actingAs($this->owner)->post(route('channels.update', 'telegram'), [
        'is_active' => true,
        'credentials' => [
            'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
        ],
    ]);

    $telegramUpdate->assertRedirect();

    $channel = BusinessChannel::where('business_id', $this->business->id)->where('channel', 'telegram')->first();
    expect($channel)->not->toBeNull();
    expect($channel->is_active)->toBeTrue();
    expect($channel->credentials['bot_token'])->toBe('123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
});

test('owner can manage staff members and assign roles', function () {
    $response = $this->actingAs($this->owner)->get(route('staff.index'));
    $response->assertStatus(200);

    $createStaff = $this->actingAs($this->owner)->post(route('staff.store'), [
        'name' => 'Agent Smith',
        'email' => 'agent_'.uniqid().'@test.com',
        'role' => 'agent',
        'phone' => '+1234567890',
        'password' => 'secretPassword123!',
    ]);

    $createStaff->assertRedirect();

    $agent = User::where('name', 'Agent Smith')->first();
    expect($agent)->not->toBeNull();
    expect($agent->role)->toBe(UserRole::Agent);
    expect($agent->business_id)->toBe($this->business->id);
});

test('user can create workflow and import pre-built industry template', function () {
    $template = AutomationTemplate::firstOrCreate([
        'name' => 'Test Lead Automation',
    ], [
        'industry' => 'real_estate',
        'description' => 'Test workflow template',
        'workflow_snapshot' => [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger_whatsapp', 'label' => 'WhatsApp Trigger', 'position' => ['x' => 10, 'y' => 10]],
                ['id' => 'n2', 'type' => 'ai_intent', 'label' => 'DeepSeek Intent', 'position' => ['x' => 100, 'y' => 10]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'n1', 'target' => 'n2', 'label' => 'Next'],
            ],
        ],
        'is_published' => true,
    ]);

    $importResponse = $this->actingAs($this->owner)->post(route('automations.fromTemplate', $template->id));
    $importResponse->assertRedirect();

    $workflow = AutomationWorkflow::where('business_id', $this->business->id)->first();
    expect($workflow)->not->toBeNull();
    expect($workflow->nodes()->count())->toBe(2);
    expect($workflow->edges()->count())->toBe(1);
});
