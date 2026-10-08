<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    businesses: {
        type: Object,
        required: true,
    },
    users: {
        type: Object,
        default: () => ({ data: [], links: [], total: 0 }),
    },
    recentPayments: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', user_search: '' }),
    },
});

const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const activeTab = ref(urlParams?.get('tab') || 'stores');

const selectTab = (tab) => {
    activeTab.value = tab;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    }
};

const search = ref(props.filters.search || '');
const userSearch = ref(props.filters.user_search || '');
const togglingId = ref(null);
const updatingPlanId = ref(null);

// Upstream DeepSeek live balance state
const liveDeepseek = ref(props.stats?.deepseek_balance || null);
const refreshingBalance = ref(false);

const refreshDeepSeekBalance = async () => {
    refreshingBalance.value = true;
    try {
        const res = await axios.get(route('admin.deepseek-balance'));
        if (res.data) {
            liveDeepseek.value = res.data;
        }
    } catch (err) {
        console.error('Failed to refresh DeepSeek balance:', err);
    } finally {
        refreshingBalance.value = false;
    }
};

// Modal state for Adjusting Credits
const showCreditModal = ref(false);
const creditBusiness = ref(null);
const creditAmount = ref(500);
const creditReason = ref('');
const creditSubmitting = ref(false);

const openCreditModal = (business) => {
    creditBusiness.value = business;
    creditAmount.value = 500;
    creditReason.value = 'Platform bonus / top-up';
    showCreditModal.value = true;
};

const submitCreditAdjustment = () => {
    if (!creditBusiness.value || !creditAmount.value) return;
    creditSubmitting.value = true;
    router.post(route('admin.businesses.credits', { business: creditBusiness.value.id }), {
        amount: parseInt(creditAmount.value, 10),
        reason: creditReason.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            creditSubmitting.value = false;
            showCreditModal.value = false;
        },
    });
};

const toggleBusiness = (business) => {
    togglingId.value = business.id;
    router.post(route('admin.businesses.toggle', { business: business.id }), {}, {
        preserveScroll: true,
        onFinish: () => { togglingId.value = null; },
    });
};

const changePlan = (business, newPlan) => {
    updatingPlanId.value = business.id;
    router.patch(route('admin.businesses.plan', { business: business.id }), { plan: newPlan }, {
        preserveScroll: true,
        onFinish: () => { updatingPlanId.value = null; },
    });
};

const inspectStore = (business) => {
    if (!business || !business.id) return;
    router.post(route('admin.businesses.impersonate', { business: business.id }));
};

