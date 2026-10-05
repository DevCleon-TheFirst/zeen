<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    items: Object,
    categories: Array,
    filters: Object,
    industry: String,
    stats: Object,
    day_of_week_trends: Object,
    ai_insights: Object,
});

const aiInsights = ref(props.ai_insights || null);
const isRefreshingInsights = ref(false);
const isAdvisorExpanded = ref(true);

// SVG area chart helpers for day-of-week sparkline
const CHART_W = 280;
const CHART_H = 56;
const CHART_PAD_X = 6;

const chartPoints = computed(() => {
    const breakdown = props.day_of_week_trends?.breakdown ?? [];
    if (!breakdown.length) return [];
    const counts = breakdown.map(d => d.count ?? 0);
    const maxVal = Math.max(...counts, 1);
    const step = (CHART_W - CHART_PAD_X * 2) / (counts.length - 1);
    return counts.map((v, i) => ({
        x: CHART_PAD_X + i * step,
        y: CHART_H - 6 - ((v / maxVal) * (CHART_H - 14)),
        count: v,
        day: breakdown[i]?.short ?? '',
        isPeak: breakdown[i]?.day === props.day_of_week_trends?.peak_day,
    }));
});

const sparklinePath = computed(() => {
    const pts = chartPoints.value;
    if (pts.length < 2) return '';
    // Catmull-Rom to bezier for smooth curve
    const d = pts.map((p, i) => {
        if (i === 0) return `M ${p.x},${p.y}`;
        const prev = pts[i - 1];
        const cp1x = prev.x + (p.x - prev.x) / 3;
        const cp2x = p.x - (p.x - prev.x) / 3;
        return `C ${cp1x},${prev.y} ${cp2x},${p.y} ${p.x},${p.y}`;
    });
    return d.join(' ');
});

const sparklineAreaPath = computed(() => {
    const pts = chartPoints.value;
    if (pts.length < 2) return '';
    const last = pts[pts.length - 1];
    const first = pts[0];
    return `${sparklinePath.value} L ${last.x},${CHART_H} L ${first.x},${CHART_H} Z`;
});

const refreshAiInsights = async () => {
    isRefreshingInsights.value = true;
    try {
        const response = await fetch(route('catalog.ai-insights'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        const data = await response.json();
        if (data.status === 'ok' && data.insights) {
            aiInsights.value = data.insights;
        }
    } catch (e) {
        console.error('Failed to refresh AI insights', e);
    } finally {
        isRefreshingInsights.value = false;
    }
};

const formatMoney = (amount, currency = 'NGN') => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: currency || 'NGN',
        maximumFractionDigits: 0,
    }).format(amount || 0);
};

const stockoutBadge = (item) => {
    if (item.stockout_risk === 'out_of_stock') {
        return { label: 'Depleted', class: 'bg-red-50 text-red-700 border-red-200' };
    }
    if (item.stockout_risk === 'critical') {
        return { label: `Critical: ~${item.predicted_stockout_days}d left`, class: 'bg-red-100 text-red-800 border-red-300 font-semibold' };
    }
    if (item.stockout_risk === 'warning') {
        return { label: item.predicted_stockout_days ? `Stockout in ~${item.predicted_stockout_days}d` : 'Low Stock', class: 'bg-amber-100 text-amber-900 border-amber-300' };
    }
    if (item.stockout_risk === 'healthy' && item.predicted_stockout_days) {
        return { label: `~${item.predicted_stockout_days}d supply`, class: 'bg-emerald-50 text-emerald-800 border-emerald-200' };
    }
    return null;
};

const showCreateModal = ref(false);
const editingItem = ref(null);
const searchInput = ref(props.filters.search || '');
const imagePreview = ref(null);
const uploadMode = ref('file'); // 'file' or 'url'

const itemForm = useForm({
    name: '',
    description: '',
    price: '',
    currency: 'NGN',
    category: '',
    availability_status: 'available',
    stock_quantity: '',
    sizes: '',
    image_url: '',
    image_file: null,
    is_active: true,
});

const openCreateModal = () => {
    editingItem.value = null;
    imagePreview.value = null;
    uploadMode.value = 'file';
    itemForm.reset();
    itemForm.currency = 'NGN';
    itemForm.availability_status = 'available';
    itemForm.stock_quantity = '';
    itemForm.sizes = '';
    itemForm.image_url = '';
    itemForm.image_file = null;
    itemForm.is_active = true;
    showCreateModal.value = true;
};

