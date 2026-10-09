<?php

namespace App\Services\Automation;

use App\Models\AutomationWorkflow;
use App\Models\Business;

class AutomationPlanGuard
{
    public const PLAN_STARTER = 'starter';

    public const PLAN_PRO = 'pro';

    public const PLAN_ENTERPRISE = 'enterprise';

    /**
     * Check whether an active workflow execution is permitted under the business's current plan.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public static function canExecute(AutomationWorkflow $workflow, Business $business): array
    {
        if (auth()->check() && auth()->user()->is_super_admin) {
            return ['allowed' => true, 'reason' => null];
        }

        $plan = strtolower($business->plan ?? self::PLAN_STARTER);

        if (in_array($plan, [self::PLAN_PRO, self::PLAN_ENTERPRISE], true)) {
            return ['allowed' => true, 'reason' => null];
        }

        // On Starter Plan: disallow external HTTP request node execution
        $hasRestrictedNode = $workflow->nodes()
            ->where('type', 'action_http_request')
            ->exists();

        if ($hasRestrictedNode) {
            return [
                'allowed' => false,
                'reason' => 'External HTTP Request node requires a Pro or Enterprise plan.',
            ];
        }

        // On Starter Plan: first 3 active workflows are permitted to execute
        $activeWorkflows = AutomationWorkflow::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->take(3)
            ->toArray();

        if (! in_array($workflow->id, $activeWorkflows, true)) {
            return [
                'allowed' => false,
                'reason' => 'Starter plan allows a maximum of 3 simultaneously active workflows. Please upgrade to Pro for unlimited workflows.',
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
        if (auth()->check() && auth()->user()->is_super_admin) {
            return ['allowed' => true, 'reason' => null];
        }

        $plan = strtolower($business->plan ?? self::PLAN_STARTER);

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

        if ($currentActiveCount >= 3) {
            return [
                'allowed' => false,
                'reason' => 'Starter plan is limited to 3 active workflows. Upgrade to Pro or deactivate an existing workflow.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Check if a specific node type is permitted for the business.
     */
    public static function isNodeTypeAllowed(string $nodeType, Business $business): bool
    {
        if (auth()->check() && auth()->user()->is_super_admin) {
            return true;
        }

        $plan = strtolower($business->plan ?? self::PLAN_STARTER);

        if (in_array($plan, [self::PLAN_PRO, self::PLAN_ENTERPRISE], true)) {
            return true;
        }

        // Restricted nodes on Starter tier
        return $nodeType !== 'action_http_request';
    }

    /**
     * Get maximum permitted active workflows for a business.
     */
    public static function maxActiveWorkflows(?Business $business = null): ?int
    {
        if (auth()->check() && auth()->user()->is_super_admin) {
            return null; // Unlimited for super admin
        }

        $plan = strtolower($business?->plan ?? self::PLAN_STARTER);

        return in_array($plan, [self::PLAN_PRO, self::PLAN_ENTERPRISE], true) ? null : 3;
    }
}
