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
    expect($user->business->aiProviderSetting)->not->toBeNull();
    expect($user->business->aiProviderSetting->provider)->toBe('deepseek');
});
