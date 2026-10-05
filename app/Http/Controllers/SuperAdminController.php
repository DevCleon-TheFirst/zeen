<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $businesses = Business::withCount(['users', 'conversations', 'channels'])
            ->latest()
            ->paginate(15);

        $stats = [
            'total_businesses' => Business::count(),
            'active_businesses' => Business::where('is_active', true)->count(),
            'total_users' => User::count(),
            'total_orders' => Order::count(),
            'total_gmv' => (float) Payment::completed()->sum('amount_kobo') / 100,
            'total_conversations' => Conversation::count(),
            'total_messages' => Message::count(),
        ];

        $recentPayments = Payment::with(['business', 'customer'])
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'businesses' => $businesses,
            'stats' => $stats,
            'recentPayments' => $recentPayments,
        ]);
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
}
