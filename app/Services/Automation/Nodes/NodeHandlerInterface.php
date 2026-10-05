<?php

namespace App\Services\Automation\Nodes;

use App\Models\AutomationExecution;
use App\Models\AutomationNode;

interface NodeHandlerInterface
{
    /**
     * Execute the node logic.
     *
     * @param  AutomationNode  $node  The node configuration.
     * @param  AutomationExecution  $execution  The current execution state.
     * @return mixed The result of the execution (e.g. array of data or string condition label).
     */
    public function handle(AutomationNode $node, AutomationExecution $execution): mixed;
}
