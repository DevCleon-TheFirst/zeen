<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    workflows: {
        type: Array,
        default: () => [],
    },
    templates: {
        type: Array,
        default: () => [],
    },
    currentBusiness: {
        type: Object,
        default: null,
    },
    allBusinesses: {
        type: Array,
        default: () => [],
    },
    isSuperAdmin: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({ business_id: '' }),
    },
});

const importTemplate = (templateId) => {
    const data = {};
    if (props.isSuperAdmin && props.filters?.business_id) {
        data.business_id = props.filters.business_id;
    }
    router.post(route('automations.fromTemplate', templateId), data);
};

const deleteWorkflow = (workflow) => {
    if (confirm(`Delete automation workflow "${workflow.name}"?`)) {
        router.delete(route('automations.destroy', workflow.id));
    }
};

const deleteTemplate = (template) => {
    if (confirm(`Remove global platform blueprint "${template.name}"?`)) {
        router.delete(route('automations.destroyTemplate', template.id));
    }
};

const changeBusinessFilter = (businessId) => {
    router.get(route('automations.index'), {
        business_id: businessId || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Automation Workflows" />

        <template #header>
            <div class="flex items-center gap-2 text-xs">
                <span class="font-bold text-[#211812] tracking-tight">Automation Engine</span>
                <span class="text-stone-300">/</span>
                <span class="text-stone-500 font-medium">Workflows &amp; Studio</span>
            </div>
        </template>

        <div class="p-6 md:p-8 space-y-8 max-w-7xl mx-auto">

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-[#e8e2d9]">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-xl font-bold text-[#211812] tracking-tight">
                            Automation Workflows
                        </h1>
                        <span
                            v-if="isSuperAdmin"
                            class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200"
                        >
                            Super Admin (Full Master Ability)
                        </span>
                    </div>
                    <p class="text-xs text-stone-500 mt-1">
                        Build and orchestrate multi-step conversational flows, trigger external webhooks, and deploy AI reasoning agents.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Super Admin Multi-Tenant Store Filter -->
                    <div v-if="isSuperAdmin && allBusinesses.length > 0" class="flex items-center gap-2">
                        <label class="text-xs text-stone-500 font-medium whitespace-nowrap">Filter Store:</label>
                        <select
                            :value="filters?.business_id || ''"
                            @change="changeBusinessFilter($event.target.value)"
                            class="text-xs rounded-lg border border-[#e8e2d9] bg-white py-1.5 px-2.5 text-[#211812] focus:ring-1 focus:ring-[#7b5537] cursor-pointer"
                        >
                            <option value="">All Stores &amp; Workspaces</option>
                            <option v-for="b in allBusinesses" :key="b.id" :value="b.id">
                                {{ b.name }}
                            </option>
                        </select>
                    </div>

                    <Link
                        :href="route('automations.create', isSuperAdmin && filters?.business_id ? { business_id: filters.business_id } : {})"
                        class="btn-primary"
                    >
                        + Create Workflow
                    </Link>
                </div>
            </div>

            <!-- Pre-built Blueprints & Templates -->
            <div v-if="templates && templates.length > 0">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-[#211812] uppercase tracking-wider text-[11px]">Platform Blueprints</h2>
                        <p class="text-xs text-stone-500 mt-0.5">Ready-to-use workflows configured with triggers, AI reasoning, and omnichannel actions.</p>
                    </div>
                    <span class="text-xs font-mono text-stone-500 bg-stone-100 px-2 py-0.5 rounded">
                        {{ templates.length }} Blueprints
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="tpl in templates"
                        :key="tpl.id"
                        class="card p-5 flex flex-col justify-between bg-white border border-[#e8e2d9] rounded-xl shadow-xs"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-2.5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider bg-stone-100 text-stone-700 border border-stone-200">
                                    {{ (tpl.industry || 'General').replace('_', ' ') }}
                                </span>
                                <span class="text-[11px] text-stone-500 font-mono">
                                    {{ tpl.workflow_snapshot?.nodes?.length || 0 }} nodes
                                </span>
                            </div>
                            <h3 class="text-sm font-bold text-[#211812] mb-1">
                                {{ tpl.name }}
                            </h3>
                            <p class="text-xs text-stone-600 leading-relaxed line-clamp-2">
                                {{ tpl.description || 'Pre-configured automation flow.' }}
                            </p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-[#e8e2d9] flex items-center justify-between">
                            <button
                                v-if="isSuperAdmin"
                                @click="deleteTemplate(tpl)"
                                type="button"
                                class="text-xs text-rose-600 hover:text-rose-800 transition-colors cursor-pointer"
                            >
                                Remove Blueprint
                            </button>
                            <span v-else class="text-[11px] text-stone-400">
                                Global Template
                            </span>

                            <button
                                @click="importTemplate(tpl.id)"
                                type="button"
                                class="btn-secondary"
                            >
                                Use Template
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Workflows List -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-[#211812] uppercase tracking-wider text-[11px]">Active &amp; Draft Workflows</h2>
                        <p class="text-xs text-stone-500 mt-0.5">Automations currently deployed or in design mode.</p>
                    </div>
                    <span class="text-xs font-mono text-stone-500 bg-stone-100 px-2 py-0.5 rounded">
                        {{ workflows.length }} Total
                    </span>
                </div>

                <!-- Empty State -->
                <div v-if="workflows.length === 0" class="card p-12 text-center border-dashed border-[#e8e2d9] bg-white rounded-xl">
                    <div class="w-10 h-10 rounded-full bg-stone-100 text-stone-600 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-[#211812] mb-1">No custom workflows created</h3>
                    <p class="text-xs text-stone-500 max-w-sm mx-auto mb-4">
                        Create a blank flow using the visual builder or select one of the blueprint templates above.
                    </p>
                    <Link
                        :href="route('automations.create', isSuperAdmin && filters?.business_id ? { business_id: filters.business_id } : {})"
                        class="btn-primary"
                    >
                        Create Blank Workflow
                    </Link>
                </div>

                <!-- Workflow Cards Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="wf in workflows"
                        :key="wf.id"
                        class="card p-5 flex flex-col justify-between bg-white border border-[#e8e2d9] rounded-xl shadow-xs"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-2.5">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-medium"
                                    :class="wf.is_active ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-stone-100 text-stone-600 border border-stone-200'"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full" :class="wf.is_active ? 'bg-emerald-500' : 'bg-stone-400'"></span>
                                    {{ wf.is_active ? 'Active' : 'Draft' }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <span v-if="wf.business" class="text-[10px] font-semibold px-1.5 py-0.2 rounded bg-amber-50 text-amber-800 border border-amber-200">
                                        {{ wf.business.name }}
                                    </span>
                                    <span class="text-[11px] text-stone-400 font-mono">v{{ wf.version }}</span>
                                </div>
                            </div>

                            <h3 class="text-sm font-bold text-[#211812] mb-1">{{ wf.name }}</h3>
                            <p class="text-xs text-stone-600 line-clamp-2 mb-4">{{ wf.description || 'No description provided.' }}</p>

                            <div class="grid grid-cols-2 gap-2 text-xs p-2.5 rounded bg-[#faf8f5] border border-[#e8e2d9]">
                                <div>
                                    <span class="block text-[10px] text-stone-500 uppercase tracking-wider font-semibold">Nodes</span>
                                    <span class="font-bold text-[#211812] tabular-nums font-mono">{{ wf.nodes_count }}</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] text-stone-500 uppercase tracking-wider font-semibold">Executions</span>
                                    <span class="font-bold text-[#211812] tabular-nums font-mono">{{ wf.executions_count }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 mt-4 border-t border-[#e8e2d9] flex items-center justify-between">
                            <button
                                @click="deleteWorkflow(wf)"
                                type="button"
                                class="text-xs text-stone-500 hover:text-red-700 transition-colors cursor-pointer"
                            >
                                Delete
                            </button>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="route('automations.executions', wf.id)"
                                    class="text-xs font-medium text-stone-600 hover:text-[#211812] px-2 py-1"
                                >
                                    Logs ({{ wf.executions_count }})
                                </Link>
                                <Link
                                    :href="route('automations.edit', wf.id)"
                                    class="btn-secondary"
                                >
                                    Edit Canvas
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
