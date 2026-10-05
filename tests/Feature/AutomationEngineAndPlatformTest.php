<?php

use App\Enums\UserRole;
use App\Jobs\CheckAbandonedCheckoutJob;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\AutomationExecution;
use App\Models\AutomationWorkflow;
use App\Models\Business;
use App\Models\BusinessCatalogItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Automation\AutomationEngineService;
use App\Services\Automation\AutomationPlanGuard;
use App\Services\Automation\AutomationTriggerDispatcher;
use App\Services\Automation\Nodes\ActionAddTagHandler;
use App\Services\Automation\Nodes\ActionHttpRequestHandler;
use App\Services\Automation\Nodes\ActionSearchCatalogHandler;
use App\Services\Automation\Nodes\ConditionCheckFieldHandler;
use App\Services\Automation\Nodes\DelayHandler;
use App\Services\Automation\Nodes\SendMessageHandler;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Auto Corp',
        'slug' => 'auto-'.uniqid(),
        'industry' => 'services',
        'timezone' => 'UTC',
        'is_active' => true,
        'plan' => 'free',
    ]);
    TenantContext::set($this->business);
});

test('AutomationPlanGuard enforces free plan active workflow limit and pro tier', function () {
    $wf1 = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Flow 1',
        'trigger_type' => 'new_message',
        'is_active' => true,
    ]);

    $wf2 = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Flow 2',
        'trigger_type' => 'new_message',
        'is_active' => true,
    ]);

    $wf3 = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Flow 3',
        'trigger_type' => 'new_message',
        'is_active' => false,
    ]);

    // Free plan max 2 active
    $check = AutomationPlanGuard::canActivate($wf3, $this->business);
    expect($check['allowed'])->toBeFalse()
        ->and($check['reason'])->toContain('Free plan is limited to 2 active workflows');

    // Upgrade to Pro
    $this->business->update(['plan' => 'pro']);
    $proCheck = AutomationPlanGuard::canActivate($wf3, $this->business);
    expect($proCheck['allowed'])->toBeTrue();
});

test('ConditionCheckFieldHandler evaluates tag presence and scalar fields', function () {
    $customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'tags' => ['VIP', 'EarlyAdopter'],
    ]);

    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Condition Test',
        'trigger_type' => 'new_message',
        'is_active' => true,
    ]);

    $node = $workflow->nodes()->create([
        'node_id' => 'check_tag',
        'type' => 'condition_check_field',
        'config' => [
            'subject' => 'customer',
            'field' => 'tags',
            'operator' => 'contains',
            'value' => 'VIP',
        ],
    ]);

    $execution = AutomationExecution::create([
        'workflow_id' => $workflow->id,
        'customer_id' => $customer->id,
        'status' => 'running',
        'context' => ['customer_id' => $customer->id],
    ]);

    $handler = new ConditionCheckFieldHandler;
    $result = $handler->handle($node, $execution);
    expect($result)->toBe('yes');

    // Test false condition
    $node->config = [
        'subject' => 'customer',
        'field' => 'tags',
        'operator' => 'contains',
        'value' => 'NonExistentTag',
    ];
    $node->save();

    $resultFalse = $handler->handle($node, $execution);
    expect($resultFalse)->toBe('no');
});

test('ActionAddTagHandler adds new tags to customer', function () {
    $customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'tags' => ['Lead'],
    ]);

    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Tagging Test',
        'trigger_type' => 'new_message',
        'is_active' => true,
    ]);

    $node = $workflow->nodes()->create([
        'node_id' => 'tag_node',
        'type' => 'action_add_tag',
        'config' => ['tag' => 'Converted'],
    ]);

    $execution = AutomationExecution::create([
        'workflow_id' => $workflow->id,
        'customer_id' => $customer->id,
        'status' => 'running',
        'context' => ['customer_id' => $customer->id],
    ]);

    $handler = new ActionAddTagHandler;
    $result = $handler->handle($node, $execution);

    expect($result['action'])->toBe('tag_added');
    $customer->refresh();
    expect($customer->tags)->toContain('Converted');
});

test('ActionHttpRequestHandler sends outbound HTTP requests with interpolated variables', function () {
    Http::fake([
        'https://external-api.com/leads' => Http::response(['status' => 'received'], 200),
    ]);

    $customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'Bob Jones',
        'email' => 'bob@example.com',
    ]);

    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Webhook Test',
        'trigger_type' => 'new_message',
        'is_active' => true,
    ]);

    $node = $workflow->nodes()->create([
        'node_id' => 'webhook_node',
        'type' => 'action_http_request',
        'config' => [
            'method' => 'POST',
            'url' => 'https://external-api.com/leads',
            'body' => ['user' => '{{customer.name}}', 'email' => '{{customer.email}}'],
        ],
    ]);

    $execution = AutomationExecution::create([
        'workflow_id' => $workflow->id,
        'customer_id' => $customer->id,
        'status' => 'running',
        'context' => ['customer_id' => $customer->id],
    ]);

    $handler = new ActionHttpRequestHandler;
    $result = $handler->handle($node, $execution);

    expect($result['status_code'])->toBe(200);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://external-api.com/leads'
            && $request['user'] === 'Bob Jones'
            && $request['email'] === 'bob@example.com';
    });
});