let searchTimeout = null;
watch(search, (newVal) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.dashboard'), {
            search: newVal,
            user_search: userSearch.value,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

let userSearchTimeout = null;
watch(userSearch, (newVal) => {
    clearTimeout(userSearchTimeout);
    userSearchTimeout = setTimeout(() => {
        router.get(route('admin.dashboard'), {
            search: search.value,
            user_search: newVal,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

const formatCurrency = (val) => {
    return '₦' + Number(val || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <Head title="Super Admin — Platform Command Center" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2 text-xs">
                <span class="font-bold text-[#211812] tracking-tight">Platform Command Center</span>
                <span class="text-stone-300">/</span>
                <span class="text-stone-500 font-medium">Overview</span>
            </div>
        </template>

        <div class="p-6 md:p-8 space-y-7 max-w-7xl mx-auto">

            <!-- Executive Header Banner -->
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-4 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-xl font-bold text-[#211812] tracking-tight">
                        Platform Overview &amp; Control
                    </h1>
                    <p class="text-xs text-stone-500 mt-1">
                        High-level financial run-rate, user directory with store associations, tenant accounts, and AI wholesale infrastructure.
                    </p>
                    <!-- System Health & Connectivity Badges -->
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-medium"
                            :class="stats.gateway_healthy ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="stats.gateway_healthy ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                            WhatsApp Gateway: {{ stats.gateway_healthy ? 'Operational' : 'Standby' }}
                        </span>

                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-medium"
                            :class="liveDeepseek?.is_available ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="liveDeepseek?.is_available ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                            DeepSeek Wholesale: {{ liveDeepseek?.is_available ? 'Connected' : 'Offline' }}
                        </span>

                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-medium bg-stone-100 text-stone-700 border border-stone-200"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="stats.failed_jobs > 0 ? 'bg-rose-500' : 'bg-stone-500'"></span>
                            Queue Workers: {{ stats.pending_jobs }} Queued <span v-if="stats.failed_jobs > 0" class="text-rose-600 font-bold ml-1">({{ stats.failed_jobs }} failed)</span>
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-start sm:self-center">
                    <button
                        @click="refreshDeepSeekBalance"
                        :disabled="refreshingBalance"
                        class="text-xs text-[#211812] font-medium inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white hover:bg-stone-50 border border-[#e8e2d9] transition-colors cursor-pointer disabled:opacity-50 shadow-xs"
                    >
                        <svg class="w-3.5 h-3.5 text-stone-600" :class="refreshingBalance ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>{{ refreshingBalance ? 'Syncing...' : 'Sync DeepSeek API' }}</span>
                    </button>
                </div>
            </div>

            <!-- EXECUTIVE METRICS ROW (6 Key Telemetry Cards - Strict Preservation) -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
                <!-- 1. Platform GMV -->
                <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-semibold text-stone-500 uppercase tracking-wider">Platform GMV</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    </div>
                    <p class="text-lg font-bold text-[#211812] font-mono tabular-nums mt-1.5">{{ formatCurrency(stats.total_gmv) }}</p>
                    <p class="text-[11px] text-stone-500 mt-0.5">{{ stats.total_orders.toLocaleString() }} orders</p>
                </div>

                <!-- 2. Estimated MRR -->
                <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-semibold text-stone-500 uppercase tracking-wider">Estimated MRR</span>
                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-stone-100 text-stone-600">SaaS</span>
                    </div>
                    <p class="text-lg font-bold text-[#211812] font-mono tabular-nums mt-1.5">{{ formatCurrency(stats.estimated_mrr) }}</p>
                    <p class="text-[11px] text-stone-500 mt-0.5">{{ stats.plan_breakdown.starter + stats.plan_breakdown.pro + stats.plan_breakdown.enterprise }} paid stores</p>
                </div>

                <!-- 3. Total Registered Users -->
                <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-semibold text-stone-500 uppercase tracking-wider">Total Users</span>
                        <span class="text-[9px] font-semibold px-1.5 py-0.2 rounded bg-stone-100 text-stone-600">Accounts</span>
                    </div>
                    <p class="text-lg font-bold text-[#211812] font-mono tabular-nums mt-1.5">{{ stats.total_users.toLocaleString() }}</p>
                    <p class="text-[11px] text-stone-500 mt-0.5">Owners &amp; team staff</p>
                </div>

                <!-- 4. Active Stores -->
                <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-semibold text-stone-500 uppercase tracking-wider">Active Stores</span>
                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-700">Live</span>
                    </div>
                    <p class="text-lg font-bold text-[#211812] font-mono tabular-nums mt-1.5">{{ stats.active_businesses }} <span class="text-xs font-normal text-stone-400">/ {{ stats.total_businesses }}</span></p>
                    <p class="text-[11px] text-stone-500 mt-0.5">Active storefronts</p>
                </div>

                <!-- 5. DeepSeek Live Balance -->
                <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-semibold text-stone-500 uppercase tracking-wider">DeepSeek Wholesale</span>
                        <span class="w-1.5 h-1.5 rounded-full" :class="liveDeepseek?.is_available ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                    </div>
                    <p class="text-lg font-bold text-emerald-700 font-mono tabular-nums mt-1.5">
                        {{ liveDeepseek?.formatted || ('$' + Number(liveDeepseek?.total_balance || 0).toFixed(2)) }}
                    </p>
                    <p class="text-[11px] text-stone-500 mt-0.5">~{{ formatCurrency((liveDeepseek?.total_balance || 0) * 1600) }}</p>
                </div>

                <!-- 6. Total Interactions -->
                <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-semibold text-stone-500 uppercase tracking-wider">Interactions</span>
                        <span class="text-[9px] font-semibold px-1.5 py-0.2 rounded bg-stone-100 text-stone-600">Omni</span>
                    </div>
                    <p class="text-lg font-bold text-[#211812] font-mono tabular-nums mt-1.5">{{ stats.total_conversations.toLocaleString() }}</p>
                    <p class="text-[11px] text-stone-500 mt-0.5">{{ stats.total_messages.toLocaleString() }} total messages</p>
                </div>
            </div>

            <!-- STANDARD SAAS TABS NAVIGATION (Zero Emojis, Clean SVG Icons) -->
            <div class="border-b border-[#e8e2d9]">
                <nav class="flex space-x-6">
                    <!-- Tab: Stores & Workspaces -->
                    <button
                        @click="selectTab('stores')"
                        class="pb-3 text-xs font-semibold inline-flex items-center gap-2 border-b-2 transition-colors cursor-pointer"
                        :class="activeTab === 'stores' ? 'border-[#211812] text-[#211812]' : 'border-transparent text-stone-500 hover:text-stone-800'"
                    >
                        <svg class="w-4 h-4 text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Stores &amp; Workspaces</span>
                        <span class="px-1.5 py-0.2 text-[10px] font-mono rounded bg-stone-100 text-stone-600">{{ businesses.total }}</span>
                    </button>

                    <!-- Tab: User Accounts & Shop Ownership -->
                    <button
                        @click="selectTab('users')"
                        class="pb-3 text-xs font-semibold inline-flex items-center gap-2 border-b-2 transition-colors cursor-pointer"
                        :class="activeTab === 'users' ? 'border-[#211812] text-[#211812]' : 'border-transparent text-stone-500 hover:text-stone-800'"
                    >
                        <svg class="w-4 h-4 text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>User Accounts &amp; Stores</span>
                        <span class="px-1.5 py-0.2 text-[10px] font-mono rounded bg-stone-100 text-stone-600">{{ users.total || stats.total_users }}</span>
                    </button>

                    <!-- Tab: AI Infrastructure & Tokens -->
                    <button
                        @click="selectTab('ai')"
                        class="pb-3 text-xs font-semibold inline-flex items-center gap-2 border-b-2 transition-colors cursor-pointer"
                        :class="activeTab === 'ai' ? 'border-[#211812] text-[#211812]' : 'border-transparent text-stone-500 hover:text-stone-800'"
                    >
                        <svg class="w-4 h-4 text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>AI Infrastructure &amp; Usage</span>
                    </button>

                    <!-- Tab: Platform Transactions -->
                    <button
                        @click="selectTab('transactions')"
                        class="pb-3 text-xs font-semibold inline-flex items-center gap-2 border-b-2 transition-colors cursor-pointer"
                        :class="activeTab === 'transactions' ? 'border-[#211812] text-[#211812]' : 'border-transparent text-stone-500 hover:text-stone-800'"
                    >
                        <svg class="w-4 h-4 text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <span>Transactions</span>
                        <span class="px-1.5 py-0.2 text-[10px] font-mono rounded bg-stone-100 text-stone-600">{{ recentPayments.length }}</span>
                    </button>
                </nav>
            </div>

            <!-- TAB 1: STORES & WORKSPACES DIRECTORY -->
            <div v-if="activeTab === 'stores'" class="bg-white border border-[#e8e2d9] rounded-xl overflow-hidden shadow-xs">
                <div class="p-4 sm:p-5 border-b border-[#e8e2d9] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-xs font-bold text-[#211812] uppercase tracking-wider">Merchant Stores Directory</h3>
                        <p class="text-[11px] text-stone-500 mt-0.5">Manage tenant workspaces, adjust AI credits, switch service plans, or inspect store backoffices.</p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="relative">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search store name, industry, email..."
                                class="text-xs rounded-lg border border-[#e8e2d9] bg-[#faf8f5] py-1.5 pl-8 pr-3 text-[#211812] focus:outline-none focus:ring-1 focus:ring-[#7b5537] w-64"
                            />
                            <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-stone-600 bg-stone-100 px-2.5 py-1 rounded-lg">
                            {{ businesses.total }} Stores
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#faf8f5] text-stone-600 border-b border-[#e8e2d9] font-medium text-[11px]">
                            <tr>
                                <th class="p-3.5">Store / Brand</th>
                                <th class="p-3.5">Industry</th>
                                <th class="p-3.5">AI Credits</th>
                                <th class="p-3.5">Volume</th>
                                <th class="p-3.5">Plan Tier</th>
                                <th class="p-3.5">Status</th>
                                <th class="p-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0ece6]">
                            <tr v-for="b in businesses.data" :key="b.id" class="hover:bg-[#faf8f5]/60 transition-colors">
                                <td class="p-3.5">
                                    <div class="font-bold text-[#211812]">{{ b.name }}</div>
                                    <div class="text-[11px] text-stone-400">Created {{ formatDate(b.created_at) }} &middot; {{ b.users_count }} staff</div>
                                </td>
                                <td class="p-3.5 capitalize text-stone-600">
                                    {{ (b.industry || 'general').replace('_', ' ') }}
                                </td>
                                <td class="p-3.5">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold font-mono tabular-nums"
                                            :class="(b.ai_credits_balance || 0) > 100 ? 'bg-amber-100 text-amber-900' : ((b.ai_credits_balance || 0) > 0 ? 'bg-orange-100 text-orange-900' : 'bg-rose-100 text-rose-800')"
                                        >
                                            {{ (b.ai_credits_balance || 0).toLocaleString() }} Credits
                                        </span>
                                        <button
                                            @click="openCreditModal(b)"
                                            title="Add / Adjust Credits"
                                            class="text-[11px] text-stone-400 hover:text-[#211812] p-1 rounded hover:bg-stone-100 cursor-pointer"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                                <td class="p-3.5 text-stone-600">
                                    <div class="text-[11px]">{{ b.conversations_count || 0 }} chats &middot; {{ b.orders_count || 0 }} orders</div>
                                    <div class="text-[10px] text-stone-400">{{ b.channels_count || 0 }} channels connected</div>
                                </td>
                                <td class="p-3.5">
                                    <select
                                        :value="b.plan || 'free'"
                                        @change="changePlan(b, $event.target.value)"
                                        :disabled="updatingPlanId === b.id"
                                        class="text-xs rounded-lg border border-[#e8e2d9] bg-white py-1 px-2 text-[#211812] focus:ring-1 focus:ring-[#7b5537] cursor-pointer"
                                    >
                                        <option value="free">Free Tier</option>
                                        <option value="starter">Starter (₦15k)</option>
                                        <option value="pro">Pro (₦35k)</option>
                                        <option value="enterprise">Enterprise (₦75k)</option>
                                    </select>
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                        :class="b.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-stone-100 text-stone-600 border border-stone-200'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="b.is_active ? 'bg-emerald-500' : 'bg-stone-400'"></span>
                                        {{ b.is_active ? 'Active' : 'Suspended' }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right space-x-2">
                                    <button
                                        @click="inspectStore(b)"
                                        title="Inspect this store in merchant mode"
                                        class="px-2.5 py-1 text-[11px] font-semibold text-stone-800 bg-stone-100 hover:bg-stone-200 rounded-md transition-colors cursor-pointer border border-stone-300 inline-flex items-center gap-1"
                                    >
                                        <svg class="w-3 h-3 text-stone-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Inspect Store</span>
                                    </button>

                                    <button
                                        @click="toggleBusiness(b)"
                                        :disabled="togglingId === b.id"
                                        class="px-2.5 py-1 text-[11px] font-medium rounded-md transition-colors cursor-pointer border"
                                        :class="b.is_active ? 'text-rose-700 bg-rose-50 hover:bg-rose-100 border-rose-200' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border-emerald-200'"
                                    >
                                        {{ b.is_active ? 'Suspend' : 'Activate' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="businesses.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-stone-400">
                                    No stores match your search filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Stores Pagination -->
                <div v-if="businesses.links && businesses.links.length > 3" class="p-4 border-t border-[#e8e2d9] flex items-center justify-between">
                    <span class="text-xs text-stone-500">
                        Showing page {{ businesses.current_page }} of {{ businesses.last_page }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Link
                            v-for="(link, i) in businesses.links"
                            :key="i"
                            :href="link.url || '#'"
                            class="px-2.5 py-1 text-xs rounded border transition-colors"
                            :class="link.active ? 'bg-[#211812] text-white border-[#211812]' : (link.url ? 'bg-white text-stone-600 border-[#e8e2d9] hover:bg-stone-50' : 'text-stone-300 border-transparent cursor-not-allowed')"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>

            <!-- TAB 2: USER ACCOUNTS & SHOP OWNERSHIP DIRECTORY (New Requirement) -->
            <div v-if="activeTab === 'users'" class="bg-white border border-[#e8e2d9] rounded-xl overflow-hidden shadow-xs">
                <div class="p-4 sm:p-5 border-b border-[#e8e2d9] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-xs font-bold text-[#211812] uppercase tracking-wider">Registered Users &amp; Store Ownership</h3>
                        <p class="text-[11px] text-stone-500 mt-0.5">Global directory of merchant owners, store managers, and staff accounts mapped to their respective stores.</p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="relative">
                            <input
                                v-model="userSearch"
                                type="text"
                                placeholder="Search user name, email, phone, store..."
                                class="text-xs rounded-lg border border-[#e8e2d9] bg-[#faf8f5] py-1.5 pl-8 pr-3 text-[#211812] focus:outline-none focus:ring-1 focus:ring-[#7b5537] w-64"
                            />
                            <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-stone-600 bg-stone-100 px-2.5 py-1 rounded-lg">
                            {{ users.total || stats.total_users }} Users
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#faf8f5] text-stone-600 border-b border-[#e8e2d9] font-medium text-[11px]">
                            <tr>
                                <th class="p-3.5">User</th>
                                <th class="p-3.5">Contact</th>
                                <th class="p-3.5">Role</th>
                                <th class="p-3.5">Assigned Shop / Store</th>
                                <th class="p-3.5">Status</th>
                                <th class="p-3.5">Joined Date</th>
                                <th class="p-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0ece6]">
                            <tr v-for="u in users.data" :key="u.id" class="hover:bg-[#faf8f5]/60 transition-colors">
                                <td class="p-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-stone-200 text-stone-700 flex items-center justify-center font-bold text-[11px] uppercase">
                                            {{ (u.name || 'U').substring(0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-[#211812]">{{ u.name }}</div>
                                            <div class="text-[11px] text-stone-500 font-mono">{{ u.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3.5 text-stone-600 font-mono text-[11px]">
                                    {{ u.phone || '-' }}
                                </td>
                                <td class="p-3.5">
                                    <span
                                        v-if="u.is_super_admin"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200"
                                    >
                                        Super Admin
                                    </span>
                                    <span
                                        v-else-if="u.role === 'owner'"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200"
                                    >
                                        Store Owner
                                    </span>
                                    <span
                                        v-else-if="u.role === 'manager'"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200"
                                    >
                                        Store Manager
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-stone-100 text-stone-600 border border-stone-200 capitalize"
                                    >
                                        {{ u.role || 'Staff' }}
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <div v-if="u.business" class="space-y-0.5">
                                        <div class="font-semibold text-[#211812] flex items-center gap-1.5">
                                            <span>{{ u.business.name }}</span>
                                            <span class="text-[9px] uppercase font-mono px-1.5 py-0.2 rounded bg-stone-100 text-stone-600">
                                                {{ u.business.plan || 'Free' }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-stone-400 font-mono">
                                            zeen.me/{{ u.business.slug || u.business.id }}
                                        </div>
                                    </div>
                                    <div v-else-if="u.is_super_admin" class="text-stone-400 text-[11px]">
                                        Platform HQ (Global Scope)
                                    </div>
                                    <div v-else class="text-stone-400 text-[11px]">
                                        Unassigned
                                    </div>
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                        :class="u.is_active !== false ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-stone-100 text-stone-600 border border-stone-200'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="u.is_active !== false ? 'bg-emerald-500' : 'bg-stone-400'"></span>
                                        {{ u.is_active !== false ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-stone-400 text-[11px]">
                                    {{ formatDate(u.created_at) }}
                                </td>
                                <td class="p-3.5 text-right">
                                    <button
                                        v-if="u.business"
                                        @click="inspectStore(u.business)"
                                        title="Impersonate and view this merchant store"
                                        class="px-2.5 py-1 text-[11px] font-semibold text-stone-800 bg-stone-100 hover:bg-stone-200 rounded-md transition-colors cursor-pointer border border-stone-300 inline-flex items-center gap-1"
                                    >
                                        <svg class="w-3 h-3 text-stone-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Inspect Store</span>
                                    </button>
                                    <span v-else class="text-stone-300 text-xs">-</span>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-stone-400">
                                    No registered users match your search filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Users Pagination -->
                <div v-if="users.links && users.links.length > 3" class="p-4 border-t border-[#e8e2d9] flex items-center justify-between">
                    <span class="text-xs text-stone-500">
                        Showing page {{ users.current_page }} of {{ users.last_page }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Link
                            v-for="(link, i) in users.links"
                            :key="i"
                            :href="link.url || '#'"
                            class="px-2.5 py-1 text-xs rounded border transition-colors"
                            :class="link.active ? 'bg-[#211812] text-white border-[#211812]' : (link.url ? 'bg-white text-stone-600 border-[#e8e2d9] hover:bg-stone-50' : 'text-stone-300 border-transparent cursor-not-allowed')"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>

            <!-- TAB 3: AI INFRASTRUCTURE & METERING -->
            <div v-if="activeTab === 'ai'" class="space-y-6">
                <!-- Master DeepSeek Wholesale Provider Card -->
                <div class="bg-white border border-[#e8e2d9] rounded-xl p-5 shadow-xs">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#e8e2d9]">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-xs font-bold text-[#211812] uppercase tracking-wider">
                                    Master Wholesale DeepSeek Account
                                </h3>
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                    :class="liveDeepseek?.is_available ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full" :class="liveDeepseek?.is_available ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                    {{ liveDeepseek?.is_available ? 'API Active & Operational' : 'Depleted' }}
                                </span>
                            </div>
                            <p class="text-xs text-stone-500 mt-1">
                                Verified live via official endpoint <code class="text-[11px] font-mono bg-stone-100 border border-stone-200 px-1 py-0.5 rounded">https://api.deepseek.com/user/balance</code>
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] font-semibold text-stone-400 uppercase tracking-wider block">Remaining Balance</span>
                            <span class="text-2xl font-black text-emerald-700 font-mono tabular-nums">
                                {{ liveDeepseek?.formatted || ('$' + Number(liveDeepseek?.total_balance || 0).toFixed(2) + ' USD') }}
                            </span>
                            <p class="text-[11px] text-stone-500 mt-0.5">
                                ~{{ formatCurrency((liveDeepseek?.total_balance || 0) * 1600) }} wholesale value
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 text-xs">
                        <div>
                            <span class="text-stone-400 block text-[11px]">Paid Cash Balance</span>
                            <span class="font-bold text-[#211812] font-mono">${{ Number(liveDeepseek?.topped_up_balance || 0).toFixed(2) }}</span>
                        </div>
                        <div>
                            <span class="text-stone-400 block text-[11px]">Granted Credit Balance</span>
                            <span class="font-bold text-[#211812] font-mono">${{ Number(liveDeepseek?.granted_balance || 0).toFixed(2) }}</span>
                        </div>
                        <div>
                            <span class="text-stone-400 block text-[11px]">Currency Unit</span>
                            <span class="font-bold text-[#211812] font-mono">{{ liveDeepseek?.currency || 'USD' }}</span>
                        </div>
                        <div>
                            <span class="text-stone-400 block text-[11px]">Status Check</span>
                            <span class="font-bold text-emerald-700">{{ liveDeepseek?.is_available ? 'Ready for requests' : 'Requires top-up' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Sub-metrics Grid (AI & Workers) -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <p class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Credits in Circulation</p>
                        <p class="text-2xl font-extrabold text-[#211812] font-mono tabular-nums mt-2">{{ stats.total_credits_in_circulation.toLocaleString() }}</p>
                        <p class="text-[11px] text-stone-500 mt-1">Sum of balances across all stores</p>
                    </div>

                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <p class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">AI Tokens Consumed</p>
                        <p class="text-2xl font-extrabold text-[#211812] font-mono tabular-nums mt-2">{{ stats.total_tokens_consumed.toLocaleString() }}</p>
                        <p class="text-[11px] text-stone-500 mt-1">Global prompt + completion tokens</p>
                    </div>

                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <p class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Provider Cost (Est.)</p>
                        <p class="text-2xl font-extrabold text-[#211812] font-mono tabular-nums mt-2">${{ stats.total_ai_cost_usd.toFixed(2) }}</p>
                        <p class="text-[11px] text-stone-500 mt-1">~{{ formatCurrency(stats.estimated_ai_cost_ngn) }} estimated cost</p>
                    </div>

                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <p class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Background Workers</p>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="text-2xl font-extrabold text-[#211812] font-mono">{{ stats.pending_jobs }}</span>
                            <span class="text-xs text-stone-400">queued</span>
                        </div>
                        <p class="text-[11px] mt-1" :class="stats.failed_jobs > 0 ? 'text-rose-600 font-semibold' : 'text-emerald-600 font-medium'">
                            {{ stats.failed_jobs }} failed jobs
                        </p>
                    </div>
                </div>
            </div>

            <!-- TAB 4: PLATFORM TRANSACTIONS -->
            <div v-if="activeTab === 'transactions'" class="bg-white border border-[#e8e2d9] rounded-xl p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-[#e8e2d9] mb-4">
                    <div>
                        <h3 class="text-xs font-bold text-[#211812] uppercase tracking-wider">Platform Transactions Audit</h3>
                        <p class="text-[11px] text-stone-500 mt-0.5">Real-time payment fulfillments and customer checkouts across merchant stores.</p>
                    </div>
                    <span class="text-xs font-mono text-stone-500">
                        {{ recentPayments.length }} Recent Records
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#faf8f5] text-stone-600 border-b border-[#e8e2d9] text-[11px]">
                            <tr>
                                <th class="p-3">Reference</th>
                                <th class="p-3">Store</th>
                                <th class="p-3">Customer</th>
                                <th class="p-3">Amount</th>
                                <th class="p-3">Gateway</th>
                                <th class="p-3">Date</th>
                                <th class="p-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0ece6]">
                            <tr v-for="p in recentPayments" :key="p.id" class="hover:bg-[#faf8f5]/50">
                                <td class="p-3 font-mono text-[11px] text-stone-600">{{ p.reference }}</td>
                                <td class="p-3 font-medium text-[#211812]">{{ p.business?.name || '-' }}</td>
                                <td class="p-3 text-stone-600">{{ p.customer?.name || p.customer_name || 'Customer' }}</td>
                                <td class="p-3 font-bold text-[#211812] font-mono tabular-nums">
                                    {{ p.currency || 'NGN' }} {{ Number(p.amount ?? (p.amount_kobo ? p.amount_kobo / 100 : 0)).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}
                                </td>
                                <td class="p-3 uppercase text-[10px] text-stone-500">{{ p.gateway }}</td>
                                <td class="p-3 text-stone-400 text-[11px]">{{ formatDate(p.created_at) }}</td>
                                <td class="p-3 text-right">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                        :class="p.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="p.status === 'completed' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                        {{ p.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="recentPayments.length === 0">
                                <td colspan="7" class="py-8 text-center text-stone-400">No payment activity recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- MODAL: ADJUST / GRANT AI CREDITS -->
        <div v-if="showCreditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <div class="bg-white rounded-xl max-w-md w-full border border-[#e8e2d9] shadow-xl p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#e8e2d9]">
                    <h3 class="text-sm font-bold text-[#211812]">Adjust AI Credit Balance</h3>
                    <button @click="showCreditModal = false" class="text-stone-400 hover:text-stone-600 cursor-pointer text-lg leading-none">&times;</button>
                </div>

                <div class="text-xs text-stone-600">
                    Modifying credit balance for <strong class="text-[#211812]">{{ creditBusiness?.name }}</strong>.
                    Current balance: <span class="font-bold text-[#211812] font-mono">{{ (creditBusiness?.ai_credits_balance || 0).toLocaleString() }} credits</span>.
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-stone-700 uppercase mb-1">
                            Credit Amount (Negative values to deduct)
                        </label>
                        <input
                            v-model="creditAmount"
                            type="number"
                            step="100"
                            placeholder="e.g. 500 or -200"
                            class="w-full text-xs rounded-lg border border-[#e8e2d9] p-2 text-[#211812] focus:outline-none focus:ring-1 focus:ring-[#7b5537] font-mono"
                        />
                        <div class="flex gap-2 mt-2">
                            <button @click="creditAmount = 500" class="px-2.5 py-1 text-[10px] font-medium rounded bg-stone-100 hover:bg-stone-200 cursor-pointer border border-stone-200">+500</button>
                            <button @click="creditAmount = 1000" class="px-2.5 py-1 text-[10px] font-medium rounded bg-stone-100 hover:bg-stone-200 cursor-pointer border border-stone-200">+1,000</button>
                            <button @click="creditAmount = 5000" class="px-2.5 py-1 text-[10px] font-medium rounded bg-stone-100 hover:bg-stone-200 cursor-pointer border border-stone-200">+5,000</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-stone-700 uppercase mb-1">
                            Adjustment Reason / Memo
                        </label>
                        <input
                            v-model="creditReason"
                            type="text"
                            placeholder="e.g. Support grant, monthly bonus, manual invoice reconciliation"
                            class="w-full text-xs rounded-lg border border-[#e8e2d9] p-2 text-[#211812] focus:outline-none focus:ring-1 focus:ring-[#7b5537]"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-[#e8e2d9]">
                    <button
                        @click="showCreditModal = false"
                        class="px-3 py-1.5 text-xs text-stone-600 hover:bg-stone-100 rounded-lg cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitCreditAdjustment"
                        :disabled="creditSubmitting || !creditAmount"
                        class="px-4 py-1.5 text-xs font-semibold text-white bg-[#211812] hover:bg-[#3b2c22] rounded-lg transition-colors cursor-pointer"
                    >
                        {{ creditSubmitting ? 'Saving...' : 'Save Adjustment' }}
                    </button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
