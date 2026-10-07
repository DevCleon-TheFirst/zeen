<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    businesses: {
        type: Object,
        required: true,
    },
    recentPayments: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});

const search = ref(props.filters.search || '');
const togglingId = ref(null);
const updatingPlanId = ref(null);

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

const switchWorkspace = (business) => {
    router.post(route('businesses.switch', { business: business.id }));
};

let searchTimeout = null;
watch(search, (newVal) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.dashboard'), { search: newVal }, {
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
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2.5">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-500 border border-amber-500/20 uppercase tracking-widest">
                        Distributor HQ
                    </span>
                    <h2 class="text-sm font-semibold text-[#241e19]">Platform Command Center</h2>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium" :class="stats.gateway_healthy ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'">
                        <span class="w-1.5 h-1.5 rounded-full" :class="stats.gateway_healthy ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                        WhatsApp Gateway: {{ stats.gateway_healthy ? 'Online' : 'Standby / Offline' }}
                    </span>
                </div>
            </div>
        </template>

        <div class="p-6 md:p-8 space-y-7 max-w-7xl mx-auto">

            <!-- Title & Quick Insight Banner -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-xl font-bold text-[#241e19] tracking-tight">
                        SaaS Revenue, AI Metring &amp; Tenant Oversight
                    </h1>
                    <p class="text-xs text-stone-500 mt-1">
                        Live financial telemetry, background worker load, AI credit circulation, and merchant account control.
                    </p>
                </div>
            </div>

            <!-- SECTION 1: FINANCIAL & SAAS REVENUE METRICS -->
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-stone-400 mb-2.5">
                    1. Financial &amp; Subscription Velocity
                </p>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Estimated MRR</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800">Recurring</span>
                        </div>
                        <p class="text-2xl font-extrabold text-[#241e19] mt-2">{{ formatCurrency(stats.estimated_mrr) }}</p>
                        <p class="text-[11px] text-stone-500 mt-1">
                            {{ stats.plan_breakdown.starter }} Starter &middot; {{ stats.plan_breakdown.pro }} Pro &middot; {{ stats.plan_breakdown.enterprise }} Ent.
                        </p>
                    </div>

                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Platform GMV</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-stone-100 text-stone-600">Merchant Sales</span>
                        </div>
                        <p class="text-2xl font-extrabold text-emerald-700 mt-2">{{ formatCurrency(stats.total_gmv) }}</p>
                        <p class="text-[11px] text-stone-500 mt-1">{{ stats.total_orders }} orders processed</p>
                    </div>

                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Active Stores</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">{{ stats.active_businesses }} Live</span>
                        </div>
                        <p class="text-2xl font-extrabold text-[#241e19] mt-2">{{ stats.total_businesses }}</p>
                        <p class="text-[11px] text-stone-500 mt-1">{{ stats.total_users }} registered merchants/staff</p>
                    </div>

                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Total Interactions</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-800">Omnichannel</span>
                        </div>
                        <p class="text-2xl font-extrabold text-[#241e19] mt-2">{{ stats.total_conversations }}</p>
                        <p class="text-[11px] text-stone-500 mt-1">{{ stats.total_messages }} total inbound/outbound msgs</p>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: AI CREDITS, TOKENS & SYSTEM INFRASTRUCTURE -->
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-stone-400 mb-2.5">
                    2. AI Metering, Provider Cost &amp; Queue Infrastructure
                </p>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <p class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Credits in Circulation</p>
                        <p class="text-2xl font-extrabold text-amber-600 mt-2">{{ stats.total_credits_in_circulation.toLocaleString() }}</p>
                        <p class="text-[11px] text-stone-500 mt-1">Available across all tenant balances</p>
                    </div>

                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <p class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">AI Tokens Consumed</p>
                        <p class="text-2xl font-extrabold text-[#241e19] mt-2">{{ stats.total_tokens_consumed.toLocaleString() }}</p>
                        <p class="text-[11px] text-stone-500 mt-1">Global prompt + completion tokens</p>
                    </div>

                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <p class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Provider API Cost (Est.)</p>
                        <p class="text-2xl font-extrabold text-rose-600 mt-2">${{ stats.total_ai_cost_usd.toFixed(2) }}</p>
                        <p class="text-[11px] text-stone-500 mt-1">~{{ formatCurrency(stats.estimated_ai_cost_ngn) }} wholesale cost</p>
                    </div>

                    <div class="p-4 bg-white border border-[#e8e2d9] rounded-xl shadow-xs">
                        <p class="text-[11px] font-semibold text-stone-500 uppercase tracking-wider">Background Workers</p>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="text-2xl font-extrabold text-[#241e19]">{{ stats.pending_jobs }}</span>
                            <span class="text-xs text-stone-400">queued</span>
                        </div>
                        <p class="text-[11px] mt-1" :class="stats.failed_jobs > 0 ? 'text-rose-600 font-semibold' : 'text-emerald-600 font-medium'">
                            {{ stats.failed_jobs }} failed jobs
                        </p>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: STORES & TENANT ACCOUNTS MANAGEMENT -->
            <div class="bg-white border border-[#e8e2d9] rounded-xl overflow-hidden shadow-xs">
                <div class="p-4 sm:p-5 border-b border-[#e8e2d9] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-xs font-bold text-[#241e19] uppercase tracking-wider">Merchant Stores &amp; Credit Ledger</h3>
                        <p class="text-[11px] text-stone-500 mt-0.5">Adjust AI credits, update plan tiers, switch workspaces, or suspend accounts.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search store, industry, email..."
                                class="text-xs rounded-lg border border-[#e8e2d9] bg-[#faf8f5] py-1.5 pl-8 pr-3 text-[#241e19] focus:outline-none focus:ring-1 focus:ring-[#7b5537] w-64"
                            />
                            <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
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
                                    <div class="font-bold text-[#241e19]">{{ b.name }}</div>
                                    <div class="text-[11px] text-stone-400">Created {{ formatDate(b.created_at) }} &middot; {{ b.users_count }} staff</div>
                                </td>
                                <td class="p-3.5 capitalize text-stone-600">
                                    {{ (b.industry || 'general').replace('_', ' ') }}
                                </td>
                                <td class="p-3.5">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
                                            :class="(b.ai_credits_balance || 0) > 100 ? 'bg-amber-100 text-amber-900' : ((b.ai_credits_balance || 0) > 0 ? 'bg-orange-100 text-orange-900' : 'bg-rose-100 text-rose-800')"
                                        >
                                            {{ (b.ai_credits_balance || 0).toLocaleString() }} Credits
                                        </span>
                                        <button
                                            @click="openCreditModal(b)"
                                            title="Add / Adjust Credits"
                                            class="text-[11px] text-stone-400 hover:text-[#241e19] p-0.5 rounded hover:bg-stone-100 cursor-pointer"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
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
                                        class="text-xs rounded-lg border border-[#e8e2d9] bg-white py-1 px-2 text-[#241e19] focus:ring-1 focus:ring-[#7b5537] cursor-pointer"
                                    >
                                        <option value="free">Free Tier</option>
                                        <option value="starter">Starter (₦15k)</option>
                                        <option value="pro">Pro (₦35k)</option>
                                        <option value="enterprise">Enterprise (₦75k)</option>
                                    </select>
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="b.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                    >
                                        {{ b.is_active ? 'Active' : 'Suspended' }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right space-x-2">
                                    <!-- Switch to workspace (Impersonation) -->
                                    <button
                                        @click="switchWorkspace(b)"
                                        title="Switch active workspace to this store"
                                        class="px-2 py-1 text-[11px] font-medium text-stone-700 bg-stone-100 hover:bg-stone-200 rounded transition-colors cursor-pointer"
                                    >
                                        Switch To
                                    </button>

                                    <!-- Toggle Active / Suspended -->
                                    <button
                                        @click="toggleBusiness(b)"
                                        :disabled="togglingId === b.id"
                                        class="px-2 py-1 text-[11px] font-medium rounded transition-colors cursor-pointer"
                                        :class="b.is_active ? 'text-rose-700 bg-rose-50 hover:bg-rose-100' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'"
                                    >
                                        {{ b.is_active ? 'Suspend' : 'Activate' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="businesses.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-stone-400">
                                    No businesses match your search filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
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
                            :class="link.active ? 'bg-[#291e17] text-white border-[#291e17]' : (link.url ? 'bg-white text-stone-600 border-[#e8e2d9] hover:bg-stone-50' : 'text-stone-300 border-transparent cursor-not-allowed')"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>

            <!-- SECTION 4: RECENT PLATFORM PAYMENT TRANSACTIONS -->
            <div class="bg-white border border-[#e8e2d9] rounded-xl p-5 shadow-xs">
                <h3 class="text-xs font-bold text-[#241e19] uppercase tracking-wider mb-1">Recent Platform Transactions</h3>
                <p class="text-[11px] text-stone-500 mb-4">Latest orders and merchant payment fulfillments across gateways.</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-stone-500 border-b border-[#e8e2d9] text-[11px]">
                            <tr>
                                <th class="pb-2">Reference</th>
                                <th class="pb-2">Store</th>
                                <th class="pb-2">Customer</th>
                                <th class="pb-2">Amount</th>
                                <th class="pb-2">Gateway</th>
                                <th class="pb-2">Date</th>
                                <th class="pb-2 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0ece6]">
                            <tr v-for="p in recentPayments" :key="p.id" class="hover:bg-[#faf8f5]/50">
                                <td class="py-2.5 font-mono text-[11px] text-stone-600">{{ p.reference }}</td>
                                <td class="py-2.5 font-medium text-[#241e19]">{{ p.business?.name || '-' }}</td>
                                <td class="py-2.5 text-stone-600">{{ p.customer?.name || p.customer_name || 'Customer' }}</td>
                                <td class="py-2.5 font-bold text-emerald-700">{{ p.currency || 'NGN' }} {{ Number(p.amount ?? (p.amount_kobo ? p.amount_kobo / 100 : 0)).toLocaleString('en-NG', { minimumFractionDigits: 2 }) }}</td>
                                <td class="py-2.5 uppercase text-[10px] text-stone-500">{{ p.gateway }}</td>
                                <td class="py-2.5 text-stone-400 text-[11px]">{{ formatDate(p.created_at) }}</td>
                                <td class="py-2.5 text-right">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="p.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                        {{ p.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="recentPayments.length === 0">
                                <td colspan="7" class="py-6 text-center text-stone-400">No payment activity recorded yet.</td>
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
                    <h3 class="text-sm font-bold text-[#241e19]">Grant / Adjust AI Credits</h3>
                    <button @click="showCreditModal = false" class="text-stone-400 hover:text-stone-600 cursor-pointer">&times;</button>
                </div>

                <div class="text-xs text-stone-600">
                    Modifying credit balance for <strong class="text-[#241e19]">{{ creditBusiness?.name }}</strong>.
                    Current balance: <span class="font-bold text-amber-700">{{ creditBusiness?.ai_credits_balance || 0 }} credits</span>.
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-stone-700 uppercase mb-1">
                            Credit Amount (Use negative numbers to deduct)
                        </label>
                        <input
                            v-model="creditAmount"
                            type="number"
                            step="100"
                            placeholder="e.g. 500 or -200"
                            class="w-full text-xs rounded-lg border border-[#e8e2d9] p-2 text-[#241e19] focus:outline-none focus:ring-1 focus:ring-[#7b5537]"
                        />
                        <div class="flex gap-2 mt-2">
                            <button @click="creditAmount = 500" class="px-2 py-1 text-[10px] rounded bg-stone-100 hover:bg-stone-200 cursor-pointer">+500</button>
                            <button @click="creditAmount = 1000" class="px-2 py-1 text-[10px] rounded bg-stone-100 hover:bg-stone-200 cursor-pointer">+1,000</button>
                            <button @click="creditAmount = 5000" class="px-2 py-1 text-[10px] rounded bg-stone-100 hover:bg-stone-200 cursor-pointer">+5,000</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-stone-700 uppercase mb-1">
                            Reason / Description
                        </label>
                        <input
                            v-model="creditReason"
                            type="text"
                            placeholder="e.g. Promotional bonus, manual billing, support resolution"
                            class="w-full text-xs rounded-lg border border-[#e8e2d9] p-2 text-[#241e19] focus:outline-none focus:ring-1 focus:ring-[#7b5537]"
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
                        class="px-4 py-1.5 text-xs font-bold text-white bg-[#291e17] hover:bg-[#3b2c22] rounded-lg transition-colors cursor-pointer"
                    >
                        {{ creditSubmitting ? 'Saving...' : 'Apply Adjustment' }}
                    </button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
