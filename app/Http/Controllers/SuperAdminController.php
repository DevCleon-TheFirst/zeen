<?php

namespace App\Http\Controllers;

use App\Models\AiCreditTransaction;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->input('search', ''));

        $query = Business::withCount(['users', 'conversations', 'channels', 'orders']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('industry', 'like', "%{$search}%")
                    ->orWhere('plan', 'like', "%{$search}%");
            });
        }

        $businesses = $query->latest()->paginate(15)->withQueryString();

        // ─── Financial & SaaS Metrics ──────────────────────────────────────────
        $planPricing = [
            'free' => 0,
            'starter' => 15000,
            'pro' => 35000,
            'enterprise' => 75000,
        ];

        $planBreakdown = Business::where('is_active', true)
            ->selectRaw('plan, count(*) as count')
            ->groupBy('plan')
            ->pluck('count', 'plan')
            ->toArray();

        $estimatedMrr = 0;
        foreach ($planBreakdown as $plan => $count) {
            $estimatedMrr += ($planPricing[$plan] ?? 0) * (int) $count;
        }

        // ─── AI Token & Credit Analytics ───────────────────────────────────────
        $totalCreditsInCirculation = (int) Business::sum('ai_credits_balance');
        $totalTokensConsumed = (int) AiCreditTransaction::sum('tokens_used');
        $totalAiCostUsd = (float) AiCreditTransaction::sum('cost_usd');

        // Estimate provider USD cost in NGN (assuming conservative ₦1,600 / $)
        $estimatedAiCostNgn = $totalAiCostUsd * 1600;
        $totalPlatformGmv = (float) Payment::completed()->sum('amount_kobo') / 100;

        // ─── System Health & Infrastructure ────────────────────────────────────
        $pendingJobs = 0;
        $failedJobs = 0;
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            // Ignore if queue tables not accessible
        }

        $gatewayHealthy = false;
        try {
            $gatewayUrl = config('services.whatsapp.gateway_url', 'http://127.0.0.1:3000');
            $res = Http::timeout(2)->get("{$gatewayUrl}/status");
            $gatewayHealthy = $res->successful();
        } catch (\Throwable) {
            $gatewayHealthy = false;
        }

        // ─── Live Upstream DeepSeek Balance ─────────────────────────────────
        $deepseekBalance = app(AiService::class)->getDeepSeekBalance();

        $stats = [
            'total_businesses' => Business::count(),
            'active_businesses' => Business::where('is_active', true)->count(),
            'total_users' => User::count(),
            'total_orders' => Order::count(),
            'total_gmv' => $totalPlatformGmv,
            'total_conversations' => Conversation::count(),
            'total_messages' => Message::count(),
            'estimated_mrr' => $estimatedMrr,
            'plan_breakdown' => [
                'free' => $planBreakdown['free'] ?? 0,
                'starter' => $planBreakdown['starter'] ?? 0,
                'pro' => $planBreakdown['pro'] ?? 0,
                'enterprise' => $planBreakdown['enterprise'] ?? 0,
            ],
            'total_credits_in_circulation' => $totalCreditsInCirculation,
            'total_tokens_consumed' => $totalTokensConsumed,
            'total_ai_cost_usd' => $totalAiCostUsd,
            'estimated_ai_cost_ngn' => $estimatedAiCostNgn,
            'deepseek_balance' => $deepseekBalance,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'gateway_healthy' => $gatewayHealthy,
        ];

        $recentPayments = Payment::with(['business', 'customer'])
            ->latest()
            ->limit(8)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'businesses' => $businesses,
            'stats' => $stats,
            'recentPayments' => $recentPayments,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function deepseekBalance(): JsonResponse
    {
        $balance = app(AiService::class)->getDeepSeekBalance();

        return response()->json($balance);
    }

    public function toggleBusiness(Business $business): RedirectResponse
    {
        $business->update(['is_active' => ! $business->is_active]);
        $status = $business->is_active ? 'activated' : 'suspended';

        return back()->with('success', "Business {$business->name} has been {$status}.");
    }

    public function updatePlan(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:free,starter,pro,enterprise'],
        ]);

        $business->update(['plan' => $validated['plan']]);

        return back()->with('success', "Plan for {$business->name} updated to ".strtoupper($validated['plan']));
    }

    public function adjustCredits(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $newBalance = max(0, (int) $business->ai_credits_balance + (int) $validated['amount']);
        $business->update(['ai_credits_balance' => $newBalance]);

        AiCreditTransaction::create([
            'business_id' => $business->id,
            'amount' => $validated['amount'],
            'balance_after' => $newBalance,
            'type' => $validated['amount'] > 0 ? 'bonus' : 'adjustment',
            'description' => $validated['reason'] ?: 'Super Admin manual adjustment',
        ]);

        return back()->with('success', "Added {$validated['amount']} credits to {$business->name}. New balance: {$newBalance}");
    }

    public function impersonate(Request $request, Business $business): RedirectResponse
    {
        $user = $request->user();
        $user->update(['business_id' => $business->id]);
        session(['impersonated_business_id' => $business->id]);

        return redirect()->route('dashboard')->with('success', "Now inspecting {$business->name} as Administrator.");
    }

    public function leaveImpersonation(Request $request): RedirectResponse
    {
        session()->forget('impersonated_business_id');

        return redirect()->route('admin.dashboard')->with('success', 'Returned to Platform Command Center.');
    }
}
