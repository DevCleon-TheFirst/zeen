<?php

namespace App\Http\Controllers;

use App\Models\DigitalCode;
use App\Models\Payment;
use App\Services\TenantContext;
use App\Services\Vouchers\DigitalVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    // ─── Payments Ledger ───────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $business = TenantContext::get();

        $payments = Payment::where('business_id', $business->id)
            ->with(['customer', 'digitalCode', 'catalogItem'])
            ->latest()
            ->paginate(30);

        $stats = [
            'total_revenue' => Payment::where('business_id', $business->id)
                ->completed()
                ->sum('amount_kobo') / 100,
            'total_transactions' => Payment::where('business_id', $business->id)
                ->completed()
                ->count(),
            'pending' => Payment::where('business_id', $business->id)
                ->pending()
                ->count(),
            'currency' => 'NGN',
        ];

        $settings = $business->settings ?? [];
        $appUrl = config('app.url');

        $gatewaySettings = [
            'active_gateway' => $settings['active_payment_gateway'] ?? 'test',
            'paystack' => [
                'public_key' => $settings['paystack_public_key'] ?? '',
                'has_secret' => ! empty($settings['paystack_secret_key']),
                'webhook_url' => "{$appUrl}/api/webhooks/payments/paystack",
            ],
            'monnify' => [
                'api_key' => $settings['monnify_api_key'] ?? '',
                'contract_code' => $settings['monnify_contract_code'] ?? '',
                'mode' => $settings['monnify_mode'] ?? 'test',
                'has_secret' => ! empty($settings['monnify_secret_key']),
                'webhook_url' => "{$appUrl}/api/webhooks/payments/monnify",
            ],
        ];

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'stats' => $stats,
            'gatewaySettings' => $gatewaySettings,
        ]);
    }

    // ─── Voucher / Code Inventory ──────────────────────────────────────────────

    public function vouchersIndex(Request $request): Response
    {
        $business = TenantContext::get();

        $codes = DigitalCode::where('business_id', $business->id)
            ->with(['customer', 'payment'])
            ->latest()
            ->paginate(30);

        $summary = DigitalCode::where('business_id', $business->id)
            ->selectRaw('category, status, COUNT(*) as count')
            ->groupBy('category', 'status')
            ->get()
            ->groupBy('category');

        return Inertia::render('Vouchers/Index', [
            'codes' => $codes,
            'summary' => $summary,
        ]);
    }

    /**
     * Batch generate codes.
     */
    public function batchGenerate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:wifi_voucher,ticket,booking_pin,license_key,gift_card,event_code'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'prefix' => ['nullable', 'string', 'max:10'],
            'valid_duration_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        $business = TenantContext::get();
        $service = new DigitalVoucherService;

        $service->batchGenerate(
            $business,
            $data['quantity'],
            $data['category'],
            $data['valid_duration_minutes'] ?? 1440,
            $data['prefix'] ?? '',
        );

        return back()->with('success', "{$data['quantity']} codes generated successfully.");
    }

    /**
     * Mark a code as redeemed manually.
     */
    /**
     * Update payment gateway settings for active business.
     */
    public function updateGatewaySettings(Request $request): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $validated = $request->validate([
            'active_gateway' => ['required', 'string', 'in:paystack,monnify,test'],
            'paystack_public_key' => ['nullable', 'string'],
            'paystack_secret_key' => ['nullable', 'string'],
            'monnify_api_key' => ['nullable', 'string'],
            'monnify_secret_key' => ['nullable', 'string'],
            'monnify_contract_code' => ['nullable', 'string'],
            'monnify_mode' => ['nullable', 'string', 'in:test,live'],
            'owner_telegram_chat_id' => ['nullable', 'string'],
        ]);

        $settings = $business->settings ?? [];

        $settings['active_payment_gateway'] = $validated['active_gateway'];

        if (! empty($validated['paystack_public_key'])) {
            $settings['paystack_public_key'] = trim($validated['paystack_public_key']);
        }
        if (! empty($validated['paystack_secret_key'])) {
            $settings['paystack_secret_key'] = trim($validated['paystack_secret_key']);
        }

        if (! empty($validated['monnify_api_key'])) {
            $settings['monnify_api_key'] = trim($validated['monnify_api_key']);
        }
        if (! empty($validated['monnify_secret_key'])) {
            $settings['monnify_secret_key'] = trim($validated['monnify_secret_key']);
        }
        if (! empty($validated['monnify_contract_code'])) {
            $settings['monnify_contract_code'] = trim($validated['monnify_contract_code']);
        }
        if (! empty($validated['monnify_mode'])) {
            $settings['monnify_mode'] = $validated['monnify_mode'];
        }
        if (isset($validated['owner_telegram_chat_id'])) {
            $settings['owner_telegram_chat_id'] = trim($validated['owner_telegram_chat_id']);
        }

        $business->settings = $settings;
        $business->save();

        return back()->with('success', 'Payment gateway settings updated successfully.');
    }
}
