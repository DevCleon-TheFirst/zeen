<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            conversations_open: 0,
            conversations_ai: 0,
            conversations_escalated: 0,
            appointments_pending: 0,
            catalog_items: 0,
            active_automations: 0,
            channels: {
                telegram:  { messages: 0, ai_rate: 0, change: '+0%', positive: true },
                whatsapp:  { messages: 0, ai_rate: 0, change: '+0%', positive: true },
                messenger: { messages: 0, ai_rate: 0, change: '+0%', positive: true },
            },
        }),
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const activePeriod = ref('7D');

const aiContainmentRate = computed(() => {
    const open = props.stats.conversations_open || 0;
    const ai   = props.stats.conversations_ai || 0;
    if (!open) return 0;
    return Math.round((ai / open) * 100);
});

const channelsList = computed(() => [
    {
        key: 'whatsapp',
        name: 'WhatsApp Cloud',
        status: 'Active',
        messages: props.stats.channels?.whatsapp?.messages || 0,
        aiRate: props.stats.channels?.whatsapp?.ai_rate || 0,
        href: route('channels.index'),
    },
    {
        key: 'telegram',
        name: 'Telegram Bot',
        status: 'Active',
        messages: props.stats.channels?.telegram?.messages || 0,
        aiRate: props.stats.channels?.telegram?.ai_rate || 0,
        href: route('channels.index'),
    },
    {
        key: 'messenger',
        name: 'Meta Messenger',
        status: 'Active',
        messages: props.stats.channels?.messenger?.messages || 0,
        aiRate: props.stats.channels?.messenger?.ai_rate || 0,
        href: route('channels.index'),
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-sm font-semibold text-[#241e19]">Overview</h2>
        </template>

        <div class="p-6 md:p-8 space-y-6 max-w-7xl mx-auto">

            <!-- ── PAGE HEADER ───────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-xl font-semibold text-[#241e19] tracking-tight">
                        Dashboard Overview
                    </h1>
                    <p class="text-xs text-stone-500 mt-1">
                        Live monitoring of inbound communications, AI automation, and customer appointments.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Period Filter -->
                    <div class="inline-flex rounded-md border border-[#e8e2d9] bg-white p-0.5 text-xs">
                        <button
                            v-for="p in ['Today', '7D', '30D', '90D']"
                            :key="p"
                            @click="activePeriod = p"
                            class="px-2.5 py-1 rounded text-xs font-medium transition-colors"
                            :class="activePeriod === p ? 'bg-[#4a3324] text-white' : 'text-stone-600 hover:text-[#241e19]'"
                        >
                            {{ p }}
                        </button>
                    </div>

                    <Link
                        :href="route('inbox.index')"
                        class="btn-primary"
                    >
                        Open Inbox
                    </Link>
                </div>
            </div>

            <!-- ── 4 KPI STAT CARDS ───────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- Open Conversations -->
                <div class="card p-5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider text-stone-500">Open Sessions</span>
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-2xl font-semibold tracking-tight text-[#241e19] tabular-nums">
                            {{ stats.conversations_open }}
                        </span>
                        <span class="text-xs text-stone-500">Inbound threads</span>
                    </div>
                </div>

                <!-- AI Automation -->
                <div class="card p-5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider text-stone-500">AI Handled</span>
                        <span class="text-xs font-medium text-[#7b5537]">{{ aiContainmentRate }}% rate</span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-2xl font-semibold tracking-tight text-[#241e19] tabular-nums">
                            {{ stats.conversations_ai }}
                        </span>
                        <span class="text-xs text-stone-500">Autonomous</span>
                    </div>
                </div>

                <!-- Escalated to Staff -->
                <div class="card p-5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider text-stone-500">Escalated</span>
                        <span v-if="stats.conversations_escalated > 0" class="badge-neutral text-amber-700 bg-amber-50 border-amber-200">
                            Attention
                        </span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-2xl font-semibold tracking-tight text-[#241e19] tabular-nums">
                            {{ stats.conversations_escalated }}
                        </span>
                        <span class="text-xs text-stone-500">Human triage</span>
                    </div>
                </div>

                <!-- Pending Bookings -->
                <div class="card p-5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider text-stone-500">Appointments</span>
                        <Link :href="route('appointments.index')" class="text-xs text-[#7b5537] hover:underline">
                            View
                        </Link>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-2xl font-semibold tracking-tight text-[#241e19] tabular-nums">
                            {{ stats.appointments_pending }}
                        </span>
                        <span class="text-xs text-stone-500">Pending</span>
                    </div>
                </div>

            </div>

            <!-- ── CHANNELS OVERVIEW ─────────────────────────── -->
            <div class="card p-5">
                <div class="flex items-center justify-between pb-4 border-b border-[#e8e2d9]">
                    <div>
                        <h2 class="text-sm font-semibold text-[#241e19]">Channel Performance</h2>
                        <p class="text-xs text-stone-500 mt-0.5">Inbound message volume and automation rates across connected networks.</p>
                    </div>
                    <Link :href="route('channels.index')" class="btn-secondary">
                        Manage Channels
                    </Link>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div
                        v-for="ch in channelsList"
                        :key="ch.key"
                        class="p-4 rounded-md border border-[#e8e2d9] bg-[#faf8f5] flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-[#241e19]">{{ ch.name }}</span>
                                <span class="inline-flex items-center gap-1 text-[11px] text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    {{ ch.status }}
                                </span>
                            </div>
                            <div class="mt-3">
                                <div class="text-xl font-semibold text-[#241e19] tabular-nums">
                                    {{ ch.messages.toLocaleString() }}
                                </div>
                                <div class="text-[11px] text-stone-500 mt-0.5">Total messages handled</div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-[#e8e2d9] flex items-center justify-between text-xs">
                            <span class="text-stone-500">AI Handling</span>
                            <span class="font-medium text-[#4a3324]">{{ ch.aiRate }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── LOWER 2-COLUMN OPERATIONAL SUMMARY ─────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                <!-- Live Inbox Stream Status (2 cols) -->
                <div class="lg:col-span-2 card p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-4 border-b border-[#e8e2d9]">
                            <div>
                                <h2 class="text-sm font-semibold text-[#241e19]">Active Inbound Queue</h2>
                                <p class="text-xs text-stone-500 mt-0.5">Live customer threads requiring human response or monitoring.</p>
                            </div>
                            <Link :href="route('inbox.index')" class="text-xs font-medium text-[#7b5537] hover:underline">
                                Full Inbox →
                            </Link>
                        </div>

                        <!-- Empty state -->
                        <div v-if="stats.conversations_open === 0" class="py-12 text-center">
                            <div class="w-10 h-10 rounded-full bg-[#f5efe6] text-[#7b5537] flex items-center justify-center mx-auto mb-3">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <h3 class="text-sm font-medium text-[#241e19]">No pending customer threads</h3>
                            <p class="text-xs text-stone-500 mt-1 max-w-sm mx-auto">
                                Inbound channels are operating normally. All messages have been answered or resolved.
                            </p>
                        </div>

                        <!-- Active threads summary -->
                        <div v-else class="divide-y divide-[#e8e2d9] mt-2">
                            <div class="py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-[#f5efe6] text-[#4a3324] flex items-center justify-center text-xs font-medium">
                                        C
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-[#241e19]">Active Inbound Conversation</p>
                                        <p class="text-[11px] text-stone-500">Autonomous AI handling in progress</p>
                                    </div>
                                </div>
                                <Link :href="route('inbox.index')" class="btn-secondary">
                                    View Thread
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdown ribbon -->
                    <div class="grid grid-cols-3 gap-3 pt-4 mt-4 border-t border-[#e8e2d9] text-center">
                        <div class="p-2.5 rounded bg-[#faf8f5] border border-[#e8e2d9]">
                            <p class="text-base font-semibold text-[#241e19] tabular-nums">{{ stats.conversations_open }}</p>
                            <p class="text-[10px] text-stone-500 uppercase tracking-wider mt-0.5">Open</p>
                        </div>
                        <div class="p-2.5 rounded bg-[#faf8f5] border border-[#e8e2d9]">
                            <p class="text-base font-semibold text-[#241e19] tabular-nums">{{ stats.conversations_ai }}</p>
                            <p class="text-[10px] text-stone-500 uppercase tracking-wider mt-0.5">AI Managed</p>
                        </div>
                        <div class="p-2.5 rounded bg-[#faf8f5] border border-[#e8e2d9]">
                            <p class="text-base font-semibold text-amber-700 tabular-nums">{{ stats.conversations_escalated }}</p>
                            <p class="text-[10px] text-stone-500 uppercase tracking-wider mt-0.5">Escalated</p>
                        </div>
                    </div>
                </div>

                <!-- Operational Systems (1 col) -->
                <div class="space-y-4">

                    <!-- Automations Card -->
                    <div class="card p-5">
                        <div class="flex items-center justify-between pb-3 border-b border-[#e8e2d9]">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-stone-500">Automations</h3>
                            <Link :href="route('automations.index')" class="text-xs text-[#7b5537] hover:underline">
                                View all
                            </Link>
                        </div>

                        <div class="mt-3 space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-stone-500">Active Workflows</span>
                                <span class="font-semibold text-[#241e19] tabular-nums">{{ stats.active_automations }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-stone-500">Autonomous Resolution</span>
                                <span class="font-semibold text-[#241e19] tabular-nums">{{ aiContainmentRate }}%</span>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-[#e8e2d9]">
                            <Link
                                :href="route('automations.create')"
                                class="btn-secondary w-full"
                            >
                                + New Workflow
                            </Link>
                        </div>
                    </div>

                    <!-- Catalog & Bookings Summary -->
                    <div class="card p-5">
                        <div class="pb-3 border-b border-[#e8e2d9]">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-stone-500">Inventory &amp; Services</h3>
                        </div>

                        <div class="mt-3 space-y-3 text-xs">
                            <Link :href="route('catalog.index')" class="flex items-center justify-between hover:bg-[#faf8f5] p-1.5 rounded transition-colors">
                                <span class="text-stone-600">Active Catalog Items</span>
                                <span class="font-semibold text-[#241e19] tabular-nums">{{ stats.catalog_items }}</span>
                            </Link>
                            <Link :href="route('appointments.index')" class="flex items-center justify-between hover:bg-[#faf8f5] p-1.5 rounded transition-colors">
                                <span class="text-stone-600">Pending Bookings</span>
                                <span class="font-semibold text-[#241e19] tabular-nums">{{ stats.appointments_pending }}</span>
                            </Link>
                            <Link :href="route('settings.ai')" class="flex items-center justify-between hover:bg-[#faf8f5] p-1.5 rounded transition-colors">
                                <span class="text-stone-600">AI Prompt Configuration</span>
                                <span class="text-[#7b5537] font-medium">Settings →</span>
                            </Link>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </AuthenticatedLayout>
</template>
