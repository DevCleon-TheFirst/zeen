<?php

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessCatalogItem;
use App\Models\Customer;
use App\Models\User;

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Atlantic Properties & Suites',
        'slug' => 'atlantic-'.uniqid(),
        'industry' => 'hospitality',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->owner = User::create([
        'name' => 'Property Host',
        'email' => 'host_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);
});

test('user can view catalog items and create a new catalog item', function () {
    $response = $this->actingAs($this->owner)->get(route('catalog.index'));
    $response->assertStatus(200);

    $createResponse = $this->actingAs($this->owner)->post(route('catalog.store'), [
        'name' => 'Deluxe Ocean View Suite',
        'description' => 'King bed with private balcony and minibar',
        'price' => 85000.00,
        'currency' => 'NGN',
        'category' => 'suites',
        'availability_status' => 'available',
        'is_active' => true,
    ]);

    $createResponse->assertRedirect();

    $item = BusinessCatalogItem::where('business_id', $this->business->id)->first();
    expect($item)->not->toBeNull();
    expect($item->name)->toBe('Deluxe Ocean View Suite');
    expect($item->price)->toEqual(85000.00);
    expect($item->availability_status)->toBe('available');
});

test('user can update catalog item', function () {
    $item = BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Standard Double Room',
        'price' => 45000.00,
        'currency' => 'NGN',
        'category' => 'rooms',
        'availability_status' => 'available',
        'is_active' => true,
    ]);

    $updateResponse = $this->actingAs($this->owner)->put(route('catalog.update', $item->id), [
        'name' => 'Executive Double Room',
        'price' => 55000.00,
        'currency' => 'NGN',
        'category' => 'rooms',
        'availability_status' => 'coming_soon',
        'is_active' => true,
    ]);

    $updateResponse->assertRedirect();
    $item->refresh();
    expect($item->name)->toBe('Executive Double Room');
    expect($item->price)->toEqual(55000.00);
    expect($item->availability_status)->toBe('coming_soon');
});

test('user can view appointments and update appointment status', function () {
    $customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'James Bond',
        'phone' => '+447911123456',
        'lead_status' => 'contacted',
    ]);

    $appointment = Appointment::create([
        'business_id' => $this->business->id,
        'customer_id' => $customer->id,
        'title' => 'VIP Suite Tour',
        'scheduled_at' => now()->addDays(2),
        'duration_minutes' => 30,
        'status' => 'pending',
    ]);

    $indexResponse = $this->actingAs($this->owner)->get(route('appointments.index'));
    $indexResponse->assertStatus(200);

    $updateResponse = $this->actingAs($this->owner)->patch(route('appointments.update', $appointment->id), [
        'status' => 'confirmed',
        'location' => 'Main Reception Desk',
    ]);

    $updateResponse->assertRedirect();
    $appointment->refresh();
    expect($appointment->status)->toBe('confirmed');
    expect($appointment->location)->toBe('Main Reception Desk');
});
