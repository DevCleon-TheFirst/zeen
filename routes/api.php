<?php

use App\Http\Controllers\InboundWebhookController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\WebhookController;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks')->group(function () {
    // Telegram
    Route::post('/telegram/{business}', [WebhookController::class, 'telegram'])->name('webhooks.telegram');

    // WhatsApp Cloud API
    Route::get('/whatsapp/{business}', fn (Request $req, Business $business) => app(WebhookController::class)->verifyMeta($req, $business, 'whatsapp')
    )->name('webhooks.whatsapp.verify');
    Route::post('/whatsapp/{business}', [WebhookController::class, 'whatsapp'])->name('webhooks.whatsapp');
    Route::post('/whatsapp-qr/{business}', [WebhookController::class, 'whatsappQr'])->name('webhooks.whatsapp.qr');
    Route::post('/whatsapp-qr/{business}/ack', [WebhookController::class, 'whatsappQrAck'])->name('webhooks.whatsapp.qr.ack');

    // Facebook Messenger
    Route::get('/messenger/{business}', fn (Request $req, Business $business) => app(WebhookController::class)->verifyMeta($req, $business, 'messenger')
    )->name('webhooks.messenger.verify');
    Route::post('/messenger/{business}', [WebhookController::class, 'messenger'])->name('webhooks.messenger');

    // Payment Gateways
    Route::post('/payments/paystack', [PaymentWebhookController::class, 'paystackWebhook'])->name('webhooks.paystack');
    Route::post('/payments/monnify', [PaymentWebhookController::class, 'monnifyWebhook'])->name('webhooks.monnify');

    // Inbound automation webhook — any external system can fire a workflow via secret URL
    Route::post('/automation/{workflowId}/{secret}', [InboundWebhookController::class, 'receive'])->name('webhooks.automation');
});
