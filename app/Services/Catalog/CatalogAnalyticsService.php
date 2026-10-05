<?php

namespace App\Services\Catalog;

use App\Models\Business;
use App\Models\BusinessCatalogItem;
use App\Models\Order;
use App\Models\OrderItem;

class CatalogAnalyticsService
{
    /**
     * Compute concise top-level inventory and sales KPIs for a business.
     */
    public function getSummaryStats(Business $business): array
    {
        $items = BusinessCatalogItem::where('business_id', $business->id)->get();
        $totalItems = $items->count();
        $activeItems = $items->where('is_active', true)->count();

        $lowStockCount = $items->filter(function ($item) {
            return $item->stock_quantity !== null && $item->stock_quantity > 0 && $item->stock_quantity <= 5;
        })->count();

        $outOfStockCount = $items->filter(function ($item) {
            return ($item->stock_quantity !== null && $item->stock_quantity <= 0)
                || $item->availability_status === 'unavailable';
        })->count();

        // Calculate sales totals from order_items connected to this business's valid orders
        $salesAggregates = OrderItem::whereHas('order', function ($query) use ($business) {
            $query->where('business_id', $business->id)
                ->where('status', '!=', 'cancelled');
        })->selectRaw('COALESCE(SUM(quantity), 0) as total_units, COALESCE(SUM(total_price), 0) as total_revenue')
            ->first();

        $totalUnitsSold = (int) ($salesAggregates->total_units ?? 0);
        $totalRevenue = (float) ($salesAggregates->total_revenue ?? 0.0);

        // Determine dominant currency
        $firstItem = $items->first();
        $currency = $firstItem->currency ?? 'NGN';

        return [
            'total_items' => $totalItems,
            'active_items' => $activeItems,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'in_stock_count' => max(0, $totalItems - $outOfStockCount),
            'total_units_sold' => $totalUnitsSold,
            'total_revenue' => $totalRevenue,
            'currency' => $currency,
        ];
    }

    /**
     * Compute sales velocity and predictive stockout timeline per catalog item.
     */
    public function getItemMetricsMap(Business $business): array
    {
        $orderItems = OrderItem::whereHas('order', function ($query) use ($business) {
            $query->where('business_id', $business->id)
                ->where('status', '!=', 'cancelled');
        })->with('order')->get();

        $metricsByItemId = [];
        $metricsByName = [];

        // Group order items by catalog_item_id or normalized item_name
        foreach ($orderItems as $oi) {
            $qty = (int) $oi->quantity;
            $revenue = (float) $oi->total_price;
            $soldAt = $oi->order?->created_at ?? $oi->created_at;

            $updateStats = function (&$entry) use ($qty, $revenue, $soldAt) {
                $entry['units_sold'] += $qty;
                $entry['total_revenue'] += $revenue;
                $entry['order_count'] += 1;
                if ($soldAt && (! $entry['last_sold_at'] || $soldAt->gt($entry['last_sold_at']))) {
                    $entry['last_sold_at'] = $soldAt;
                }
                if ($soldAt && (! $entry['first_sold_at'] || $soldAt->lt($entry['first_sold_at']))) {
                    $entry['first_sold_at'] = $soldAt;
                }
            };

            if ($oi->catalog_item_id) {
                if (! isset($metricsByItemId[$oi->catalog_item_id])) {
                    $metricsByItemId[$oi->catalog_item_id] = [
                        'units_sold' => 0,
                        'total_revenue' => 0.0,
                        'order_count' => 0,
                        'last_sold_at' => null,
                        'first_sold_at' => null,
                    ];
                }
                $updateStats($metricsByItemId[$oi->catalog_item_id]);
            }

            if ($oi->item_name) {
                $normalized = strtolower(trim($oi->item_name));
                if (! isset($metricsByName[$normalized])) {
                    $metricsByName[$normalized] = [
                        'units_sold' => 0,
                        'total_revenue' => 0.0,
                        'order_count' => 0,
                        'last_sold_at' => null,
                        'first_sold_at' => null,
                    ];
                }
                $updateStats($metricsByName[$normalized]);
            }
        }

        $catalogItems = BusinessCatalogItem::where('business_id', $business->id)->get();
        $result = [];

        foreach ($catalogItems as $item) {
            $stats = $metricsByItemId[$item->id]
                ?? $metricsByName[strtolower(trim($item->name))]
                ?? [
                    'units_sold' => 0,
                    'total_revenue' => 0.0,
                    'order_count' => 0,
                    'last_sold_at' => null,
                    'first_sold_at' => null,
                ];

            $unitsSold = $stats['units_sold'];
            $stockQty = $item->stock_quantity;

            // Velocity calculation (units per day)
            // Use time delta between first sale and now, or minimum 7 days window
            $firstSale = $stats['first_sold_at'];
            $daysActive = $firstSale ? max(1, $firstSale->diffInDays(now())) : max(1, $item->created_at->diffInDays(now()));
            $dailyVelocity = $unitsSold > 0 ? round($unitsSold / max(1, min(30, $daysActive)), 2) : 0.0;

            // Stockout prediction
            $predictedDays = null;
            $risk = 'untracked';

            if ($stockQty !== null) {
                if ($stockQty <= 0 || $item->availability_status === 'unavailable') {
                    $predictedDays = 0;
                    $risk = 'out_of_stock';
                } elseif ($dailyVelocity > 0) {
                    $predictedDays = (int) ceil($stockQty / $dailyVelocity);
                    if ($predictedDays <= 3) {
                        $risk = 'critical';
                    } elseif ($predictedDays <= 7) {
                        $risk = 'warning';
                    } else {
                        $risk = 'healthy';
                    }
                } else {
                    $predictedDays = null; // No sales velocity to predict depletion
                    $risk = $stockQty <= 5 ? 'warning' : 'healthy';
                }
            }

            // Recommended restock batch (aiming for 30-day coverage, minimum 10)
            $recommendedRestock = $dailyVelocity > 0 ? (int) max(10, ceil($dailyVelocity * 30)) : ($stockQty !== null && $stockQty <= 5 ? 15 : null);

            $result[$item->id] = [
                'units_sold' => $unitsSold,
                'total_revenue' => $stats['total_revenue'],
                'order_count' => $stats['order_count'],
                'last_sold_at' => $stats['last_sold_at']?->toIso8601String(),
                'daily_velocity' => $dailyVelocity,
                'predicted_stockout_days' => $predictedDays,
                'stockout_risk' => $risk,
                'recommended_restock' => $recommendedRestock,
            ];
        }

        return $result;
    }

