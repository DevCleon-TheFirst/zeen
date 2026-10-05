<?php

namespace App\Http\Controllers;

use App\Models\AiProviderSetting;
use App\Models\Business;
use App\Models\BusinessChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessWorkspaceController extends Controller
{
    /**
     * Switch user's active workspace.
     */
    public function switch(Request $request, Business $business): RedirectResponse
    {
        $user = $request->user();

        $hasAccess = $user->is_super_admin || $user->businesses()->where('businesses.id', $business->id)->exists();

        if (! $hasAccess) {
            abort(403, 'You do not have access to this business workspace.');
        }

        $user->update(['business_id' => $business->id]);

        return back()->with('success', "Switched workspace to {$business->name}");
    }

    /**
     * Create a new business workspace and switch to it.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'industry' => 'required|string|in:real_estate,retail,hospitality,healthcare,services,general',
            'description' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        $slugBase = Str::slug($request->name);
        $slug = $slugBase ?: 'business';
        if (Business::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(5));
        }

        $business = Business::create([
            'name' => $request->name,
            'slug' => $slug,
            'industry' => $request->industry,
            'description' => $request->description,
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        // Copy active business AI settings if available, or create default
        $currentProvider = $user->business?->aiProviderSetting;
        AiProviderSetting::create([
            'business_id' => $business->id,
            'provider' => $currentProvider?->provider ?? 'deepseek',
            'api_key' => $currentProvider?->api_key ?? 'sk-placeholder',
            'base_url' => $currentProvider?->base_url ?? 'https://api.deepseek.com',
            'model' => $currentProvider?->model ?? 'deepseek-chat',
            'temperature' => $currentProvider?->temperature ?? 0.70,
            'max_tokens' => $currentProvider?->max_tokens ?? 2000,
            'is_active' => true,
        ]);

        // Attach user as owner and set as active workspace
        $user->businesses()->attach($business->id, ['role' => 'owner']);
        $user->update(['business_id' => $business->id]);

        // Copy existing channels (e.g. Telegram bot token) from previous business so omnichannel works immediately
        $previousBusiness = $user->businesses()->where('businesses.id', '!=', $business->id)->first();
        if ($previousBusiness) {
            foreach ($previousBusiness->channels as $existingChannel) {
                BusinessChannel::firstOrCreate(
                    [
                        'business_id' => $business->id,
                        'channel' => $existingChannel->channel,
                    ],
                    [
                        'credentials' => $existingChannel->credentials,
                        'webhook_secret' => $existingChannel->webhook_secret,
                        'is_active' => $existingChannel->is_active,
                    ]
                );
            }
        }

        return back()->with('success', "Workspace \"{$business->name}\" created and active!");
    }

    /**
     * Update the dominant operating mode / industry of the current business.
     */
    public function updateDominantMode(Request $request): RedirectResponse
    {
        $request->validate([
            'industry' => 'required|string|in:real_estate,retail,hospitality,healthcare,services,general',
        ]);

        $user = $request->user();
        $business = $user->business;

        if (! $business) {
            abort(404, 'No active business found.');
        }

        $hasAccess = $user->is_super_admin || $user->businesses()->where('businesses.id', $business->id)->exists();
        if (! $hasAccess) {
            abort(403, 'Unauthorized.');
        }

        $business->update(['industry' => $request->industry]);

        $labels = [
            'real_estate' => 'Real Estate & Properties',
            'retail' => 'Online Store & Retail',
            'hospitality' => 'Hotels & Shortlets',
            'healthcare' => 'Healthcare & Clinics',
            'services' => 'Services & Appointments',
            'general' => 'General / Multi-domain',
        ];

        $label = $labels[$request->industry] ?? $request->industry;

        return back()->with('success', "Dominant focus set to {$label}. AI Agent & workflows updated!");
    }
}
