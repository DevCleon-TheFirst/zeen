<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters.search || '');
const activeStatus = ref(props.filters.status || 'all');
const updatingOrderId = ref(null);

const applyFilters = () => {
    router.get(route('orders.index'), {
        search: search.value || undefined,
        status: activeStatus.value !== 'all' ? activeStatus.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const filterStatus = (status) => {
    activeStatus.value = status;
    applyFilters();
};

const updateStatus = (order, newStatus) => {
    updatingOrderId.value = order.id;
    router.patch(route('orders.status', { order: order.id }), { status: newStatus }, {
        preserveScroll: true,
        onFinish: () => { updatingOrderId.value = null; },
    });
};

const formatCurrency = (val) => {
    return '₦' + Number(val || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Orders &amp; Tracking" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-sm font-semibold text-[#241e19]">Orders &amp; Tracking</h2>
        </template>

        <div class="p-6 md:p-8 space-y-6 max-w-7xl mx-auto">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-xl font-semibold text-[#241e19] tracking-tight">
                        Store Orders &amp; Package Dispatch
                    </h1>
                    <p class="text-xs text-stone-500 mt-1">
                        Track orders made through Telegram, WhatsApp, and online checkout with instant automated customer notifications.
                    </p>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Total Orders</p>
                    <p class="text-2xl font-bold text-[#241e19] mt-1">{{ stats.total_orders }}</p>
                    <p class="text-[11px] text-stone-500 mt-1">Confirmed checkouts</p>
                </div>
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Total Sales</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1">{{ formatCurrency(stats.total_sales) }}</p>
                    <p class="text-[11px] text-emerald-600 font-medium mt-1">Revenue from orders</p>
                </div>
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">To Dispatch</p>
                    <p class="text-2xl font-bold text-amber-700 mt-1">{{ stats.confirmed }}</p>
                    <p class="text-[11px] text-amber-600 font-medium mt-1">Paid, awaiting pickup</p>
                </div>
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Delivered</p>
                    <p class="text-2xl font-bold text-blue-700 mt-1">{{ stats.delivered }}</p>
                    <p class="text-[11px] text-blue-600 font-medium mt-1">Completed deliveries</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="inline-flex rounded-md border border-[#e8e2d9] bg-white p-0.5 text-xs w-full sm:w-auto overflow-x-auto">
                    <button
                        v-for="s in ['all', 'confirmed', 'processing', 'dispatched', 'delivered']"
                        :key="s"
                        @click="filterStatus(s)"
                        class="px-3 py-1.5 rounded text-xs font-medium capitalize transition-colors whitespace-nowrap"
                        :class="activeStatus === s ? 'bg-[#4a3324] text-white' : 'text-stone-600 hover:text-[#241e19]'"
                    >
                        {{ s }}
                    </button>
                </div>

                <div class="w-full sm:w-64">
                    <input
                        v-model="search"
                        @keyup.enter="applyFilters"
                        type="text"
                        placeholder="Search tracking, name, phone..."
                        class="w-full text-xs rounded-md border border-[#e8e2d9] px-3 py-1.5 focus:border-[#7b5537] focus:ring-1 focus:ring-[#7b5537]"
                    />
                </div>
            </div>

            <!-- Orders List -->
            <div class="card bg-white border border-[#e8e2d9] rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#faf8f5] text-stone-600 border-b border-[#e8e2d9] font-medium text-[11px]">
                            <tr>
                                <th class="p-3.5">Tracking / Date</th>
                                <th class="p-3.5">Customer &amp; Address</th>
                                <th class="p-3.5">Items &amp; Sizes</th>
                                <th class="p-3.5">Total Paid</th>
                                <th class="p-3.5">Delivery Status</th>
                                <th class="p-3.5 text-right">Fulfillment Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0ece6]">
                            <tr v-for="order in orders.data" :key="order.id" class="hover:bg-[#faf8f5]/50 transition-colors">
                                <!-- Tracking Code & Date -->
                                <td class="p-3.5">
                                    <div class="font-mono font-bold text-[#7b5537] text-xs">
                                        {{ order.tracking_code }}
                                    </div>
                                    <div class="text-[11px] text-stone-400 mt-0.5">
                                        {{ formatDate(order.created_at) }}
                                    </div>
                                </td>

                                <!-- Customer Details -->
                                <td class="p-3.5">
                                    <div class="font-semibold text-[#241e19]">{{ order.customer_name || 'Customer' }}</div>
                                    <div class="text-[11px] text-stone-500 font-mono">{{ order.customer_phone || '-' }}</div>
                                    <div v-if="order.shipping_address" class="text-[11px] text-stone-600 mt-1 max-w-xs truncate" :title="order.shipping_address">
                                        📍 {{ order.shipping_address }}
                                    </div>
                                </td>

                                <!-- Items & Sizes -->
                                <td class="p-3.5">
                                    <div class="space-y-1">
                                        <div v-for="item in order.items" :key="item.id" class="text-stone-700">
                                            <span class="font-medium">{{ item.quantity }}x {{ item.item_name }}</span>
                                            <span v-if="item.size" class="ml-1 px-1.5 py-0.2 rounded text-[10px] bg-stone-100 font-semibold text-stone-600">
                                                Size {{ item.size }}
                                            </span>
                                            <span v-if="item.color" class="ml-1 text-[11px] text-stone-500">
                                                ({{ item.color }})
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Total Paid -->
                                <td class="p-3.5">
                                    <div class="font-bold text-[#241e19]">
                                        {{ order.currency }} {{ Number(order.total_amount).toLocaleString() }}
                                    </div>
                                    <div class="text-[10px] text-emerald-600 font-medium">PAID ✅</div>
                                </td>

                                <!-- Delivery Status -->
                                <td class="p-3.5">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider"
                                        :class="{
                                            'bg-amber-100 text-amber-800': order.status === 'confirmed',
                                            'bg-blue-100 text-blue-800': order.status === 'processing',
                                            'bg-purple-100 text-purple-800': order.status === 'dispatched',
                                            'bg-emerald-100 text-emerald-800': order.status === 'delivered',
                                            'bg-stone-100 text-stone-600': order.status === 'cancelled',
                                        }"
                                    >
                                        {{ order.status }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="p-3.5 text-right">
                                    <select
                                        :value="order.status"
                                        @change="updateStatus(order, $event.target.value)"
                                        :disabled="updatingOrderId === order.id"
                                        class="text-xs rounded border border-[#e8e2d9] bg-white py-1 px-2 text-[#241e19] focus:ring-1 focus:ring-[#7b5537]"
                                    >
                                        <option value="confirmed">Confirmed</option>
                                        <option value="processing">Processing</option>
                                        <option value="dispatched">Dispatched 🚚</option>
                                        <option value="delivered">Delivered ✅</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </td>
                            </tr>
                            <tr v-if="!orders.data.length">
                                <td colspan="6" class="p-8 text-center text-stone-400">
                                    No orders found matching this filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