const openEditModal = (item) => {
    editingItem.value = item;
    imagePreview.value = item.image_url || null;
    uploadMode.value = item.image_url && !item.image_url.includes('/storage/catalog/') ? 'url' : 'file';
    itemForm.name = item.name;
    itemForm.description = item.description || '';
    itemForm.price = item.price !== null ? item.price : '';
    itemForm.currency = item.currency || 'NGN';
    itemForm.category = item.category || '';
    itemForm.availability_status = item.availability_status;
    itemForm.stock_quantity = item.stock_quantity !== null && item.stock_quantity !== undefined ? item.stock_quantity : '';
    itemForm.sizes = item.sizes_str || '';
    itemForm.image_url = item.image_url || '';
    itemForm.image_file = null;
    itemForm.is_active = item.is_active;
    showCreateModal.value = true;
};

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        itemForm.image_file = file;
        imagePreview.value = URL.createObjectURL(file);
    }
};

const handleFileDrop = (event) => {
    const file = event.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        itemForm.image_file = file;
        imagePreview.value = URL.createObjectURL(file);
    }
};

const removeImage = () => {
    itemForm.image_file = null;
    itemForm.image_url = '';
    imagePreview.value = null;
};

const saveItem = () => {
    if (editingItem.value) {
        // Inertia multipart post with _method PUT for file upload support
        router.post(route('catalog.update', editingItem.value.id), {
            _method: 'put',
            ...itemForm.data(),
        }, {
            preserveScroll: true,
            onSuccess: () => { showCreateModal.value = false; itemForm.reset(); },
        });
    } else {
        itemForm.post(route('catalog.store'), {
            preserveScroll: true,
            onSuccess: () => { showCreateModal.value = false; itemForm.reset(); },
        });
    }
};

const deleteItem = (item) => {
    if (confirm(`Are you sure you want to delete "${item.name}"?`)) {
        router.delete(route('catalog.destroy', item.id), { preserveScroll: true });
    }
};

const applySearch = () => {
    router.get(route('catalog.index'), {
        search: searchInput.value || null,
        category: props.filters.category === 'all' ? null : props.filters.category,
        status: props.filters.status === 'all' ? null : props.filters.status,
    }, { preserveState: true });
};

const filterCategory = (cat) => {
    router.get(route('catalog.index'), {
        category: cat === 'all' ? null : cat,
        status: props.filters.status === 'all' ? null : props.filters.status,
        search: searchInput.value || null,
    }, { preserveState: true });
};

const statusStyle = (s) => {
    if (s === 'available') return 'background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;';
    if (s === 'coming_soon') return 'background:#fffbeb;color:#92400e;border:1px solid #fde68a;';
    return 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';
};

