<?php

use App\Enums\ConversationState;
use App\Http\Controllers\AiSettingController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AutomationExecutionController;
use App\Http\Controllers\AutomationWorkflowController;
use App\Http\Controllers\BusinessWorkspaceController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SystemSetupController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

Route::post('/contact', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'company' => 'nullable|string|max:255',
        'venue_type' => 'nullable|string|max:100',
        'access_points' => 'nullable|string|max:50',
        'message' => 'required|string|max:2000',
    ]);

    Log::info('New Omnichannel Commerce Consultation Request:', $validated);

    if ($request->wantsJson()) {
        return response()->json([
            'success' => true,
            'message' => 'Thank you. Your consultation request has been received. Our solutions team will reach out shortly.',
        ]);
    }

    return back()->with('success', 'Thank you! Your inquiry has been received.');
})->name('contact.submit');

// Public Customer Payment Flows (Callback, Test Fulfill, and Success Screen)
Route::get('/payments/callback', [PaymentWebhookController::class, 'callback'])->name('payments.callback');
Route::get('/payments/{payment}/test-fulfill', [PaymentWebhookController::class, 'testFulfill'])->name('payments.test-fulfill');
Route::get('/payments/{payment:reference}/success', [PaymentWebhookController::class, 'success'])->name('payments.success');

