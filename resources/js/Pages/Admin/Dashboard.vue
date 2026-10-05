<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
});

const togglingId = ref(null);
const updatingPlanId = ref(null);

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

const formatCurrency = (val) => {
    return '₦' + Number(val || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <Head title="Super Admin — Platform Command" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-300 uppercase tracking-wider">Super Admin</span>
                <h2 class="text-sm font-semibold text-[#241e19]">Platform Command Center</h2>
            </div>
        </template>

        <div class="p-6 md:p-8 space-y-6 max-w-7xl mx-auto">

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-xl font-semibold text-[#241e19] tracking-tight">
                        Platform Oversight &amp; Tenant Accounts
                    </h1>
                    <p class="text-xs text-stone-500 mt-1">
                        High-level monitoring of registered businesses, total platform volume, subscriptions, and system health.
                    </p>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Total Stores</p>
                    <p class="text-2xl font-bold text-[#241e19] mt-1">{{ stats.total_businesses }}</p>
                    <p class="text-[11px] text-emerald-600 font-medium mt-1">{{ stats.active_businesses }} Active</p>
                </div>
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Platform GMV</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1">{{ formatCurrency(stats.total_gmv) }}</p>
                    <p class="text-[11px] text-stone-500 mt-1">Total volume paid</p>
                </div>
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Total Orders</p>
                    <p class="text-2xl font-bold text-[#241e19] mt-1">{{ stats.total_orders }}</p>
                    <p class="text-[11px] text-stone-500 mt-1">Chat &amp; store sales</p>
                </div>
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Communications</p>
                    <p class="text-2xl font-bold text-[#241e19] mt-1">{{ stats.total_conversations }}</p>
                    <p class="text-[11px] text-stone-500 mt-1">{{ stats.total_messages }} total messages</p>
                </div>
            </div>

            <!-- Stores / Tenants Management Table -->
            <div class="card bg-white border border-[#e8e2d9] rounded-lg overflow-hidden">
                <div class="p-4 border-b border-[#e8e2d9] flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-semibold text-[#241e19] uppercase tracking-wider">Registered Businesses &amp; Tenants</h3>
                        <p class="text-[11px] text-stone-500 mt-0.5">Manage accounts, active statuses, and subscription tiers.</p>
                    </div>
                    <span class="text-xs font-medium text-stone-600 bg-stone-100 px-2 py-0.5 rounded">
                        {{ businesses.total }} Stores
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#faf8f5] text-stone-600 border-b border-[#e8e2d9] font-medium text-[11px]">
                            <tr>
                                <th class="p-3.5">Store / Business</th>
                                <th class="p-3.5">Industry</th>
                                <th class="p-3.5">Channels</th>
                                <th class="p-3.5">Team</th>
                                <th class="p-3.5">Plan Tier</th>
                                <th class="p-3.5">Status</th>
                                <th class="p-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0ece6]">
                            <tr v-for="b in businesses.data" :key="b.id" class="hover:bg-[#faf8f5]/50 transition-colors">
                                <td class="p-3.5">
                                    <div class="font-medium text-[#241e19]">{{ b.name }}</div>
                                    <div class="text-[11px] text-stone-400">Created {{ formatDate(b.created_at) }}</div>
                                </td>
                                <td class="p-3.5 capitalize text-stone-600">
                                    {{ (b.industry || 'General').replace('_', ' ') }}
                                </td>
                                <td class="p-3.5 text-stone-600">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-stone-100 text-stone-700">
                                        {{ b.channels_count }} connected
                                    </span>
                                </td>
                                <td class="p-3.5 text-stone-600">
                                    {{ b.users_count }} member(s)
                                </td>
                                <td class="p-3.5">
                                    <select
                                        :value="b.plan || 'free'"
                                        @change="changePlan(b, $event.target.value)"
                                        :disabled="updatingPlanId === b.id"
                                        class="text-xs rounded border border-[#e8e2d9] bg-white py-1 px-2 text-[#241e19] focus:ring-1 focus:ring-[#7b5537]"
                                    >
                                        <option value="free">Free Tier</option>
                                        <option value="starter">Starter</option>
                                        <option value="pro">Pro</option>
                                        <option value="enterprise">Enterprise</option>
                                    </select>
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                        :class="b.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                    >
                                        {{ b.is_active ? 'Active' : 'Suspended' }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right">
                                    <button
                                        @click="toggleBusiness(b)"
                                        :disabled="togglingId === b.id"
                                        type="button"
                                        class="text-xs font-medium px-2.5 py-1 rounded transition-colors"
                                        :class="b.is_active
                                            ? 'text-rose-700 hover:bg-rose-50 border border-rose-200'
                                            : 'text-emerald-700 hover:bg-emerald-50 border border-emerald-200'"
                                    >
                                        {{ b.is_active ? 'Suspend' : 'Activate' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Platform Transactions -->
            <div class="card bg-white border border-[#e8e2d9] rounded-lg overflow-hidden">
                <div class="p-4 border-b border-[#e8e2d9]">
                    <h3 class="text-xs font-semibold text-[#241e19] uppercase tracking-wider">Recent Platform Transactions</h3>
                    <p class="text-[11px] text-stone-500 mt-0.5">Live stream of payments across Paystack, Monnify, and test checkouts.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#faf8f5] text-stone-600 border-b border-[#e8e2d9] font-medium text-[11px]">
                            <tr>
                                <th class="p-3.5">Reference</th>
                                <th class="p-3.5">Business</th>
                                <th class="p-3.5">Customer</th>
                                <th class="p-3.5">Amount</th>
                                <th class="p-3.5">Gateway</th>
                                <th class="p-3.5">Status</th>
                                <th class="p-3.5 text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0ece6]">
                            <tr v-for="p in recentPayments" :key="p.id" class="hover:bg-[#faf8f5]/50">
                                <td class="p-3.5 font-mono text-[11px] text-[#241e19]">{{ p.reference }}</td>
                                <td class="p-3.5 font-medium text-stone-700">{{ p.business?.name || 'N/A' }}</td>
                                <td class="p-3.5 text-stone-600">{{ p.customer?.name || 'Guest' }}</td>
                                <td class="p-3.5 font-semibold text-[#241e19]">
                                    {{ p.currency }} {{ Number(p.amount_kobo / 100).toLocaleString() }}
                                </td>
                                <td class="p-3.5 capitalize">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-stone-100 text-stone-700 border border-stone-200">
                                        {{ p.gateway }}
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                        :class="p.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                    >
                                        {{ p.status }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right text-stone-500 text-[11px]">
                                    {{ formatDate(p.created_at) }}
                                </td>
                            </tr>
                            <tr v-if="!recentPayments.length">
                                <td colspan="7" class="p-6 text-center text-stone-400">
                                    No payments recorded yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
