<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(): Response
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $staff = User::where('business_id', $business->id)
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value ?? 'agent',
                'role_label' => $user->role?->label() ?? 'Agent',
                'is_active' => (bool) $user->is_active,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'is_owner' => $user->isOwner(),
                'created_at' => $user->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Staff/Index', [
            'staff' => $staff,
            'roles' => [
                ['value' => 'admin', 'label' => 'Administrator', 'description' => 'Full access to channels, AI settings, staff, and automations.'],
                ['value' => 'manager', 'label' => 'Manager', 'description' => 'Can manage conversations, leads, appointments, and automations.'],
                ['value' => 'agent', 'label' => 'Agent', 'description' => 'Can chat with customers, view catalog, and manage appointments.'],
                ['value' => 'support', 'label' => 'Support', 'description' => 'Can handle customer support tickets and view basic catalog.'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:'.User::class,
            'role' => ['required', Rule::enum(UserRole::class)],
            'phone' => 'nullable|string|max:30',
            'password' => ['required', Rules\Password::defaults()],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => UserRole::from($validated['role']),
            'phone' => $validated['phone'],
            'business_id' => $business->id,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Staff member added successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $user->business_id === $business->id, 403);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => 'required|boolean',
        ]);

        // Prevent demoting the owner
        if ($user->isOwner() && $validated['role'] !== UserRole::Owner->value) {
            return redirect()->back()->with('error', 'Cannot change the role of the primary business owner.');
        }

        $user->update([
            'role' => UserRole::from($validated['role']),
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->back()->with('success', 'Staff member updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $user->business_id === $business->id, 403);

        if ($user->isOwner()) {
            return redirect()->back()->with('error', 'The business owner cannot be removed.');
        }

        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'Staff member removed.');
    }
}
