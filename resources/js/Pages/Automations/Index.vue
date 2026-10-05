<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    workflows: Array,
    templates: Array,
});

const importTemplate = (templateId) => {
    router.post(route('automations.fromTemplate', templateId));
};

const deleteWorkflow = (workflow) => {
    if (confirm(`Delete automation workflow "${workflow.name}"?`)) {
        router.delete(route('automations.destroy', workflow.id));
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Automations" />

        <template #header>
            <h2 class="text-sm font-semibold text-[#241e19]">Automations</h2>
        </template>

        <div class="p-6 md:p-8 space-y-8 max-w-7xl mx-auto">

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-xl font-semibold text-[#241e19] tracking-tight">
                        Automation Workflows
                    </h1>
                    <p class="text-xs text-stone-500 mt-1">
                        Build and orchestrate multi-step conversational flows across all connected communication channels.
                    </p>
                </div>
                <Link
                    :href="route('automations.create')"
                    class="btn-primary"
                >
                    + New Workflow
                </Link>
            </div>

            <!-- Pre-built Templates -->
            <div v-if="templates && templates.length > 0">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-semibold text-[#241e19]">Pre-built Blueprints</h2>
                        <p class="text-xs text-stone-500">Quick-start templates configured with triggers, AI reasoning, and actions.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="tpl in templates"
                        :key="tpl.id"
                        class="card p-5 flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-2.5">
                                <span class="badge-neutral uppercase tracking-wider text-[10px]">
                                    {{ tpl.industry.replace('_', ' ') }}
                                </span>
                                <span class="text-[11px] text-stone-500 font-mono">
                                    {{ tpl.workflow_snapshot?.nodes?.length || 0 }} nodes
                                </span>
                            </div>
                            <h3 class="text-sm font-semibold text-[#241e19] mb-1">
                                {{ tpl.name }}
                            </h3>
                            <p class="text-xs text-stone-600 leading-relaxed">
                                {{ tpl.description }}
                            </p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-[#e8e2d9] flex items-center justify-between">
                            <span class="text-[11px] text-stone-500">
                                Inbound Trigger
                            </span>
                            <button
                                @click="importTemplate(tpl.id)"
                                type="button"
                                class="btn-secondary"
                            >
                                Use Template →
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Your Workflows -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-semibold text-[#241e19]">Custom Workflows</h2>
                        <p class="text-xs text-stone-500">Active and draft automations built for your business.</p>
                    </div>
                    <span class="badge-neutral">
                        {{ workflows.length }} Total
                    </span>
                </div>

                <!-- Empty State -->
                <div v-if="workflows.length === 0" class="card p-12 text-center border-dashed">
                    <div class="w-10 h-10 rounded-full bg-[#f5efe6] text-[#7b5537] flex items-center justify-center mx-auto mb-3">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-medium text-[#241e19] mb-1">No custom workflows created</h3>
                    <p class="text-xs text-stone-500 max-w-sm mx-auto mb-4">
                        Create a blank flow using the visual builder or select one of the blueprint templates above.
                    </p>
                    <Link
                        :href="route('automations.create')"
                        class="btn-primary"
                    >
                        Create Blank Workflow
                    </Link>
                </div>

                <!-- Workflow Cards -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="wf in workflows"
                        :key="wf.id"
                        class="card p-5 flex flex-col justify-between"
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
                                <span class="text-[11px] text-stone-500 font-mono">v{{ wf.version }}</span>
                            </div>

                            <h3 class="text-sm font-semibold text-[#241e19] mb-1">{{ wf.name }}</h3>
                            <p class="text-xs text-stone-600 line-clamp-2 mb-4">{{ wf.description || 'No description provided.' }}</p>

                            <div class="grid grid-cols-2 gap-2 text-xs p-2.5 rounded bg-[#faf8f5] border border-[#e8e2d9]">
                                <div>
                                    <span class="block text-[10px] text-stone-500 uppercase tracking-wider">Nodes</span>
                                    <span class="font-semibold text-[#241e19] tabular-nums">{{ wf.nodes_count }}</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] text-stone-500 uppercase tracking-wider">Executions</span>
                                    <span class="font-semibold text-[#241e19] tabular-nums">{{ wf.executions_count }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 mt-4 border-t border-[#e8e2d9] flex items-center justify-between">
                            <button
                                @click="deleteWorkflow(wf)"
                                type="button"
                                class="text-xs text-stone-500 hover:text-red-700 transition-colors"
                            >
                                Delete
                            </button>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="route('automations.executions', wf.id)"
                                    class="text-xs font-medium text-[#7b5537] hover:underline px-2 py-1"
                                >
                                    Logs ({{ wf.executions_count }})
                                </Link>
                                <Link
                                    :href="route('automations.edit', wf.id)"
                                    class="btn-secondary"
                                >
                                    Edit Canvas →
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