Route::get('/dashboard', function () {
    $user = auth()->user();
    $business = $user?->business;

    $defaultTrend = [2, 5, 3, 8, 6, 9, 7];

    $stats = [
        'conversations_open' => 0,
        'conversations_ai' => 0,
        'conversations_escalated' => 0,
        'appointments_pending' => 0,
        'catalog_items' => 0,
        'active_automations' => 0,
        'channels' => [
            'telegram' => ['messages' => 0, 'ai_rate' => 0, 'trend' => [3, 5, 4, 8, 6, 10, 7], 'change' => '+0%', 'positive' => true],
            'whatsapp' => ['messages' => 0, 'ai_rate' => 0, 'trend' => [2, 4, 3, 6, 5, 8, 9],  'change' => '+0%', 'positive' => true],
            'messenger' => ['messages' => 0, 'ai_rate' => 0, 'trend' => [1, 3, 2, 5, 4, 6, 5],  'change' => '+0%', 'positive' => true],
        ],
    ];

    if ($business) {
        $stats['conversations_open'] = $business->conversations()->whereNotIn('status', [ConversationState::Resolved])->count();
        $stats['conversations_ai'] = $business->conversations()->where('status', ConversationState::AiHandling)->count();
        $stats['conversations_escalated'] = $business->conversations()->whereIn('status', [ConversationState::HumanHandling, ConversationState::Escalated])->count();
        $stats['appointments_pending'] = $business->appointments()->where('status', 'pending')->count();
        $stats['catalog_items'] = $business->catalogItems()->count();
        $stats['active_automations'] = $business->workflows()->where('is_active', true)->count();
    }

    return Inertia::render('Dashboard', ['stats' => $stats]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Omnichannel Live Inbox & Human Handover
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::post('/inbox/{conversation}/reply', [InboxController::class, 'reply'])->name('inbox.reply');
    Route::post('/inbox/{conversation}/handover', [InboxController::class, 'toggleHandover'])->name('inbox.handover');
    Route::post('/inbox/{conversation}/resolve', [InboxController::class, 'resolve'])->name('inbox.resolve');
    Route::post('/inbox/{conversation}/note', [InboxController::class, 'note'])->name('inbox.note');
    Route::post('/inbox/quick-product', [InboxController::class, 'quickProductStore'])->name('inbox.quick-product');
    Route::patch('/inbox/quick-stock/{item}', [InboxController::class, 'quickStockAdjust'])->name('inbox.quick-stock');
    Route::post('/inbox/{conversation}/send-product/{item}', [InboxController::class, 'sendProductCard'])->name('inbox.send-product');

    // AI Provider Settings (DeepSeek default, OpenAI, Anthropic, Custom)
    Route::get('/settings/ai', [AiSettingController::class, 'index'])->name('settings.ai');
    Route::post('/settings/ai', [AiSettingController::class, 'update'])->name('settings.ai.update');
    Route::post('/settings/ai/test', [AiSettingController::class, 'test'])->name('settings.ai.test');

    // Channels (WhatsApp, Telegram, Messenger)
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::get('/channels/qr/status', [ChannelController::class, 'getQrStatus'])->name('channels.qr.status');
    Route::post('/channels/qr/pairing-code', [ChannelController::class, 'requestPairingCode'])->name('channels.qr.pairing-code');
    Route::post('/channels/qr/disconnect', [ChannelController::class, 'disconnectQr'])->name('channels.qr.disconnect');
    Route::post('/channels/{channelType}', [ChannelController::class, 'update'])->name('channels.update');
    Route::post('/channels/{channelType}/test', [ChannelController::class, 'testConnection'])->name('channels.test');

    // Staff Management
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::patch('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');

    // Visual Automation Workflows (n8n-style)
    Route::get('/automations', [AutomationWorkflowController::class, 'index'])->name('automations.index');
    Route::get('/automations/create', [AutomationWorkflowController::class, 'create'])->name('automations.create');
    Route::post('/automations', [AutomationWorkflowController::class, 'store'])->name('automations.store');
    Route::get('/automations/{workflow}/edit', [AutomationWorkflowController::class, 'edit'])->name('automations.edit');
    Route::put('/automations/{workflow}', [AutomationWorkflowController::class, 'update'])->name('automations.update');
    Route::delete('/automations/{workflow}', [AutomationWorkflowController::class, 'destroy'])->name('automations.destroy');
    Route::post('/automations/templates/{template}/import', [AutomationWorkflowController::class, 'fromTemplate'])->name('automations.fromTemplate');
    Route::get('/automations/{workflow}/executions', [AutomationExecutionController::class, 'index'])->name('automations.executions');
    Route::post('/automations/{workflow}/executions/{execution}/retry', [AutomationExecutionController::class, 'retry'])->name('automations.executions.retry');

    // Business Catalog & Inventory (properties, products, rooms)
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::post('/catalog', [CatalogController::class, 'store'])->name('catalog.store');
    Route::put('/catalog/{item}', [CatalogController::class, 'update'])->name('catalog.update');
    Route::delete('/catalog/{item}', [CatalogController::class, 'destroy'])->name('catalog.destroy');
    Route::post('/catalog/ai-insights', [CatalogController::class, 'refreshAiInsights'])->name('catalog.ai-insights');

    // Appointments & Inspections
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');

    // E-Commerce Orders & Tracking
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

    // Payments Ledger & Gateway Settings
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/gateway-settings', [PaymentController::class, 'updateGatewaySettings'])->name('payments.gateway-settings');

    Route::get('/vouchers', [PaymentController::class, 'vouchersIndex'])->name('vouchers.index');
    Route::post('/vouchers/batch-generate', [PaymentController::class, 'batchGenerate'])->name('vouchers.batch-generate');
    Route::patch('/vouchers/{digitalCode}/redeem', [PaymentController::class, 'redeemCode'])->name('vouchers.redeem');

    // System Setup & Health
    Route::get('/setup', [SystemSetupController::class, 'index'])->name('setup.index');
    Route::get('/setup/health', [SystemSetupController::class, 'health'])->name('setup.health');
    Route::post('/setup/telegram-webhook', [SystemSetupController::class, 'registerTelegramWebhook'])->name('setup.telegram-webhook');
    Route::get('/setup/telegram-info', [SystemSetupController::class, 'telegramWebhookInfo'])->name('setup.telegram-info');
    Route::post('/setup/app-url', [SystemSetupController::class, 'updateAppUrl'])->name('setup.app-url');
    Route::post('/setup/migrate', [SystemSetupController::class, 'migrate'])->name('setup.migrate');
    Route::post('/setup/clear-caches', [SystemSetupController::class, 'clearCaches'])->name('setup.clear-caches');

    // Multi-Business Workspace Switching & Dominant Focus
    Route::post('/businesses', [BusinessWorkspaceController::class, 'store'])->name('businesses.store');
    Route::post('/businesses/{business}/switch', [BusinessWorkspaceController::class, 'switch'])->name('businesses.switch');
    Route::patch('/businesses/dominant-mode', [BusinessWorkspaceController::class, 'updateDominantMode'])->name('businesses.dominant-mode');

    // Super Admin Platform Oversight
    Route::middleware([EnsureSuperAdmin::class])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [SuperAdminController::class, 'index'])->name('dashboard');
        Route::post('/businesses/{business}/toggle', [SuperAdminController::class, 'toggleBusiness'])->name('businesses.toggle');
        Route::patch('/businesses/{business}/plan', [SuperAdminController::class, 'updatePlan'])->name('businesses.plan');
        Route::post('/businesses/{business}/credits', [SuperAdminController::class, 'adjustCredits'])->name('businesses.credits');
    });
});

require __DIR__.'/auth.php';
