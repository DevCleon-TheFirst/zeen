<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? $request->user()->load('business') : null,
                'businesses' => function () use ($request) {
                    $user = $request->user();
                    if (! $user) {
                        return [];
                    }

                    if ($user->is_super_admin) {
                        return Business::select(['id', 'name', 'industry', 'slug', 'is_active'])->get();
                    }

                    $list = $user->businesses()->select(['businesses.id', 'businesses.name', 'businesses.industry', 'businesses.slug', 'businesses.is_active'])->get();
                    if ($list->isEmpty() && $user->business) {
                        $list = collect([$user->business]);
                    }

                    return $list;
                },
                'is_impersonating' => $request->session()->has('impersonated_business_id'),
                'impersonated_business' => function () use ($request) {
                    $id = $request->session()->get('impersonated_business_id');

                    return $id ? Business::select(['id', 'name', 'slug', 'industry'])->find($id) : null;
                },
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
