<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Acme Test Corp',
        'slug' => 'acme-test-'.uniqid(),
        'industry' => 'retail',
        'timezone' => 'UTC',
        'is_active' => true,
        'plan' => 'starter',
    ]);

    $this->superAdmin = User::create([
        'name' => 'Super Administrator',
        'email' => 'superadmin_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'is_super_admin' => true,
        'is_active' => true,
    ]);
});

test('super admin can view admin dashboard with correct mrr calculated from paid plans', function () {
    // 1 starter (20k), 1 pro (40k), 1 enterprise (80k) -> Total MRR = 140,000
    Business::create([
        'name' => 'Pro Business',
        'slug' => 'pro-'.uniqid(),
        'industry' => 'ecommerce',
        'timezone' => 'UTC',
        'is_active' => true,
        'plan' => 'pro',
    ]);

    Business::create([
        'name' => 'Enterprise Business',
        'slug' => 'ent-'.uniqid(),
        'industry' => 'tech',
        'timezone' => 'UTC',
        'is_active' => true,
        'plan' => 'enterprise',
    ]);

    $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->has('stats')
        ->where('stats.estimated_mrr', 140000)
    );
});

test('super admin can update a business plan to pro or enterprise', function () {
    $response = $this->actingAs($this->superAdmin)
        ->from(route('admin.dashboard'))
        ->patch(route('admin.businesses.plan', $this->business), [
            'plan' => 'enterprise',
        ]);

    $response->assertRedirect(route('admin.dashboard'));
    expect($this->business->fresh()->plan)->toBe('enterprise');
});

test('super admin cannot set business plan to free or invalid tiers', function () {
    $response = $this->actingAs($this->superAdmin)
        ->from(route('admin.dashboard'))
        ->patch(route('admin.businesses.plan', $this->business), [
            'plan' => 'free',
        ]);

    $response->assertSessionHasErrors(['plan']);
    expect($this->business->fresh()->plan)->toBe('starter');
});
