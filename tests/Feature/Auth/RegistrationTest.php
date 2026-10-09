<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register and their business is created', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'business_name' => 'Apex Realty',
        'industry' => 'real_estate',
        'email' => 'owner@apex.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'owner@apex.test')->first();
    expect($user)->not->toBeNull();
    expect($user->isOwner())->toBeTrue();
    expect($user->business)->not->toBeNull();
    expect($user->business->name)->toBe('Apex Realty');
    expect($user->business->plan)->toBe('starter');
    expect($user->business->ai_credits_balance)->toBe(2500);
    expect($user->business->aiProviderSetting)->not->toBeNull();
    expect($user->business->aiProviderSetting->provider)->toBe('deepseek');
});

test('users can register with omnichannel pro plan', function () {
    $response = $this->post('/register', [
        'name' => 'Pro User',
        'business_name' => 'Pro Corp',
        'industry' => 'ecommerce',
        'email' => 'pro@corp.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'plan' => 'pro',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'pro@corp.test')->first();
    expect($user)->not->toBeNull();
    expect($user->business->plan)->toBe('pro');
    expect($user->business->ai_credits_balance)->toBe(8000);
});

test('users can register with enterprise plan', function () {
    $response = $this->post('/register', [
        'name' => 'Enterprise User',
        'business_name' => 'Mega Corp',
        'industry' => 'finance',
        'email' => 'enterprise@corp.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'plan' => 'enterprise',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'enterprise@corp.test')->first();
    expect($user)->not->toBeNull();
    expect($user->business->plan)->toBe('enterprise');
    expect($user->business->ai_credits_balance)->toBe(25000);
});

test('registration rejects free or invalid plan selection', function () {
    $response = $this->post('/register', [
        'name' => 'Free Seeker',
        'business_name' => 'No Pay LLC',
        'industry' => 'retail',
        'email' => 'nopay@retail.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'plan' => 'free',
    ]);

    $response->assertSessionHasErrors(['plan']);
    $this->assertGuest();
});
