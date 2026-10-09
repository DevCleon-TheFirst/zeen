<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AiProviderSetting;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'initialPlan' => $request->query('plan', 'starter'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            'industry' => 'required|string|max:100',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'plan' => ['nullable', 'string', 'in:starter,pro,enterprise'],
        ]);

        $selectedPlan = $validated['plan'] ?? 'starter';
        $initialCredits = match ($selectedPlan) {
            'enterprise' => 25000,
            'pro' => 8000,
            default => 2500,
        };

        $user = DB::transaction(function () use ($validated, $selectedPlan, $initialCredits) {
            $slugBase = Str::slug($validated['business_name']);
            $slug = $slugBase ?: 'business';
            if (Business::where('slug', $slug)->exists()) {
                $slug .= '-'.Str::lower(Str::random(5));
            }

            $business = Business::create([
                'name' => $validated['business_name'],
                'slug' => $slug,
                'industry' => $validated['industry'],
                'timezone' => 'UTC',
                'is_active' => true,
                'plan' => $selectedPlan,
                'ai_credits_balance' => $initialCredits,
            ]);

            // Default AI Provider set to DeepSeek per requirement
            AiProviderSetting::create([
                'business_id' => $business->id,
                'provider' => 'deepseek',
                'api_key' => 'sk-placeholder',
                'base_url' => 'https://api.deepseek.com',
                'model' => 'deepseek-chat',
                'temperature' => 0.70,
                'max_tokens' => 2000,
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'business_id' => $business->id,
                'role' => UserRole::Owner,
                'is_active' => true,
            ]);

            $user->businesses()->attach($business->id, ['role' => 'owner']);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