const stockBadge = (item) => {
    if (item.stock_quantity === null || item.stock_quantity === undefined) {
        return { label: 'Always Available', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    }
    if (item.stock_quantity <= 0 || item.availability_status === 'unavailable') {
        return { label: 'Out of Stock', class: 'bg-red-50 text-red-700 border-red-200' };
    }
    if (item.stock_quantity <= 5) {
        return { label: `Only ${item.stock_quantity} left`, class: 'bg-amber-50 text-amber-800 border-amber-200' };
    }
    return { label: `${item.stock_quantity} in stock`, class: 'bg-emerald-50 text-emerald-800 border-emerald-200' };
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Catalog & Inventory" />

        <template #header>
            <div class="flex items-center gap-2 text-xs font-semibold text-[#291e17]">
                Catalog & Inventory
            </div>
        </template>

        <div class="p-6 md:p-8 space-y-6 bg-[#faf8f5] min-h-screen text-[#291e17]">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-lg font-semibold tracking-tight text-[#291e17]">
                        Catalog & Inventory Management
                    </h1>
                    <p class="text-xs text-[#7b5537] mt-0.5">
                        Upload product images, track stock levels, and set prices. The AI checks inventory before placing customer orders.
                    </p>
                </div>
                <button
                    @click="openCreateModal"
                    type="button"
                    class="btn-primary inline-flex items-center gap-2 self-start md:self-auto"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Catalog Item
                </button>
            </div>

            <!-- Quick KPI Metrics Bar -->
            <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Catalog Products -->
                <div class="card p-4 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-xs text-[#7b5537]">
                        <span class="font-medium">Total Products</span>
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-[#291e17]">{{ stats.total_items }}</span>
                        <span class="text-xs text-[#a89078]">({{ stats.active_items }} active)</span>
                    </div>
                    <p class="text-[11px] text-[#7b5537] mt-1">{{ stats.in_stock_count }} in stock & ready to order</p>
                </div>

                <!-- Units Sold -->
                <div class="card p-4 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-xs text-[#7b5537]">
                        <span class="font-medium">Units Sold</span>
                        <svg class="w-4 h-4 text-[#7b5537]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-[#291e17]">{{ stats.total_units_sold }}</span>
                        <span class="text-xs text-emerald-700 font-medium">Delivered & confirmed</span>
                    </div>
                    <p class="text-[11px] text-[#7b5537] mt-1">Across all customer orders</p>
                </div>

                <!-- Total Revenue -->
                <div class="card p-4 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-xs text-[#7b5537]">
                        <span class="font-medium">Catalog Sales</span>
                        <svg class="w-4 h-4 text-[#7b5537]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-xl font-bold text-[#291e17] tabular-nums">{{ formatMoney(stats.total_revenue, stats.currency) }}</span>
                    </div>
                    <p class="text-[11px] text-[#7b5537] mt-1">Gross merchandise volume</p>
                </div>

                <!-- Inventory Alerts -->
                <div class="card p-4 flex flex-col justify-between" :class="stats.low_stock_count > 0 || stats.out_of_stock_count > 0 ? 'border-amber-300 bg-amber-50/20' : ''">
                    <div class="flex items-center justify-between text-xs text-[#7b5537]">
                        <span class="font-medium">Stock Status</span>
                        <span v-if="stats.low_stock_count > 0 || stats.out_of_stock_count > 0" class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-900 border border-amber-300">
                            Attention
                        </span>
                        <span v-else class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-900 border border-emerald-200">
                            Healthy
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold" :class="stats.low_stock_count > 0 ? 'text-amber-800' : 'text-[#291e17]'">{{ stats.low_stock_count }}</span>
                        <span class="text-xs text-[#7b5537]">Low Stock</span>
                        <span class="text-xs text-[#d9c39f]">•</span>
                        <span class="text-sm font-semibold" :class="stats.out_of_stock_count > 0 ? 'text-red-700' : 'text-[#a89078]'">{{ stats.out_of_stock_count }} Out</span>
                    </div>
                    <p class="text-[11px] text-[#7b5537] mt-1">Forecasted depletion risks flagged below</p>
                </div>
            </div>

            <!-- AI Business Advisor -->
            <div v-if="aiInsights" class="overflow-hidden rounded-2xl border border-[#e0d8ce] bg-white shadow-sm">

                <!-- Top banner: AI's #1 message right now -->
                <div
                    class="relative flex items-start justify-between gap-4 px-6 py-5 overflow-hidden"
                    :class="aiInsights.urgency_level === 'critical'
                        ? 'bg-red-600'
                        : aiInsights.urgency_level === 'warning'
                        ? 'bg-amber-500'
                        : 'bg-[#291e17]'"
                >
                    <div class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white opacity-10"></div>
                    <div class="pointer-events-none absolute right-20 bottom-0 h-20 w-20 rounded-full bg-white opacity-5"></div>

                    <div class="flex items-start gap-3 min-w-0">
                        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/15">
                            <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-widest text-white/60">AI Advisor — What to focus on now</p>
                            <p class="text-sm font-semibold leading-snug text-white">{{ aiInsights.headline }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5">
                        <span class="hidden text-[10px] tabular-nums text-white/50 md:block">{{ aiInsights.generated_at }}</span>
                        <button
                            @click="refreshAiInsights"
                            :disabled="isRefreshingInsights"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-[11px] font-medium text-white transition-colors hover:bg-white/20 disabled:opacity-40"
                        >
                            <svg class="h-3 w-3" :class="{ 'animate-spin': isRefreshingInsights }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            {{ isRefreshingInsights ? 'Updating...' : 'Refresh AI' }}
                        </button>
                        <button
                            @click="isAdvisorExpanded = !isAdvisorExpanded"
                            type="button"
                            class="rounded-lg p-1.5 text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                        >
                            <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': !isAdvisorExpanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- 3 plain-English insight cards -->
                <div v-show="isAdvisorExpanded" class="grid grid-cols-1 divide-y divide-[#f0ebe4] sm:grid-cols-3 sm:divide-x sm:divide-y-0">

                    <!-- Card 1: Best day to sell + area chart -->
                    <div class="flex flex-col gap-4 p-5">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg border border-amber-100 bg-amber-50">
                                <svg class="h-3.5 w-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span class="text-[11px] font-semibold uppercase tracking-widest text-[#a89078]">Best Day to Sell</span>
                        </div>

                        <div>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold tracking-tight text-[#291e17]">{{ day_of_week_trends?.peak_day || 'N/A' }}</span>
                                <span v-if="day_of_week_trends?.peak_percentage > 0" class="text-sm text-[#a89078]">{{ day_of_week_trends.peak_percentage }}% of orders</span>
                            </div>
                            <p class="mt-1 text-xs leading-relaxed text-[#5a4030]">
                                {{ aiInsights.signals?.[0]?.action || aiInsights.peak_day_insight }}
                            </p>
                        </div>

                        <!-- SVG smooth area chart -->
                        <div class="mt-1">
                            <p class="mb-2 text-[10px] font-medium uppercase tracking-wide text-[#b8a898]">Orders by day of the week</p>
                            <div v-if="chartPoints.length >= 2">
                                <svg :viewBox="`0 0 ${CHART_W} 72`" class="w-full" style="height: 72px;" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="wkGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#c97a20" stop-opacity="0.25"/>
                                            <stop offset="100%" stop-color="#c97a20" stop-opacity="0"/>
                                        </linearGradient>
                                    </defs>
                                    <path :d="sparklineAreaPath" fill="url(#wkGrad)" />
                                    <path :d="sparklinePath" fill="none" stroke="#c97a20" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <g v-for="pt in chartPoints" :key="pt.day">
                                        <circle :cx="pt.x" :cy="pt.y" :r="pt.isPeak ? 4.5 : 2.5"
                                            :fill="pt.isPeak ? '#92400e' : '#d4b896'"
                                            :stroke="pt.isPeak ? '#fff' : 'none'" stroke-width="2"/>
                                        <g v-if="pt.isPeak">
                                            <rect :x="pt.x - 18" :y="pt.y - 24" width="36" height="15" rx="4" fill="#92400e"/>
                                            <text :x="pt.x" :y="pt.y - 13" text-anchor="middle" font-size="8.5" fill="white" font-weight="700" font-family="sans-serif">Best day</text>
                                        </g>
                                        <text :x="pt.x" y="70" text-anchor="middle" font-size="8"
                                            :fill="pt.isPeak ? '#92400e' : '#b8a898'"
                                            :font-weight="pt.isPeak ? '700' : '400'"
                                            font-family="sans-serif">{{ pt.day }}</text>
                                        <text v-if="pt.count > 0 && !pt.isPeak" :x="pt.x" :y="pt.y - 7" text-anchor="middle" font-size="8" fill="#a89078" font-family="sans-serif">{{ pt.count }}</text>
                                    </g>
                                </svg>
                            </div>
                            <div v-else class="flex h-14 items-center justify-center rounded-xl bg-[#faf8f5] text-xs text-[#a89078]">
                                No orders yet — your chart will appear here
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Stock situation in plain English -->
                    <div class="flex flex-col gap-4 p-5">
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-lg border"
                                :class="aiInsights.urgency_level === 'critical' ? 'border-red-100 bg-red-50' : aiInsights.urgency_level === 'warning' ? 'border-amber-100 bg-amber-50' : 'border-emerald-100 bg-emerald-50'"
                            >
                                <svg class="h-3.5 w-3.5" :class="aiInsights.urgency_level === 'critical' ? 'text-red-600' : aiInsights.urgency_level === 'warning' ? 'text-amber-600' : 'text-emerald-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                            <span class="text-[11px] font-semibold uppercase tracking-widest text-[#a89078]">Your Stock Right Now</span>
                        </div>

                        <div>
                            <span
                                class="text-2xl font-bold tracking-tight"
                                :class="aiInsights.urgency_level === 'critical' ? 'text-red-600' : aiInsights.urgency_level === 'warning' ? 'text-amber-600' : 'text-emerald-600'"
                            >
                                {{ aiInsights.urgency_level === 'critical' ? 'Restock Now' : aiInsights.urgency_level === 'warning' ? 'Running Low' : 'All Good' }}
                            </span>
                            <p class="mt-1 text-xs leading-relaxed text-[#5a4030]">
                                {{ aiInsights.signals?.[1]?.action || aiInsights.restock_advice }}
                            </p>
                        </div>

                        <div class="mt-auto space-y-3">
                            <!-- Ready to sell -->
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-[#7b5537]">Ready to sell</span>
                                    <span class="font-bold text-[#291e17]">{{ stats?.in_stock_count ?? 0 }} products</span>
                                </div>
                                <div class="h-2.5 w-full overflow-hidden rounded-full bg-[#e8e2d9]">
                                    <div class="h-full rounded-full bg-emerald-500 transition-all duration-700"
                                        :style="{ width: stats?.total_items ? `${Math.round(((stats.in_stock_count ?? 0) / stats.total_items) * 100)}%` : '100%' }"></div>
                                </div>
                            </div>
                            <!-- Running low -->
                            <div v-if="stats?.low_stock_count > 0">
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-amber-700">Running low — order more soon</span>
                                    <span class="font-bold text-amber-700">{{ stats.low_stock_count }}</span>
                                </div>
                                <div class="h-2.5 w-full overflow-hidden rounded-full bg-[#e8e2d9]">
                                    <div class="h-full rounded-full bg-amber-400 transition-all duration-700"
                                        :style="{ width: `${Math.round((stats.low_stock_count / stats.total_items) * 100)}%` }"></div>
                                </div>
                            </div>
                            <!-- Out of stock -->
                            <div v-if="stats?.out_of_stock_count > 0">
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-red-600">Out of stock — customers can't order</span>
                                    <span class="font-bold text-red-600">{{ stats.out_of_stock_count }}</span>
                                </div>
                                <div class="h-2.5 w-full overflow-hidden rounded-full bg-[#e8e2d9]">
                                    <div class="h-full rounded-full bg-red-500 transition-all duration-700"
                                        :style="{ width: `${Math.round((stats.out_of_stock_count / stats.total_items) * 100)}%` }"></div>
                                </div>
                            </div>
                            <p v-if="stats?.low_stock_count === 0 && stats?.out_of_stock_count === 0" class="text-xs font-medium text-emerald-700">
                                All {{ stats?.total_items }} products are available and ready for customers to order.
                            </p>
                        </div>
                    </div>

                    <!-- Card 3: Revenue + growth tip -->
                    <div class="flex flex-col gap-4 p-5">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg border border-[#291e17]/10 bg-[#291e17]/5">
                                <svg class="h-3.5 w-3.5 text-[#4a3324]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </div>
                            <span class="text-[11px] font-semibold uppercase tracking-widest text-[#a89078]">How to Earn More</span>
                        </div>

                        <div>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold tracking-tight text-[#291e17]">
                                    {{ stats?.total_units_sold > 0 ? formatMoney(stats.total_revenue / stats.total_units_sold, stats.currency) : formatMoney(stats?.total_revenue, stats?.currency) }}
                                </span>
                                <span class="text-xs text-[#a89078]">
                                    {{ stats?.total_units_sold > 0 ? 'per order on average' : 'total earned so far' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs leading-relaxed text-[#5a4030]">
                                {{ aiInsights.signals?.[2]?.action || aiInsights.merchandising_tip }}
                            </p>
                        </div>

                        <!-- Big readable revenue numbers -->
                        <div class="mt-auto grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-[#faf8f5] p-3 text-center">
                                <p class="text-xl font-bold tabular-nums text-[#291e17]">{{ stats?.total_units_sold ?? 0 }}</p>
                                <p class="mt-0.5 text-[10px] text-[#a89078]">Items sold</p>
                            </div>
                            <div class="rounded-xl bg-[#faf8f5] p-3 text-center">
                                <p class="text-xl font-bold tabular-nums text-[#291e17]">{{ formatMoney(stats?.total_revenue, stats?.currency) }}</p>
                                <p class="mt-0.5 text-[10px] text-[#a89078]">Total earned</p>
                            </div>
                        </div>

                        <p class="flex items-center gap-1 text-[10px] text-[#c4b8a8]">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Powered by {{ aiInsights.source === 'ai' ? 'live AI analysis' : 'your sales data' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Filters Row -->
            <div class="card p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <!-- Category filters -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0 flex-wrap">
                    <button
                        @click="filterCategory('all')"
                        class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors"
                        :class="filters.category === 'all'
                            ? 'bg-[#4a3324] text-[#faf8f5]'
                            : 'bg-white text-[#7b5537] border border-[#e8e2d9] hover:bg-[#f2ece4]'"
                    >
                        All Items
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        @click="filterCategory(cat)"
                        class="px-3 py-1.5 rounded-md text-xs font-medium capitalize transition-colors"
                        :class="filters.category === cat
                            ? 'bg-[#4a3324] text-[#faf8f5]'
                            : 'bg-white text-[#7b5537] border border-[#e8e2d9] hover:bg-[#f2ece4]'"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- Search -->
                <form @submit.prevent="applySearch" class="flex items-center gap-2">
                    <div class="relative">
                        <svg class="absolute left-2.5 top-2.5 w-3.5 h-3.5 text-[#a89078]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            type="text"
                            v-model="searchInput"
                            placeholder="Search items..."
                            class="text-xs rounded-md pl-8 pr-3 py-1.5 w-56 border border-[#e8e2d9] bg-white text-[#291e17] placeholder-[#a89078] focus:border-[#4a3324] focus:outline-none"
                        />
                    </div>
                    <button
                        type="submit"
                        class="btn-secondary py-1.5 px-3"
                    >
                        Search
                    </button>
                </form>
            </div>

            <!-- Empty State -->
            <div v-if="items.data.length === 0" class="card p-12 text-center border-dashed border-[#d9c39f]">
                <div class="w-10 h-10 rounded-md bg-[#f2ece4] border border-[#e8e2d9] text-[#7b5537] flex items-center justify-center mx-auto mb-3">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-sm text-[#291e17] mb-1">No catalog items found</h3>
                <p class="text-xs text-[#7b5537] max-w-sm mx-auto mb-4">
                    Add properties, products, or services so your AI can provide accurate details and stock to customers.
                </p>
                <button
                    @click="openCreateModal"
                    class="btn-primary inline-flex items-center gap-1.5"
                >
                    Add First Item
                </button>
            </div>

            <!-- Items Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="item in items.data"
                    :key="item.id"
                    class="card flex flex-col justify-between hover:border-[#b89f7e] transition-colors relative overflow-hidden"
                    :class="item.stockout_risk === 'out_of_stock' || item.stockout_risk === 'critical' ? 'border-red-300' : item.stockout_risk === 'warning' ? 'border-amber-300' : ''"
                >
                    <!-- Urgent restock alert strip — sits at very top of card, impossible to miss -->
                    <div
                        v-if="item.recommended_restock && item.stockout_risk !== 'healthy' && item.stockout_risk !== 'untracked' && item.stockout_risk !== null"
                        class="flex items-center justify-between gap-2 px-4 py-2 text-xs font-medium"
                        :class="item.stockout_risk === 'out_of_stock' || item.stockout_risk === 'critical'
                            ? 'bg-red-50 border-b border-red-200 text-red-800'
                            : 'bg-amber-50 border-b border-amber-200 text-amber-800'"
                    >
                        <div class="flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>{{ item.stockout_risk === 'out_of_stock' ? 'Out of stock — order more now' : item.stockout_risk === 'critical' ? 'Almost out — restock urgently' : 'Running low — order more soon' }}</span>
                        </div>
                        <span class="shrink-0 rounded bg-white/70 px-1.5 py-0.5 font-bold tabular-nums">+{{ item.recommended_restock }} units</span>
                    </div>
                    <div class="p-5">
                        <!-- Product Image if available -->
                        <div v-if="item.image_url" class="mb-3 rounded-md overflow-hidden bg-[#f2ece4] h-48 w-full border border-[#e8e2d9] relative group">
                            <img :src="item.image_url" :alt="item.name" class="w-full h-full object-cover" />
                        </div>
                        <div v-else class="mb-3 rounded-md overflow-hidden bg-[#f7f4ef] h-48 w-full border border-dashed border-[#d9c39f] flex flex-col items-center justify-center text-[#a89078]">
                            <svg class="w-8 h-8 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-[11px]">No Image Uploaded</span>
                        </div>

                        <div class="flex items-center justify-between mb-2">
                            <span class="badge-neutral text-[10px] uppercase font-mono tracking-wider">
                                {{ item.category || 'General' }}
                            </span>
                            <div class="flex items-center gap-1.5 flex-wrap justify-end">
                                <!-- Stock level badge -->
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium border" :class="stockBadge(item).class">
                                    {{ stockBadge(item).label }}
                                </span>
                                <!-- Stockout prediction badge -->
                                <span v-if="stockoutBadge(item)" class="px-2 py-0.5 rounded-full text-[10px] font-medium border" :class="stockoutBadge(item).class">
                                    {{ stockoutBadge(item).label }}
                                </span>
                            </div>
                        </div>

                        <!-- Sales summary -->
                        <div class="mb-2 flex items-center justify-between bg-[#f2ece4] rounded px-2.5 py-1.5 text-[11px] border border-[#e8e2d9]">
                            <span class="text-[#7b5537] font-medium">Sold so far</span>
                            <span class="font-bold text-[#291e17] font-mono">{{ item.units_sold }} {{ item.units_sold === 1 ? 'unit' : 'units' }} &nbsp;·&nbsp; {{ item.formatted_revenue }}</span>
                        </div>

                        <h3 class="font-semibold text-sm text-[#291e17] mb-1">{{ item.name }}</h3>
                        <p class="text-xs text-[#7b5537] line-clamp-2 mb-3 leading-relaxed">{{ item.description || 'No description provided.' }}</p>

                        <!-- Available sizes -->
                        <div v-if="item.sizes_str" class="flex items-center gap-1.5 flex-wrap mb-3">
                            <span class="text-[10px] text-[#7b5537] font-medium">Sizes:</span>
                            <span v-for="sz in item.sizes_str.split(',')" :key="sz" class="px-1.5 py-0.5 rounded bg-[#f2ece4] border border-[#e8e2d9] text-[10px] font-mono font-medium text-[#4a3324]">
                                {{ sz.trim() }}
                            </span>
                        </div>

                        <div class="text-base font-semibold text-[#291e17] tabular-nums">
                            {{ item.formatted_price }}
                        </div>
                    </div>

                    <!-- Footer: selling rate + actions -->
                    <div class="border-t border-[#e8e2d9] px-5 pb-4 pt-3">
                        <!-- Selling velocity + days of stock left -->
                        <div v-if="item.daily_velocity > 0 && item.predicted_stockout_days !== null" class="mb-3 flex items-center justify-between rounded-lg bg-[#faf8f5] border border-[#e8e2d9] px-3 py-2 text-[11px]">
                            <span class="text-[#7b5537]">Selling rate</span>
                            <span class="font-semibold text-[#291e17]">
                                ~{{ item.daily_velocity.toFixed(1) }} units/day &nbsp;&middot;&nbsp;
                                <span :class="item.stockout_risk === 'critical' || item.stockout_risk === 'out_of_stock' ? 'text-red-600 font-bold' : item.stockout_risk === 'warning' ? 'text-amber-700 font-bold' : 'text-emerald-700'">
                                    {{ item.predicted_stockout_days }}d of stock left
                                </span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-xs">
                            <button
                                @click="deleteItem(item)"
                                type="button"
                                class="text-[#991b1b] hover:text-[#7f1d1d] font-medium transition-colors"
                            >
                                Delete
                            </button>
                            <button
                                @click="openEditModal(item)"
                                type="button"
                                class="text-[#4a3324] hover:text-[#291e17] font-medium transition-colors"
                            >
                                Edit Item &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create/Edit Modal -->
            <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/40 backdrop-blur-[1px]">
                <div class="card max-w-lg w-full p-6 shadow-xl">
                    <!-- Modal header -->
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-[#e8e2d9]">
                        <h3 class="font-semibold text-sm text-[#291e17]">
                            {{ editingItem ? 'Edit Catalog Item & Stock' : 'Add New Catalog Item' }}
                        </h3>
                        <button @click="showCreateModal = false" class="w-6 h-6 rounded-md flex items-center justify-center text-sm text-[#7b5537] hover:bg-[#f2ece4]">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="saveItem" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-[#4a3324] mb-1">Item / Product Name *</label>
                            <input
                                type="text"
                                class="w-full rounded-md px-3 py-2 text-xs border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                v-model="itemForm.name"
                                placeholder="e.g. Cold-Pressed Black Seed Oil"
                                required
                            />
                        </div>

                        <!-- Local Image Upload & URL Picker -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-medium text-[#4a3324]">Product Picture</label>
                                <div class="flex items-center gap-2 text-[11px]">
                                    <button
                                        type="button"
                                        @click="uploadMode = 'file'"
                                        class="font-medium"
                                        :class="uploadMode === 'file' ? 'text-[#4a3324] underline' : 'text-[#7b5537] hover:text-[#4a3324]'"
                                    >
                                        📁 Upload from Computer
                                    </button>
                                    <span class="text-[#a89078]">|</span>
                                    <button
                                        type="button"
                                        @click="uploadMode = 'url'"
                                        class="font-medium"
                                        :class="uploadMode === 'url' ? 'text-[#4a3324] underline' : 'text-[#7b5537] hover:text-[#4a3324]'"
                                    >
                                        🔗 Paste URL
                                    </button>
                                </div>
                            </div>

                            <!-- File Dropzone -->
                            <div v-if="uploadMode === 'file'" class="space-y-2">
                                <div
                                    @dragover.prevent
                                    @drop.prevent="handleFileDrop"
                                    class="border-2 border-dashed border-[#d9c39f] hover:border-[#4a3324] bg-[#faf8f5] hover:bg-[#f5efea] rounded-lg p-4 text-center cursor-pointer transition-colors relative"
                                >
                                    <input
                                        type="file"
                                        accept="image/*"
                                        @change="handleFileSelect"
                                        class="absolute inset-0 opacity-0 w-full h-full cursor-pointer"
                                    />
                                    <div v-if="!imagePreview" class="space-y-1">
                                        <svg class="w-8 h-8 mx-auto text-[#7b5537]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <p class="text-xs font-medium text-[#291e17]">Click to upload or drag product photo here</p>
                                        <p class="text-[10px] text-[#7b5537]">PNG, JPG, WEBP up to 5MB</p>
                                    </div>

                                    <div v-else class="flex items-center justify-center gap-3">
                                        <img :src="imagePreview" class="h-16 w-16 object-cover rounded border border-[#e8e2d9]" />
                                        <div class="text-left text-xs">
                                            <p class="font-medium text-[#291e17]">Image Selected</p>
                                            <button
                                                type="button"
                                                @click.stop="removeImage"
                                                class="text-red-600 hover:text-red-800 text-[10px] font-semibold underline mt-1"
                                            >
                                                Remove Photo
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- URL Input Alternative -->
                            <div v-else>
                                <input
                                    type="url"
                                    class="w-full rounded-md px-3 py-2 text-xs border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                    v-model="itemForm.image_url"
                                    placeholder="https://images.unsplash.com/... or direct image link"
                                />
                            </div>
                            <p class="text-[10px] text-[#7b5537] mt-1">Photo sent to Telegram/WhatsApp customers when they ask for product pictures.</p>
                        </div>

                        <!-- Category & Status -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-[#4a3324] mb-1">Category</label>
                                <input
                                    type="text"
                                    class="w-full rounded-md px-3 py-2 text-xs border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                    v-model="itemForm.category"
                                    placeholder="e.g. Essential Oils, Teas"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#4a3324] mb-1">Availability Status</label>
                                <select
                                    v-model="itemForm.availability_status"
                                    class="w-full rounded-md px-3 py-2 text-xs border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                >
                                    <option value="available">Available (In Stock)</option>
                                    <option value="coming_soon">Coming Soon</option>
                                    <option value="unavailable">Unavailable (Sold Out)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Price, Currency & Stock Quantity -->
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-[#4a3324] mb-1">Price</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    class="w-full rounded-md px-3 py-2 text-xs font-mono border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                    v-model.number="itemForm.price"
                                    placeholder="e.g. 14000"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#4a3324] mb-1">Currency</label>
                                <input
                                    type="text"
                                    class="w-full rounded-md px-3 py-2 text-xs font-mono uppercase border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                    v-model="itemForm.currency"
                                    placeholder="NGN, USD"
                                    required
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#4a3324] mb-1">Stock Quantity</label>
                                <input
                                    type="number"
                                    min="0"
                                    class="w-full rounded-md px-3 py-2 text-xs font-mono border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                    v-model.number="itemForm.stock_quantity"
                                    placeholder="e.g. 50 (Blank = ∞)"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-[#4a3324] mb-1">Description & Details for AI Assistant</label>
                            <textarea
                                v-model="itemForm.description"
                                rows="3"
                                class="w-full text-xs rounded-md px-3 py-2 border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                placeholder="Include key benefits, ingredients, usage directions, or store notes..."
                            ></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-[#4a3324] mb-1">Available Sizes / Options (comma-separated)</label>
                            <input
                                type="text"
                                class="w-full rounded-md px-3 py-2 text-xs border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                v-model="itemForm.sizes"
                                placeholder="e.g. 100ml Bottle, 250ml Bottle or S, M, L, XL"
                            />
                            <p class="text-[10px] text-[#7b5537] mt-1">Options presented to customers during conversation before cart checkout.</p>
                        </div>

                        <label class="flex items-center gap-2 cursor-pointer pt-1">
                            <input
                                type="checkbox"
                                v-model="itemForm.is_active"
                                class="rounded h-4 w-4 accent-[#4a3324]"
                            />
                            <span class="text-xs font-medium text-[#291e17]">Active (AI can present and sell this item)</span>
                        </label>

                        <div class="pt-3 border-t border-[#e8e2d9] flex justify-end gap-2">
                            <button
                                type="button"
                                @click="showCreateModal = false"
                                class="btn-secondary"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="itemForm.processing"
                                class="btn-primary disabled:opacity-50"
                            >
                                {{ editingItem ? 'Update Item & Inventory' : 'Save Catalog Item' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
