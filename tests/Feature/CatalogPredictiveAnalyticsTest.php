<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessCatalogItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Catalog\CatalogAiAdvisorService;
use App\Services\Catalog\CatalogAnalyticsService;
use App\Services\TenantContext;

beforeEach(function () {
    TenantContext::clear();

    $this->business = Business::create([
        'name' => 'Herbal Glow Botanicals',
        'slug' => 'herbal-glow-'.uniqid(),
        'industry' => 'wellness',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $this->owner = User::create([
        'name' => 'Botanical Merchant',
        'email' => 'merchant_'.uniqid().'@test.com',
        'password' => bcrypt('password123'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);

    // Create 3 catalog items
    $this->item1 = BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Chamomile Calming Tea',
        'price' => 5000.00,
        'currency' => 'NGN',
        'category' => 'Tea',
        'availability_status' => 'available',
        'stock_quantity' => 4, // Low stock (<= 5)
        'track_inventory' => true,
        'is_active' => true,
    ]);

    $this->item2 = BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Black Seed Healing Oil',
        'price' => 12000.00,
        'currency' => 'NGN',
        'category' => 'Oils',
        'availability_status' => 'available',
        'stock_quantity' => 20,
        'track_inventory' => true,
        'is_active' => true,
    ]);

    $this->item3 = BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Rare Mountain Honey',
        'price' => 8000.00,
        'currency' => 'NGN',
        'category' => 'Honey',
        'availability_status' => 'unavailable',
        'stock_quantity' => 0, // Out of stock
        'track_inventory' => true,
        'is_active' => true,
    ]);

    // Create an order with items
    $order = Order::create([
        'business_id' => $this->business->id,
        'status' => 'confirmed',
        'tracking_code' => 'TRK-TEST01',
        'subtotal' => 10000.00,
        'total_amount' => 10000.00,
        'currency' => 'NGN',
        'customer_name' => 'Test Customer',
        'customer_phone' => '08012345678',
        'created_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'catalog_item_id' => $this->item1->id,
        'item_name' => 'Chamomile Calming Tea',
        'quantity' => 2,
        'unit_price' => 5000.00,
        'total_price' => 10000.00,
        'created_at' => now(),
    ]);
});

test('catalog analytics service computes correct summary stats and stock counts', function () {
    $service = app(CatalogAnalyticsService::class);
    $stats = $service->getSummaryStats($this->business);

    expect($stats['total_items'])->toBe(3)
        ->and($stats['active_items'])->toBe(3)
        ->and($stats['low_stock_count'])->toBe(1) // Item 1 (4 units)
        ->and($stats['out_of_stock_count'])->toBe(1) // Item 3 (0 units / unavailable)
        ->and($stats['total_units_sold'])->toBe(2)
        ->and($stats['total_revenue'])->toBe(10000.0);
});

test('catalog analytics service calculates item velocity and stockout predictions', function () {
    $service = app(CatalogAnalyticsService::class);
    $metrics = $service->getItemMetricsMap($this->business);

    expect($metrics)->toHaveKey($this->item1->id)
        ->and($metrics[$this->item1->id]['units_sold'])->toBe(2)
        ->and($metrics[$this->item1->id]['total_revenue'])->toBe(10000.0)
        ->and($metrics[$this->item1->id]['stockout_risk'])->toBeIn(['critical', 'warning']);

    expect($metrics)->toHaveKey($this->item3->id)
        ->and($metrics[$this->item3->id]['stockout_risk'])->toBe('out_of_stock');
});

test('catalog ai advisor generates structured insights with day-of-week trends', function () {
    $advisor = app(CatalogAiAdvisorService::class);
    $insights = $advisor->getInsights($this->business, forceRefresh: true);

    expect($insights)->toHaveKeys([
        'headline',
        'peak_day_insight',
        'restock_advice',
        'merchandising_tip',
        'urgency_level',
        'generated_at',
    ]);

    expect($insights['headline'])->toBeString()
        ->and($insights['urgency_level'])->toBeIn(['critical', 'warning', 'healthy']);
});

test('catalog index route renders with analytics and ai insight props', function () {
    $this->actingAs($this->owner)
        ->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Index')
            ->has('stats')
            ->has('day_of_week_trends')
            ->has('ai_insights')
            ->has('items.data', 3)
            ->where('stats.total_units_sold', 2)
            ->where('stats.low_stock_count', 1)
        );
});

test('refresh ai insights endpoint returns json response', function () {
    $this->actingAs($this->owner)
        ->postJson(route('catalog.ai-insights'))
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'insights' => [
                'headline',
                'peak_day_insight',
                'restock_advice',
                'merchandising_tip',
            ],
        ]);
});