test('DelayHandler sets execution status to waiting and re-dispatches job with delay', function () {
    Queue::fake();

    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Drip Sequence',
        'trigger_type' => 'new_message',
        'is_active' => true,
    ]);

    $delayNode = $workflow->nodes()->create([
        'node_id' => 'delay_1',
        'type' => 'delay',
        'config' => ['delay_minutes' => 30],
    ]);

    $nextNode = $workflow->nodes()->create([
        'node_id' => 'next_action',
        'type' => 'action_add_tag',
        'config' => ['tag' => 'FollowedUp'],
    ]);

    $workflow->edges()->create([
        'edge_id' => 'edge_1',
        'source_node_id' => 'delay_1',
        'target_node_id' => 'next_action',
    ]);

    $execution = AutomationExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'running',
        'current_node_id' => 'delay_1',
    ]);

    $handler = new DelayHandler;
    $result = $handler->handle($delayNode, $execution);

    expect($result['action'])->toBe('delay_started');
    $execution->refresh();
    expect($execution->status)->toBe('waiting')
        ->and($execution->current_node_id)->toBe('next_action');

    Queue::assertPushed(ExecuteWorkflowJob::class);
});

test('user can view execution logs and retry failed runs', function () {
    $user = User::create([
        'name' => 'Admin User',
        'email' => 'admin_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);

    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'History Test',
        'trigger_type' => 'new_message',
        'is_active' => true,
    ]);

    $execution = AutomationExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'failed',
        'error_message' => 'Simulated failure',
        'started_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('automations.executions', $workflow->id));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Automations/Executions')
            ->has('executions.data', 1)
        );

    // Retry execution
    Queue::fake();
    $retryResponse = $this->actingAs($user)->post(route('automations.executions.retry', [$workflow->id, $execution->id]));
    $retryResponse->assertRedirect();

    $execution->refresh();
    expect($execution->status)->toBe('pending')
        ->and($execution->error_message)->toBeNull();

    Queue::assertPushed(ExecuteWorkflowJob::class);
});

test('SendMessageHandler interpolates customer, order, checkout, and catalog variables', function () {
    $customer = Customer::create([
        'business_id' => $this->business->id,
        'name' => 'Chioma Adebayo',
        'email' => 'chioma@example.com',
        'phone' => '+2348011223344',
    ]);

    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Template Interpolation Flow',
        'trigger_type' => 'order_created',
        'is_active' => true,
    ]);

    $execution = AutomationExecution::create([
        'workflow_id' => $workflow->id,
        'customer_id' => $customer->id,
        'status' => 'running',
        'context' => [
            'customer_id' => $customer->id,
            'tracking_code' => 'ORD-TEST-9988',
            'total_amount' => 12500.50,
            'currency' => 'NGN',
            'checkout_url' => 'https://paystack.com/pay/test1234',
            'catalog_summary' => "• Black Oxford Shoes - NGN 15,000.00\n• Brown Loafers - NGN 12,500.00",
        ],
    ]);

    $handler = app(SendMessageHandler::class);
    $template = 'Hi {{customer.name}} ({{customer.phone}}), your order {{order.tracking_code}} for {{order.currency}} {{order.total_amount}} is ready. Pay at {{checkout_url}}. Items: {{catalog.summary}}';
    $interpolated = $handler->interpolateVariables($template, $execution);

    expect($interpolated)->toContain('Hi Chioma Adebayo (+2348011223344)')
        ->and($interpolated)->toContain('ORD-TEST-9988')
        ->and($interpolated)->toContain('NGN 12,500.50')
        ->and($interpolated)->toContain('https://paystack.com/pay/test1234')
        ->and($interpolated)->toContain('Black Oxford Shoes');
});

test('ActionSearchCatalogHandler searches active items and populates execution context', function () {
    BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Wireless Bluetooth Earbuds',
        'description' => 'Noise cancelling with 24hr battery',
        'price' => 25000.00,
        'currency' => 'NGN',
        'category' => 'electronics',
        'stock_quantity' => 15,
        'track_inventory' => true,
        'is_active' => true,
    ]);

    BusinessCatalogItem::create([
        'business_id' => $this->business->id,
        'name' => 'Wired Studio Headphones',
        'description' => 'Professional studio reference headphones',
        'price' => 45000.00,
        'currency' => 'NGN',
        'category' => 'electronics',
        'stock_quantity' => 0,
        'track_inventory' => true,
        'is_active' => true,
    ]);

    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Catalog Search Flow',
        'trigger_type' => 'new_message',
        'is_active' => true,
    ]);

    $node = $workflow->nodes()->create([
        'node_id' => 'search_node',
        'type' => 'action_search_catalog',
        'config' => [
            'query' => 'Bluetooth',
            'limit' => 3,
        ],
    ]);

    $execution = AutomationExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'running',
        'context' => [],
    ]);

    $handler = new ActionSearchCatalogHandler;
    $result = $handler->handle($node, $execution);

    expect($result['results_count'])->toBe(1)
        ->and($result['catalog_summary'])->toContain('Wireless Bluetooth Earbuds');

    $execution->refresh();
    expect($execution->context['catalog_summary'])->toContain('Wireless Bluetooth Earbuds')
        ->and($execution->context['catalog_results'])->toHaveCount(1)
        ->and($execution->context['catalog_results'][0]['name'])->toBe('Wireless Bluetooth Earbuds');
});

