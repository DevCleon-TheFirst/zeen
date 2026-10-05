<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    workflow: Object,
    executions: Object,
    stats: Object,
    currentFilter: String,
    nodeMap: Object,   // node_id → { label, type }
});

const selectedExecution = ref(null);
const expandedSteps = ref(new Set()); // indices of expanded JSON panels

const openDetail = (execution) => {
    selectedExecution.value = execution;
    expandedSteps.value = new Set();
};

const closeDetail = () => {
    selectedExecution.value = null;
};

const toggleStepExpand = (idx) => {
    if (expandedSteps.value.has(idx)) {
        expandedSteps.value.delete(idx);
    } else {
        expandedSteps.value.add(idx);
    }
    // trigger reactivity on Set
    expandedSteps.value = new Set(expandedSteps.value);
};

const filterByStatus = (status) => {
    router.get(route('automations.executions', props.workflow.id), status ? { status } : {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const retryExecution = (executionId) => {
    if (confirm('Re-run this workflow execution from the beginning?')) {
        router.post(route('automations.executions.retry', [props.workflow.id, executionId]), {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedExecution.value?.id === executionId) {
                    selectedExecution.value = null;
                }
            },
        });
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const calculateDuration = (exec) => {
    if (!exec.started_at) return '—';
    if (exec.status === 'waiting') return 'Waiting';
    if (!exec.completed_at) return 'In progress';
    const start = new Date(exec.started_at).getTime();
    const end = new Date(exec.completed_at).getTime();
    const diffMs = Math.max(0, end - start);
    if (diffMs < 1000) return `${diffMs}ms`;
    if (diffMs < 60000) return `${(diffMs / 1000).toFixed(1)}s`;
    return `${Math.round(diffMs / 60000)}m`;
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'completed': return { label: 'Completed', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
        case 'failed':    return { label: 'Failed',    class: 'bg-red-50 text-red-700 border-red-200' };
        case 'waiting':   return { label: 'Waiting',   class: 'bg-amber-50 text-amber-700 border-amber-200' };
        case 'running':
        case 'pending':   return { label: 'Running',   class: 'bg-blue-50 text-blue-700 border-blue-200' };
        default:          return { label: status,      class: 'bg-stone-50 text-stone-700 border-stone-200' };
    }
};

// ─── Node type helpers ────────────────────────────────────────────────────────
const nodeLabel = (nodeId) => props.nodeMap?.[nodeId]?.label ?? nodeId;
const nodeType  = (nodeId) => props.nodeMap?.[nodeId]?.type  ?? 'unknown';

const stepIcon = (nodeId) => {
    const t = nodeType(nodeId);
    if (t.startsWith('trigger')) return '⚡';
    if (t.startsWith('ai'))      return '🤖';
    if (t === 'condition_check_field' || t === 'condition_time_window') return '🔀';
    if (t === 'delay')           return '⏱';
    if (t === 'action_send_message' || t === 'action_send_email') return '💬';
    if (t === 'action_http_request') return '🌐';
    if (t === 'action_search_catalog') return '🔍';
    if (t === 'action_add_tag')  return '🏷';
    if (t === 'action_assign_staff') return '👤';
    return '▶';
};

/** Returns Tailwind classes for the left-side accent line segment of each step */
const stepAccent = (step, idx, log) => {
    const isLast = idx === log.length - 1;
    const hasFailed = step.error ?? false;
    if (hasFailed) return { dot: 'bg-red-500', line: 'bg-red-200' };
    const t = nodeType(step.node_id);
    if (t.startsWith('ai'))        return { dot: 'bg-[#4a3324]', line: 'bg-[#d4b99a]' };
    if (t.startsWith('condition')) return { dot: 'bg-amber-500', line: 'bg-amber-200' };
    if (t === 'delay')             return { dot: 'bg-purple-500', line: 'bg-purple-200' };
    if (isLast)                    return { dot: 'bg-emerald-500', line: 'bg-emerald-200' };
    return { dot: 'bg-[#7b5537]', line: 'bg-[#e8d5c4]' };
};

// ─── Stats progress bar ────────────────────────────────────────────────────────
const successRate = computed(() => {
    if (!props.stats.total) return 0;
    return Math.round((props.stats.completed / props.stats.total) * 100);
});
</script>


<template>
    <AuthenticatedLayout>
        <Head :title="`Execution History — ${workflow.name}`" />

        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2 text-xs">
                    <Link :href="route('automations.index')" class="text-stone-500 hover:text-stone-900 transition-colors">
                        Automations
                    </Link>
                    <span class="text-stone-300">/</span>
                    <span class="font-semibold text-[#241e19]">{{ workflow.name }}</span>
                    <span class="text-stone-300">/</span>
                    <span class="text-stone-500">Executions</span>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('automations.edit', workflow.id)"
                        class="btn-secondary text-xs py-1 px-3"
                    >
                        Open in Canvas
                    </Link>
                </div>
            </div>
        </template>

        <div class="p-6 md:p-8 space-y-6 max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-[#e8e2d9]">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-xl font-semibold text-[#241e19] tracking-tight">
                            Execution History
                        </h1>
                        <span
                            class="text-[10px] px-2 py-0.5 rounded-full font-medium border"
                            :class="workflow.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-stone-100 text-stone-600 border-stone-200'"
                        >
                            {{ workflow.is_active ? 'Active' : 'Draft' }}
                        </span>
                    </div>
                    <p class="text-xs text-stone-500 mt-1">
                        Trigger: <code class="font-mono bg-stone-100 px-1 py-0.5 rounded text-stone-700">{{ workflow.trigger_type }}</code>
                        <span v-if="workflow.description" class="ml-2">— {{ workflow.description }}</span>
                    </p>
                </div>

                <!-- Stats summary badges -->
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2">
                        <div class="px-3 py-2 rounded-lg bg-[#faf8f5] border border-[#e8e2d9] text-center min-w-[70px]">
                            <div class="text-[10px] uppercase font-semibold text-stone-500">Total</div>
                            <div class="text-sm font-semibold text-[#241e19]">{{ stats.total }}</div>
                        </div>
                        <div class="px-3 py-2 rounded-lg bg-[#faf8f5] border border-[#e8e2d9] text-center min-w-[70px]">
                            <div class="text-[10px] uppercase font-semibold text-emerald-600">Success</div>
                            <div class="text-sm font-semibold text-emerald-700">{{ stats.completed }}</div>
                        </div>
                        <div class="px-3 py-2 rounded-lg bg-[#faf8f5] border border-[#e8e2d9] text-center min-w-[70px]">
                            <div class="text-[10px] uppercase font-semibold text-red-600">Failed</div>
                            <div class="text-sm font-semibold text-red-700">{{ stats.failed }}</div>
                        </div>
                        <div class="px-3 py-2 rounded-lg bg-[#faf8f5] border border-[#e8e2d9] text-center min-w-[70px]">
                            <div class="text-[10px] uppercase font-semibold text-amber-600">Waiting</div>
                            <div class="text-sm font-semibold text-amber-700">{{ stats.waiting }}</div>
                        </div>
                    </div>
                    <!-- Success rate bar -->
                    <div v-if="stats.total > 0" class="w-full">
                        <div class="flex items-center justify-between text-[10px] text-stone-500 mb-1">
                            <span>Success rate</span>
                            <span class="font-semibold text-emerald-700">{{ successRate }}%</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
                            <div
                                class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                :style="`width:${successRate}%`"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter tabs -->
            <div class="flex items-center gap-1.5 border-b border-[#e8e2d9] pb-3 text-xs">
                <button
                    @click="filterByStatus('')"
                    class="px-3 py-1.5 rounded-md font-medium transition-colors"
                    :class="!currentFilter ? 'bg-[#7b5537] text-white shadow-xs' : 'text-stone-600 hover:bg-[#faf8f5]'"
                >
                    All ({{ stats.total }})
                </button>
                <button
                    @click="filterByStatus('completed')"
                    class="px-3 py-1.5 rounded-md font-medium transition-colors"
                    :class="currentFilter === 'completed' ? 'bg-[#7b5537] text-white shadow-xs' : 'text-stone-600 hover:bg-[#faf8f5]'"
                >
                    Completed ({{ stats.completed }})
                </button>
                <button
                    @click="filterByStatus('failed')"
                    class="px-3 py-1.5 rounded-md font-medium transition-colors"
                    :class="currentFilter === 'failed' ? 'bg-[#7b5537] text-white shadow-xs' : 'text-stone-600 hover:bg-[#faf8f5]'"
                >
                    Failed ({{ stats.failed }})
                </button>
                <button
                    @click="filterByStatus('waiting')"
                    class="px-3 py-1.5 rounded-md font-medium transition-colors"
                    :class="currentFilter === 'waiting' ? 'bg-[#7b5537] text-white shadow-xs' : 'text-stone-600 hover:bg-[#faf8f5]'"
                >
                    Waiting / Drip ({{ stats.waiting }})
                </button>
            </div>

            <!-- Table of executions -->
            <div class="card overflow-hidden">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-[#e8e2d9] bg-[#faf8f5] text-[11px] font-semibold text-stone-500 uppercase tracking-wider">
                            <th class="py-3 px-4">Execution ID</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Contact / Context</th>
                            <th class="py-3 px-4">Steps Executed</th>
                            <th class="py-3 px-4">Triggered At</th>
                            <th class="py-3 px-4">Duration</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e8e2d9]">
                        <tr
                            v-for="exec in executions.data"
                            :key="exec.id"
                            class="hover:bg-[#faf8f5]/60 transition-colors"
                        >
                            <td class="py-3 px-4 font-mono font-medium text-[#241e19]">
                                #{{ exec.id }}
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium border"
                                    :class="getStatusBadge(exec.status).class"
                                >
                                    {{ getStatusBadge(exec.status).label }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-stone-600">
                                <span v-if="exec.customer?.name" class="font-medium text-[#241e19]">
                                    {{ exec.customer.name }}
                                </span>
                                <span v-else-if="exec.conversation?.customer?.name" class="font-medium text-[#241e19]">
                                    {{ exec.conversation.customer.name }}
                                </span>
                                <span v-else-if="exec.context?.customer_email" class="text-stone-500 font-mono text-[11px]">
                                    {{ exec.context.customer_email }}
                                </span>
                                <span v-else class="text-stone-400">System Trigger</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded bg-stone-100 text-stone-700 font-mono text-[11px]">
                                    {{ (exec.execution_log || []).length }} steps
                                </span>
                            </td>
                            <td class="py-3 px-4 text-stone-600 font-mono text-[11px]">
                                {{ formatDate(exec.started_at) }}
                            </td>
                            <td class="py-3 px-4 text-stone-600 font-mono text-[11px]">
                                {{ calculateDuration(exec) }}
                            </td>
                            <td class="py-3 px-4 text-right space-x-2">
                                <button
                                    @click="openDetail(exec)"
                                    type="button"
                                    class="text-xs font-medium text-[#7b5537] hover:underline"
                                >
                                    View Log
                                </button>
                                <button
                                    v-if="exec.status === 'failed'"
                                    @click="retryExecution(exec.id)"
                                    type="button"
                                    class="text-xs font-medium text-stone-600 hover:text-stone-900"
                                >
                                    Retry
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!executions.data || executions.data.length === 0">
                            <td colspan="7" class="py-12 text-center text-stone-400 text-xs">
                                No execution runs recorded for this workflow yet.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="executions.links && executions.links.length > 3" class="px-4 py-3 border-t border-[#e8e2d9] flex items-center justify-between bg-[#faf8f5]">
                    <div class="text-[11px] text-stone-500">
                        Showing {{ executions.from || 0 }} to {{ executions.to || 0 }} of {{ executions.total }} executions
                    </div>
                    <div class="flex items-center gap-1">
                        <Link
                            v-for="(link, i) in executions.links"
                            :key="i"
                            :href="link.url || '#'"
                            class="px-2.5 py-1 text-xs rounded border transition-colors"
                            :class="link.active
                                ? 'bg-[#7b5537] text-white border-[#7b5537]'
                                : link.url ? 'bg-white border-[#e8e2d9] text-stone-700 hover:bg-[#faf8f5]' : 'bg-transparent border-transparent text-stone-300 pointer-events-none'"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>

            <!-- Execution Detail Modal / Drawer -->
            <div v-if="selectedExecution" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/40">
                <div class="card max-w-2xl w-full p-6 shadow-xl bg-white max-h-[90vh] flex flex-col">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-[#e8e2d9] flex-shrink-0">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-sm text-[#241e19]">
                                Execution Run #{{ selectedExecution.id }}
                            </h3>
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium border"
                                :class="getStatusBadge(selectedExecution.status).class"
                            >
                                {{ getStatusBadge(selectedExecution.status).label }}
                            </span>
                        </div>
                        <button @click="closeDetail" class="text-stone-400 hover:text-stone-700 text-lg leading-none">✕</button>
                    </div>

                    <!-- Modal Body (Scrollable) -->
                    <div class="overflow-y-auto py-4 space-y-4 flex-1">
                        <!-- Error Alert if failed -->
                        <div v-if="selectedExecution.error_message" class="p-3 rounded-md bg-red-50 border border-red-200 text-red-800 text-xs font-mono">
                            <div class="font-semibold text-red-900 mb-0.5">Execution Error:</div>
                            {{ selectedExecution.error_message }}
                        </div>

                        <!-- Metadata -->
                        <div class="grid grid-cols-2 gap-3 p-3 rounded-lg bg-[#faf8f5] border border-[#e8e2d9] text-xs">
                            <div>
                                <span class="text-stone-500 block text-[10px] uppercase font-semibold">Started</span>
                                <span class="font-mono text-stone-800">{{ formatDate(selectedExecution.started_at) }}</span>
                            </div>
                            <div>
                                <span class="text-stone-500 block text-[10px] uppercase font-semibold">Completed</span>
                                <span class="font-mono text-stone-800">{{ formatDate(selectedExecution.completed_at) }}</span>
                            </div>
                            <div>
                                <span class="text-stone-500 block text-[10px] uppercase font-semibold">Duration</span>
                                <span class="font-mono text-stone-800">{{ calculateDuration(selectedExecution) }}</span>
                            </div>
                            <div>
                                <span class="text-stone-500 block text-[10px] uppercase font-semibold">Current / Last Node</span>
                                <span class="font-mono text-stone-800">{{ selectedExecution.current_node_id || 'Finished' }}</span>
                            </div>
                        </div>

                        <!-- Step Timeline -->
                        <div>
                            <h4 class="text-xs font-semibold text-[#241e19] mb-3">Node Execution Path</h4>

                            <div v-if="selectedExecution.execution_log && selectedExecution.execution_log.length > 0" class="space-y-0">
                                <div
                                    v-for="(step, idx) in selectedExecution.execution_log"
                                    :key="idx"
                                    class="relative flex gap-3"
                                >
                                    <!-- Left timeline track -->
                                    <div class="flex flex-col items-center flex-shrink-0 w-6">
                                        <!-- Dot -->
                                        <div
                                            class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[11px] z-10 mt-0.5 flex-shrink-0"
                                            :class="stepAccent(step, idx, selectedExecution.execution_log).dot"
                                        >
                                            {{ stepIcon(step.node_id) }}
                                        </div>
                                        <!-- Connector line (hidden for last step) -->
                                        <div
                                            v-if="idx < selectedExecution.execution_log.length - 1"
                                            class="w-0.5 flex-1 my-1 min-h-[16px] rounded-full"
                                            :class="stepAccent(step, idx, selectedExecution.execution_log).line"
                                        ></div>
                                    </div>

                                    <!-- Step card -->
                                    <div class="flex-1 pb-4">
                                        <div
                                            class="border rounded-lg overflow-hidden"
                                            :class="step.error ? 'border-red-200 bg-red-50/40' : 'border-[#e8e2d9] bg-white'"
                                        >
                                            <!-- Step header -->
                                            <div class="flex items-center justify-between px-3 py-2">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <span class="font-semibold text-xs text-[#241e19] truncate">
                                                        {{ nodeLabel(step.node_id) }}
                                                    </span>
                                                    <span class="text-[9px] px-1.5 py-0.5 rounded font-mono bg-stone-100 text-stone-600 flex-shrink-0">
                                                        {{ nodeType(step.node_id) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2 flex-shrink-0">
                                                    <span class="text-[10px] text-stone-400 font-mono">
                                                        {{ formatDate(step.executed_at) }}
                                                    </span>
                                                    <!-- Expand result toggle -->
                                                    <button
                                                        v-if="step.result"
                                                        type="button"
                                                        @click="toggleStepExpand(idx)"
                                                        class="text-[10px] px-1.5 py-0.5 rounded bg-stone-100 hover:bg-stone-200 text-stone-600 transition-colors"
                                                    >
                                                        {{ expandedSteps.has(idx) ? 'Hide' : 'Result' }}
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Error message -->
                                            <div v-if="step.error" class="px-3 pb-2 text-[11px] text-red-700 font-mono">
                                                ⚠ {{ step.error }}
                                            </div>

                                            <!-- Collapsible result JSON -->
                                            <div v-if="step.result && expandedSteps.has(idx)" class="border-t border-[#e8e2d9]">
                                                <pre class="p-3 font-mono text-[10px] text-stone-700 bg-[#faf8f5] overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(step.result, null, 2) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-stone-400 text-xs italic py-2">
                                No step details logged yet.
                            </div>
                        </div>

                        <!-- Trigger Context Payload -->
                        <div>
                            <h4 class="text-xs font-semibold text-[#241e19] mb-1">Trigger Context Payload</h4>
                            <pre class="bg-[#faf8f5] p-3 rounded-md border border-[#e8e2d9] font-mono text-[11px] text-stone-700 overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(selectedExecution.context || {}, null, 2) }}</pre>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="pt-3 border-t border-[#e8e2d9] flex items-center justify-between flex-shrink-0">
                        <button
                            v-if="selectedExecution.status === 'failed'"
                            @click="retryExecution(selectedExecution.id)"
                            type="button"
                            class="btn-primary text-xs py-1.5 px-3"
                        >
                            Retry This Execution
                        </button>
                        <div v-else></div>

                        <button
                            @click="closeDetail"
                            type="button"
                            class="btn-secondary text-xs py-1.5 px-3"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
