<?php

namespace App\Services\Catalog;

use App\Models\Business;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CatalogAiAdvisorService
{
    public function __construct(
        protected CatalogAnalyticsService $analyticsService,
    ) {}

    /**
     * Get or generate AI inventory & demand forecast insights.
     */
    public function getInsights(Business $business, bool $forceRefresh = false): array
    {
        $cacheKey = "catalog_ai_insights_biz_{$business->id}";

        if (! $forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $ragContext = $this->analyticsService->buildRagContext($business);

        $insights = $this->generateWithLlm($business, $ragContext);

        if (! $insights || empty($insights['signals'])) {
            $insights = $this->generateHeuristicFallback($ragContext);
        }

        // Cache for 15 minutes
        Cache::put($cacheKey, $insights, now()->addMinutes(15));

        return $insights;
    }

    /**
     * Call LLM (DeepSeek / configured provider) with RAG context.
     */
    protected function generateWithLlm(Business $business, array $context): ?array
    {
        $setting = $business->aiProviderSetting;
        if (! $setting || ! $setting->is_active || empty($setting->api_key)) {
            return null;
        }

        $baseUrl = rtrim($setting->base_url ?: 'https://api.deepseek.com', '/');
        $model = $setting->model ?: 'deepseek-chat';

        $prompt = $this->buildPrompt($context);

        try {
            $response = Http::withToken($setting->api_key)
                ->timeout(12)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an elite retail merchandising intelligence engine for high-growth merchants. Output ONLY valid JSON without markdown wrapping, backticks, or emoji. Format: {"headline": string, "urgency_level": "critical"|"warning"|"healthy", "peak_day_insight": string, "restock_advice": string, "merchandising_tip": string, "signals": [{"type": "demand"|"inventory"|"commercial", "badge": string, "title": string, "action": string, "metric": string}]}. Write terse, editorial, professional prose. No emoji, no bullet symbols, no fluff. Sound like a sharp analyst, not a chatbot.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.25,
                    'max_tokens' => 500,
                ]);

            if ($response->successful()) {
                $rawContent = trim($response->json('choices.0.message.content') ?? '');
                $rawContent = preg_replace('/^```(?:json)?\s*/i', '', $rawContent);
                $rawContent = preg_replace('/\s*```$/i', '', $rawContent);

                $decoded = json_decode($rawContent, true);
                if (is_array($decoded) && ! empty($decoded['headline'])) {
                    $decoded['source'] = 'ai';
                    $decoded['generated_at'] = now()->format('M j, g:i A');

                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[CatalogAiAdvisor] LLM generation failed for Business #{$business->id}: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Build dense RAG prompt for the LLM.
     */
    protected function buildPrompt(array $c): string
    {
        $summary = $c['summary'];
        $dayTrends = $c['day_trends'];
        $currency = $summary['currency'];

        $contextText = "STORE TELEMETRY:\n";
        $contextText .= "- Business: {$c['business_name']} ({$c['industry']})\n";
        $contextText .= "- Catalog: {$summary['total_items']} items ({$summary['active_items']} active). Low stock: {$summary['low_stock_count']}, Out of stock: {$summary['out_of_stock_count']}.\n";
        $contextText .= "- Volume: {$summary['total_units_sold']} units sold, Revenue: {$currency} ".number_format($summary['total_revenue'], 2).".\n";
        $contextText .= "- Day-of-week sales: Peak is {$dayTrends['peak_day']} with {$dayTrends['peak_percentage']}% of total orders.\n";

        if (! empty($c['top_selling'])) {
            $contextText .= "- Top products:\n";
            foreach ($c['top_selling'] as $item) {
                $stockStr = $item['stock'] !== null ? "{$item['stock']} left" : 'unlimited stock';
                $predStr = $item['predicted_stockout_days'] !== null ? "stockout in ~{$item['predicted_stockout_days']}d" : 'stable';
                $contextText .= "  • {$item['name']}: {$item['units_sold']} sold ({$currency} {$item['revenue']}), {$stockStr}, {$predStr}\n";
            }
        }

        if (! empty($c['low_stock_alerts'])) {
            $contextText .= "- Stock alerts:\n";
            foreach ($c['low_stock_alerts'] as $item) {
                $contextText .= "  • {$item['name']}: {$item['stock']} remaining, Reorder: {$item['recommended_restock']} units.\n";
            }
        }

        if (! empty($c['slow_moving'])) {
            $contextText .= '- Unsold items: '.implode(', ', array_column($c['slow_moving'], 'name'))."\n";
        }

        $contextText .= "\nGenerate 3 crisp executive signals: 1 for demand timing, 1 for inventory runway, 1 for commercial growth/bundling.";

        return $contextText;
    }

    /**
     * Intelligent heuristic fallback when LLM is unavailable or offline.
     */
    protected function generateHeuristicFallback(array $c): array
    {
        $summary = $c['summary'];
        $dayTrends = $c['day_trends'];
        $lowStock = $c['low_stock_alerts'];
        $topSelling = $c['top_selling'];
        $currency = $summary['currency'];

        $urgency = ! empty($lowStock) ? 'warning' : 'healthy';

        $headline = "Catalog velocity steady across {$summary['total_items']} items with {$summary['total_units_sold']} units sold.";
        if (! empty($topSelling)) {
            $top = $topSelling[0];
            $headline = "{$top['name']} leads sales velocity ({$top['units_sold']} sold, {$currency} ".number_format($top['revenue'], 2).').';
        }

        $peakDay = $dayTrends['peak_day'] ?? 'Tuesday';
        $peakPct = $dayTrends['peak_percentage'] > 0 ? "{$dayTrends['peak_percentage']}% of checkouts" : 'steady distribution';

        $signals = [];

        // 1. Demand Timing Signal
        $signals[] = [
            'type' => 'demand',
            'badge' => 'Timing',
            'title' => "{$peakDay} Peak Velocity",
            'action' => "Broadcast WhatsApp campaign on {$peakDay} morning",
            'metric' => "{$peakDay} drives {$peakPct}",
        ];

        // 2. Inventory Runway Signal
        if (! empty($lowStock)) {
            $crit = $lowStock[0];
            $daysLeft = $crit['predicted_stockout_days'] !== null ? "~{$crit['predicted_stockout_days']}d buffer" : 'low stock';
            $signals[] = [
                'type' => 'inventory',
                'badge' => 'Restock Priority',
                'title' => "Reorder {$crit['name']}",
                'action' => "Order +{$crit['recommended_restock']} units to avoid stockout",
                'metric' => "{$crit['stock']} remaining ({$daysLeft})",
            ];
            $urgency = 'critical';
            $restockAdvice = "Restock Alert: \"{$crit['name']}\" has {$crit['stock']} unit(s) remaining.";
        } else {
            $signals[] = [
                'type' => 'inventory',
                'badge' => 'Healthy Runway',
                'title' => 'Stock Levels Optimal',
                'action' => 'Maintain current lean inventory buffer',
                'metric' => "{$summary['active_items']} SKUs ready to fulfill",
            ];
            $restockAdvice = 'All active products have stable inventory coverage.';
        }

        // 3. Commercial Growth Signal
        if (! empty($c['slow_moving']) && ! empty($topSelling)) {
            $slow = $c['slow_moving'][0]['name'];
            $best = $topSelling[0]['name'];
            $signals[] = [
                'type' => 'commercial',
                'badge' => 'AOV Lift',
                'title' => 'Curate Product Bundle',
                'action' => "Pair \"{$best}\" with \"{$slow}\"",
                'metric' => 'Lift average basket size',
            ];
            $merchandisingTip = "Bundle \"{$best}\" and \"{$slow}\" at a slight promotional discount to lift order values.";
        } else {
            $signals[] = [
                'type' => 'commercial',
                'badge' => 'Growth',
                'title' => 'Showcase Bestsellers',
                'action' => 'Pin top sellers in WhatsApp greeting catalog',
                'metric' => 'Accelerate first-response conversions',
            ];
            $merchandisingTip = 'Pin top sellers in your WhatsApp welcome message to drive fast customer conversions.';
        }

        $peakInsight = "Orders concentrate on {$peakDay} ({$peakPct}). Schedule promotions 12–24h prior.";

        return [
            'headline' => $headline,
            'urgency_level' => $urgency,
            'peak_day_insight' => $peakInsight,
            'restock_advice' => $restockAdvice,
            'merchandising_tip' => $merchandisingTip,
            'signals' => $signals,
            'source' => 'heuristics',
            'generated_at' => now()->format('M j, g:i A'),
        ];
    }
}
