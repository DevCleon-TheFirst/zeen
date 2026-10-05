<?php

namespace App\Services\Automation;

use App\Models\AutomationWorkflow;
use App\Models\Business;

class AutomationPlanGuard
{
    public const PLAN_FREE = 'free';

    public const PLAN_PRO = 'pro';

    public const PLAN_ENTERPRISE = 'enterprise';

    /**
     * Check whether an active workflow execution is permitted under the business's current plan.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public static function canExecute(AutomationWorkflow $workflow, Business $business): array
    {
        $plan = strtolower($business->plan ?? self::PLAN_FREE);

        if (in_array($plan, [self::PLAN_PRO, self::PLAN_ENTERPRISE], true)) {
            return ['allowed' => true, 'reason' => null];
        }

        // On Free Plan: disallow external HTTP request node execution
        $hasRestrictedNode = $workflow->nodes()
            ->where('type', 'action_http_request')
            ->exists();

        if ($hasRestrictedNode) {
            return [
                'allowed' => false,
                'reason' => 'External HTTP Request node requires a Pro or Enterprise plan.',
            ];
        }

        // On Free Plan: only the first 2 active workflows are permitted to execute
        $activeWorkflows = AutomationWorkflow::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->take(2)
            ->toArray();

        if (! in_array($workflow->id, $activeWorkflows, true)) {
            return [
                'allowed' => false,
                'reason' => 'Free plan allows a maximum of 2 simultaneously active workflows. Please upgrade to Pro for unlimited workflows.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Check whether a business can activate a workflow.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public static function canActivate(AutomationWorkflow $workflow, Business $business): array
    {
        $plan = strtolower($business->plan ?? self::PLAN_FREE);

        if (in_array($plan, [self::PLAN_PRO, self::PLAN_ENTERPRISE], true)) {
            return ['allowed' => true, 'reason' => null];
        }

        $hasRestrictedNode = $workflow->nodes()
            ->where('type', 'action_http_request')
            ->exists();

        if ($hasRestrictedNode) {
            return [
                'allowed' => false,
                'reason' => 'Workflows with HTTP Webhook Request nodes require upgrading to Pro.',
            ];
        }

        $currentActiveCount = AutomationWorkflow::where('business_id', $business->id)
            ->where('is_active', true)
            ->where('id', '!=', $workflow->id)
            ->count();

        if ($currentActiveCount >= 2) {
            return [
                'allowed' => false,
                'reason' => 'Free plan is limited to 2 active workflows. Upgrade to Pro or deactivate an existing workflow.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Check if a specific node type is permitted for the business.
     */
    public static function isNodeTypeAllowed(string $nodeType, Business $business): bool
    {
        $plan = strtolower($business->plan ?? self::PLAN_FREE);

        if (in_array($plan, [self::PLAN_PRO, self::PLAN_ENTERPRISE], true)) {
            return true;
        }

        // Restricted nodes on Free tier
        return $nodeType !== 'action_http_request';
    }

    /**
     * Get maximum permitted active workflows for a business.
     */
    public static function maxActiveWorkflows(Business $business): ?int
    {
        $plan = strtolower($business->plan ?? self::PLAN_FREE);

        return in_array($plan, [self::PLAN_PRO, self::PLAN_ENTERPRISE], true) ? null : 2;
    }
}