    /**
     * Compute day-of-week order distribution (Mon - Sun).
     */
    public function getDayOfWeekTrends(Business $business): array
    {
        $days = [
            'Monday' => 0,
            'Tuesday' => 0,
            'Wednesday' => 0,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0,
            'Sunday' => 0,
        ];

        $orders = Order::where('business_id', $business->id)
            ->where('status', '!=', 'cancelled')
            ->get(['created_at']);

        $totalOrders = $orders->count();

        foreach ($orders as $order) {
            $dayName = $order->created_at->format('l');
            if (isset($days[$dayName])) {
                $days[$dayName]++;
            }
        }

        $formatted = [];
        $maxCount = 0;
        $peakDay = 'Thursday';

        foreach ($days as $day => $count) {
            $pct = $totalOrders > 0 ? round(($count / $totalOrders) * 100) : 0;
            if ($count > $maxCount) {
                $maxCount = $count;
                $peakDay = $day;
            }
            $formatted[] = [
                'day' => $day,
                'short' => substr($day, 0, 3),
                'count' => $count,
                'percentage' => $pct,
            ];
        }

        return [
            'total_orders' => $totalOrders,
            'peak_day' => $peakDay,
            'peak_percentage' => $totalOrders > 0 ? round(($maxCount / $totalOrders) * 100) : 0,
            'breakdown' => $formatted,
        ];
    }

    /**
     * Compile rich, condensed RAG context payload for AI insights.
     */
    public function buildRagContext(Business $business): array
    {
        $summary = $this->getSummaryStats($business);
        $itemMetrics = $this->getItemMetricsMap($business);
        $dayTrends = $this->getDayOfWeekTrends($business);

        $items = BusinessCatalogItem::where('business_id', $business->id)->get();

        $topSelling = [];
        $lowStockItems = [];
        $slowMoving = [];

        foreach ($items as $item) {
            $m = $itemMetrics[$item->id] ?? null;
            $unitsSold = $m['units_sold'] ?? 0;
            $stock = $item->stock_quantity;
            $predictedDays = $m['predicted_stockout_days'] ?? null;

            if ($unitsSold > 0) {
                $topSelling[] = [
                    'name' => $item->name,
                    'price' => $item->price,
                    'units_sold' => $unitsSold,
                    'revenue' => $m['total_revenue'] ?? 0,
                    'stock' => $stock,
                    'predicted_stockout_days' => $predictedDays,
                ];
            }

            if ($m && in_array($m['stockout_risk'], ['critical', 'warning', 'out_of_stock'])) {
                $lowStockItems[] = [
                    'name' => $item->name,
                    'stock' => $stock,
                    'risk' => $m['stockout_risk'],
                    'predicted_stockout_days' => $predictedDays,
                    'recommended_restock' => $m['recommended_restock'] ?? 15,
                ];
            }

            if ($unitsSold === 0 && $item->is_active) {
                $slowMoving[] = [
                    'name' => $item->name,
                    'price' => $item->price,
                    'stock' => $stock,
                ];
            }
        }

        // Sort top sellers descending by units sold
        usort($topSelling, fn ($a, $b) => $b['units_sold'] <=> $a['units_sold']);

        return [
            'business_name' => $business->name,
            'industry' => $business->industry,
            'summary' => $summary,
            'day_trends' => $dayTrends,
            'top_selling' => array_slice($topSelling, 0, 4),
            'low_stock_alerts' => array_slice($lowStockItems, 0, 5),
            'slow_moving' => array_slice($slowMoving, 0, 4),
        ];
    }
}
