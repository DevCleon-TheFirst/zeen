<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Orders\OrderFulfillmentService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Apex Test Store',
        'slug' => 'apex-store-'.uniqid(),
        'industry' => 'retail_store',
        'timezone' => 'UTC',
        'is_active' => true,
        'settings' => ['active_payment_gateway' => 'test'],
    ]);

    $this->user = User::create([
        'name' => 'Store Manager',
        'email' => 'mgr_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->business->id,
        'role' => UserRole::Admin,
        'is_active' => true,
        'is_super_admin' => false,
    ]);

    $this->superAdmin = User::create([
        'name' => 'Platform Owner',
        'email' => 'super_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'is_active' => true,
        'is_super_admin' => true,
    ]);

    $this->customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'Jane Buyer',
        'phone' => '+2348011223344',
        'lead_status' => 'new',
    ]);
});

test('store can switch active payment gateway to monnify', function () {
    $response = $this->actingAs($this->user)
        ->post(route('payments.gateway-settings'), [
            'active_gateway' => 'monnify',
            'monnify_api_key' => 'MK_TEST_123',
            'monnify_secret_key' => 'SEC_456',
            'monnify_contract_code' => '9988776655',
            'monnify_test_mode' => true,
        ]);

    $response->assertSessionHas('success');

    $this->business->refresh();
    expect($this->business->settings['active_payment_gateway'])->toBe('monnify');
    expect($this->business->settings['monnify_contract_code'])->toBe('9988776655');
});

test('order fulfillment creates order records and tracking code', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1234]]),
    ]);

    // amount_kobo = amount in minor units (50000 NGN = 5000000 kobo)
    $payment = Payment::create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'currency' => 'NGN',
        'amount_kobo' => 5000000,
        'status' => 'pending',
        'reference' => 'PAY-TEST-'.uniqid(),
        'checkout_url' => 'https://example.com/checkout/123',
        'metadata' => [
            'order_type' => 'cart_order',
            'shipping_address' => '14 Admiralty Way, Lekki Phase 1, Lagos',
            'cart' => [
                'currency' => 'NGN',
                'subtotal' => 50000,
                'items' => [
                    [
                        'name' => 'Classic Oxford Shirt',
                        'size' => 'L',
                        'color' => 'Navy Blue',
                        'quantity' => 2,
                        'unit_price' => 25000,
                        'total_price' => 50000,
                    ],
                ],
            ],
        ],
    ]);

    $service = new OrderFulfillmentService;
    $order = $service->fulfillOrderFromPayment($payment, $this->business);

    expect($order)->not->toBeNull();
    expect($order->tracking_code)->toStartWith('TRK-');
    expect($order->status)->toBe('confirmed');
    expect($order->shipping_address)->toBe('14 Admiralty Way, Lekki Phase 1, Lagos');
    expect($order->items()->count())->toBe(1);

    $item = $order->items()->first();
    expect($item->item_name)->toBe('Classic Oxford Shirt');
    expect($item->size)->toBe('L');
    expect($item->quantity)->toBe(2);
});

test('tracking lookup returns itemized report for valid tracking code', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    $payment = Payment::create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'currency' => 'NGN',
        'amount_kobo' => 1500000,
        'status' => 'pending',
        'reference' => 'PAY-TRK-'.uniqid(),
        'checkout_url' => 'https://example.com/checkout/456',
        'metadata' => [
            'order_type' => 'cart_order',
            'shipping_address' => 'VI Lagos',
            'cart' => [
                'currency' => 'NGN',
                'subtotal' => 15000,
                'items' => [['name' => 'Blue Sneaker', 'size' => '42', 'quantity' => 1, 'unit_price' => 15000, 'total_price' => 15000]],
            ],
        ],
    ]);

    $service = new OrderFulfillmentService;
    $order = $service->fulfillOrderFromPayment($payment, $this->business);

    $report = $service->lookupTracking($order->tracking_code, $this->business);
    expect($report)->toContain($order->tracking_code);
    expect($report)->toContain('CONFIRMED');
    expect($report)->toContain('VI Lagos');
});

test('non super-admin is forbidden from admin command route', function () {
    $response = $this->actingAs($this->user)->get(route('admin.dashboard'));
    $response->assertStatus(403);
});

test('super-admin can access admin command dashboard', function () {
    $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));
    $response->assertStatus(200);
});
