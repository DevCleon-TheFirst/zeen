<?php

namespace App\Services\Automation\Nodes;

use App\Models\AutomationExecution;
use App\Models\AutomationNode;
use Carbon\Carbon;

class ConditionTimeWindowHandler implements NodeHandlerInterface
{
    /**
     * Returns "in_window" or "out_of_window".
     * Config: { "timezone": "Africa/Lagos", "days": ["Mon","Tue","Wed"], "from": "09:00", "to": "17:00" }
     */
    public function handle(AutomationNode $node, AutomationExecution $execution): mixed
    {
        $timezone = $node->config['timezone'] ?? 'UTC';
        $allowedDays = array_map('strtolower', $node->config['days'] ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']);
        $from = $node->config['from'] ?? '00:00';
        $to = $node->config['to'] ?? '23:59';

        try {
            $now = Carbon::now($timezone);
        } catch (\Throwable) {
            $now = Carbon::now('UTC');
        }

        $currentDay = strtolower($now->format('D')); // 'mon', 'tue', etc.
        $currentTime = $now->format('H:i');

        $dayOk = in_array($currentDay, $allowedDays);
        $timeOk = $currentTime >= $from && $currentTime <= $to;

        return ($dayOk && $timeOk) ? 'in_window' : 'out_of_window';
    }
}
