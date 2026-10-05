<?php

namespace App\Http\Controllers;

use App\Models\BusinessCatalogItem;
use App\Services\Catalog\CatalogAiAdvisorService;
use App\Services\Catalog\CatalogAnalyticsService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __construct(
        protected CatalogAnalyticsService $analyticsService,
        protected CatalogAiAdvisorService $aiAdvisorService,
    ) {}

    public function index(Request $request): Response
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $category = $request->query('category');
        $status = $request->query('status');
        $search = $request->query('search');

        $query = BusinessCatalogItem::where('business_id', $business->id)
            ->orderBy('id', 'desc');

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($status && $status !== 'all') {
            $query->where('availability_status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $stats = $this->analyticsService->getSummaryStats($business);
        $itemMetrics = $this->analyticsService->getItemMetricsMap($business);
        $dayOfWeekTrends = $this->analyticsService->getDayOfWeekTrends($business);
        $aiInsights = $this->aiAdvisorService->getInsights($business);

        $items = $query->paginate(15)->withQueryString()->through(function (BusinessCatalogItem $item) use ($itemMetrics) {
            $m = $itemMetrics[$item->id] ?? [
                'units_sold' => 0,
                'total_revenue' => 0.0,
                'order_count' => 0,
                'last_sold_at' => null,
                'daily_velocity' => 0.0,
                'predicted_stockout_days' => null,
                'stockout_risk' => 'untracked',
                'recommended_restock' => null,
            ];

            return [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'price' => (float) $item->price,
                'currency' => $item->currency,
                'formatted_price' => $item->price ? number_format($item->price, 2).' '.$item->currency : 'Contact for Price',
                'category' => $item->category,
                'availability_status' => $item->availability_status,
                'stock_quantity' => $item->stock_quantity,
                'track_inventory' => (bool) $item->track_inventory,
                'attributes' => $item->attributes ?? [],
                'images' => $item->images ?? [],
                'image_url' => ! empty($item->images) ? $item->images[0] : null,
                'sizes_str' => ! empty($item->attributes['sizes']) ? implode(', ', (array) $item->attributes['sizes']) : '',
                'is_active' => (bool) $item->is_active,
                'units_sold' => $m['units_sold'],
                'total_revenue' => $m['total_revenue'],
                'formatted_revenue' => number_format($m['total_revenue'], 2).' '.$item->currency,
                'daily_velocity' => $m['daily_velocity'],
                'predicted_stockout_days' => $m['predicted_stockout_days'],
                'stockout_risk' => $m['stockout_risk'],
                'recommended_restock' => $m['recommended_restock'],
                'created_at' => $item->created_at?->format('M j, Y'),
            ];
        });

        $categories = BusinessCatalogItem::where('business_id', $business->id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return Inertia::render('Catalog/Index', [
            'items' => $items,
            'categories' => $categories,
            'stats' => $stats,
            'day_of_week_trends' => $dayOfWeekTrends,
            'ai_insights' => $aiInsights,
            'filters' => [
                'category' => $category ?? 'all',
                'status' => $status ?? 'all',
                'search' => $search ?? '',
            ],
            'industry' => $business->industry,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:10',
            'category' => 'nullable|string|max:100',
            'availability_status' => 'required|string|in:available,unavailable,coming_soon',
            'stock_quantity' => 'nullable|integer|min:0',
            'track_inventory' => 'nullable|boolean',
            'sizes' => 'nullable|string|max:255',
            'image_url' => 'nullable|string|max:1000',
            'image_file' => 'nullable|file|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'attributes' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $attributes = $validated['attributes'] ?? [];
        if (! empty($validated['sizes'])) {
            $attributes['sizes'] = array_values(array_filter(array_map('trim', explode(',', $validated['sizes']))));
        }

        $images = [];
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('catalog', 'public');
            $images[] = asset('storage/'.$path);
        } elseif (! empty($validated['image_url'])) {
            $images[] = trim($validated['image_url']);
        }

        $stock = isset($validated['stock_quantity']) ? (int) $validated['stock_quantity'] : null;
        $status = $validated['availability_status'];
        if ($stock !== null && $stock === 0) {
            $status = 'unavailable';
        }

        BusinessCatalogItem::create([
            'business_id' => $business->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'] ?? null,
            'currency' => strtoupper($validated['currency']),
            'category' => $validated['category'] ?? null,
            'availability_status' => $status,
            'stock_quantity' => $stock,
            'track_inventory' => $validated['track_inventory'] ?? true,
            'attributes' => ! empty($attributes) ? $attributes : null,
            'images' => ! empty($images) ? $images : null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'Catalog item added successfully.');
    }

    public function update(Request $request, BusinessCatalogItem $item): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $item->business_id === $business->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:10',
            'category' => 'nullable|string|max:100',
            'availability_status' => 'required|string|in:available,unavailable,coming_soon',
            'stock_quantity' => 'nullable|integer|min:0',
            'track_inventory' => 'nullable|boolean',
            'sizes' => 'nullable|string|max:255',
            'image_url' => 'nullable|string|max:1000',
            'image_file' => 'nullable|file|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'is_active' => 'boolean',
        ]);

        $attributes = $item->attributes ?? [];
        if (isset($validated['sizes'])) {
            $attributes['sizes'] = array_values(array_filter(array_map('trim', explode(',', $validated['sizes']))));
        }

        $images = $item->images ?? [];
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('catalog', 'public');
            $images = [asset('storage/'.$path)];
        } elseif (! empty($validated['image_url'])) {
            $images = [trim($validated['image_url'])];
        }

        $stock = isset($validated['stock_quantity']) ? (int) $validated['stock_quantity'] : $item->stock_quantity;
        $status = $validated['availability_status'];
        if ($stock !== null && $stock === 0) {
            $status = 'unavailable';
        } elseif ($stock !== null && $stock > 0 && $status === 'unavailable') {
            $status = 'available';
        }

        $item->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'] ?? null,
            'currency' => strtoupper($validated['currency']),
            'category' => $validated['category'] ?? null,
            'availability_status' => $status,
            'stock_quantity' => $stock,
            'track_inventory' => $validated['track_inventory'] ?? $item->track_inventory,
            'attributes' => ! empty($attributes) ? $attributes : null,
            'images' => ! empty($images) ? $images : null,
            'is_active' => $validated['is_active'] ?? $item->is_active,
        ]);

        return redirect()->back()->with('success', 'Catalog item updated.');
    }

    public function destroy(BusinessCatalogItem $item): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $item->business_id === $business->id, 403);

        $item->delete();

        return redirect()->back()->with('success', 'Catalog item deleted.');
    }

    public function refreshAiInsights(Request $request): JsonResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $insights = $this->aiAdvisorService->getInsights($business, forceRefresh: true);

        return response()->json([
            'status' => 'ok',
            'insights' => $insights,
        ]);
    }
}
