<?php

namespace App\Services\Automation\Nodes;

use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use App\Models\BusinessCatalogItem;

class ActionSearchCatalogHandler implements NodeHandlerInterface
{
    public function handle(AutomationNode $node, AutomationExecution $execution): ?array
    {
        $context = $execution->context ?? [];
        $businessId = $execution->workflow?->business_id;

        $query = $node->config['query'] ?? $context['search_query'] ?? $context['message_text'] ?? '';
        $category = $node->config['category'] ?? null;
        $limit = (int) ($node->config['limit'] ?? 5);

        $queryBuilder = BusinessCatalogItem::query();
        if ($businessId) {
            $queryBuilder->where('business_id', $businessId);
        }
        $queryBuilder->where('is_active', true);

        if (! empty($category)) {
            $queryBuilder->where('category', $category);
        }

        if (! empty($query)) {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            });
        }

        $items = $queryBuilder->take($limit)->get();

        $catalogResults = $items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'price' => (float) $item->price,
            'currency' => $item->currency,
            'stock_quantity' => $item->stock_quantity,
            'in_stock' => ! $item->track_inventory || $item->stock_quantity > 0,
        ])->toArray();

        $summaryLines = [];
        foreach ($items as $item) {
            $priceStr = ($item->currency ?: 'NGN').' '.number_format($item->price, 2);
            $stockStr = ($item->track_inventory && $item->stock_quantity <= 0) ? ' [Out of Stock]' : '';
            $summaryLines[] = "• {$item->name} - {$priceStr}{$stockStr}";
        }

        $catalogSummary = count($summaryLines) > 0 ? implode("\n", $summaryLines) : 'No matching products found.';

        // Persist catalog results into execution context for downstream nodes
        $context['catalog_results'] = $catalogResults;
        $context['catalog_summary'] = $catalogSummary;
        $execution->update(['context' => $context]);

        return [
            'action' => 'search_catalog',
            'query' => $query,
            'results_count' => count($catalogResults),
            'catalog_summary' => $catalogSummary,
        ];
    }
}