test('AutomationEngineService respects condition labels and breaks infinite loops', function () {
    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Loop & Condition Flow',
        'trigger_type' => 'new_message',
        'is_active' => true,
    ]);

    // Create circular loop between node_a and node_b
    $workflow->nodes()->create([
        'node_id' => 'node_a',
        'type' => 'action_add_tag',
        'config' => ['tag' => 'LoopTagA'],
    ]);

    $workflow->nodes()->create([
        'node_id' => 'node_b',
        'type' => 'action_add_tag',
        'config' => ['tag' => 'LoopTagB'],
    ]);

    $workflow->edges()->create([
        'edge_id' => 'edge_ab',
        'source_node_id' => 'node_a',
        'target_node_id' => 'node_b',
    ]);

    $workflow->edges()->create([
        'edge_id' => 'edge_ba',
        'source_node_id' => 'node_b',
        'target_node_id' => 'node_a',
    ]);

    $execution = AutomationExecution::create([
        'workflow_id' => $workflow->id,
        'status' => 'pending',
        'current_node_id' => 'node_a',
    ]);

    $engine = new AutomationEngineService;
    $engine->execute($execution);

    $execution->refresh();
    expect($execution->status)->toBe('failed')
        ->and($execution->error_message)->toContain('Circular edge loop detected');
});

test('AutomationTriggerDispatcher filters by keyword and match type', function () {
    Queue::fake();

    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Keyword Promo Flow',
        'trigger_type' => 'new_message',
        'trigger_config' => [
            'keyword_filter' => 'PROMO',
            'keyword_match_type' => 'equals',
        ],
        'is_active' => true,
    ]);

    $workflow->nodes()->create([
        'node_id' => 'start_node',
        'type' => 'action_send_message',
        'config' => ['message' => 'Here is your 20% promo code!'],
    ]);

    // Test non-matching message
    AutomationTriggerDispatcher::dispatch('new_message', [
        'message_text' => 'Hello there',
    ], $this->business);

    Queue::assertNothingPushed();

    // Test matching message
    AutomationTriggerDispatcher::dispatch('new_message', [
        'message_text' => 'promo',
    ], $this->business);

    Queue::assertPushed(ExecuteWorkflowJob::class);
});

test('CheckAbandonedCheckoutJob dispatches abandoned_checkout trigger for pending payment', function () {
    Queue::fake();

    $payment = Payment::create([
        'business_id' => $this->business->id,
        'gateway' => 'test',
        'currency' => 'NGN',
        'amount_kobo' => 500000,
        'reference' => 'PAY-TEST-ABANDONED',
        'status' => 'pending',
        'checkout_url' => 'https://checkout.test/pay/PAY-TEST-ABANDONED',
    ]);

    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Abandoned Cart Recovery',
        'trigger_type' => 'abandoned_checkout',
        'is_active' => true,
    ]);

    $workflow->nodes()->create([
        'node_id' => 'start_recovery',
        'type' => 'action_send_message',
        'config' => ['message' => 'Did you forget something? Return to {{checkout_url}} to complete order.'],
    ]);

    $job = new CheckAbandonedCheckoutJob($payment);
    $job->handle();

    Queue::assertPushed(ExecuteWorkflowJob::class);
});

test('OrderController updateStatus dispatches order_status_updated trigger', function () {
    Queue::fake();

    $user = User::create([
        'name' => 'Staff Member',
        'email' => 'staff_'.uniqid().'@test.com',
        'password' => bcrypt('password'),
        'business_id' => $this->business->id,
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);

    $order = Order::create([
        'business_id' => $this->business->id,
        'tracking_code' => 'ORD-TRACK-5566',
        'status' => 'confirmed',
        'total_amount' => 7500.00,
        'currency' => 'NGN',
        'customer_name' => 'Tunde Bakare',
        'customer_phone' => '+2348099887766',
    ]);

    $workflow = AutomationWorkflow::create([
        'business_id' => $this->business->id,
        'name' => 'Dispatch Notification Flow',
        'trigger_type' => 'order_status_updated',
        'trigger_config' => ['order_status' => 'dispatched'],
        'is_active' => true,
    ]);

    $workflow->nodes()->create([
        'node_id' => 'dispatch_node',
        'type' => 'action_send_message',
        'config' => ['message' => 'Your order {{order.tracking_code}} has been dispatched!'],
    ]);

    $response = $this->actingAs($user)->patch(route('orders.status', $order->id), [
        'status' => 'dispatched',
    ]);

    $response->assertRedirect();
    $order->refresh();
    expect($order->status)->toBe('dispatched');

    Queue::assertPushed(ExecuteWorkflowJob::class);
});
