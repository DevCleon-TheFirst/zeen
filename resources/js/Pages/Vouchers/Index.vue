<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    codes: Object,    // paginated DigitalCode records
    summary: Object,  // grouped by category & status
});

const showGenerateModal = ref(false);

const form = useForm({
    category: 'wifi_voucher',
    quantity: 10,
    prefix: '',
    valid_duration_minutes: 1440,
});

const categories = [
    { value: 'wifi_voucher',  label: 'WiFi Voucher',   emoji: '📶' },
    { value: 'ticket',        label: 'Event Ticket',    emoji: '🎟️' },
    { value: 'booking_pin',   label: 'Booking PIN',     emoji: '🔑' },
    { value: 'license_key',   label: 'License Key',     emoji: '💻' },
    { value: 'gift_card',     label: 'Gift Card',       emoji: '🎁' },
    { value: 'event_code',    label: 'Event Code',      emoji: '📅' },
];

const categoryLabel = (val) => categories.find(c => c.value === val)?.label ?? val;
const categoryEmoji = (val) => categories.find(c => c.value === val)?.emoji ?? '🔑';

const statusClass = (status) => ({
    available: 'badge-success',
    assigned:  'badge-info',
    redeemed:  'badge-neutral',
    expired:   'badge-error',
})[status] ?? 'badge-neutral';

const generate = () => {
    form.post(route('vouchers.batch-generate'), {
        onSuccess: () => { showGenerateModal.value = false; form.reset(); },
    });
};

const redeem = (code) => {
    router.patch(route('vouchers.redeem', code.id));
};

const validityLabel = (minutes) => {
    if (!minutes) return 'No expiry';
    if (minutes >= 1440 && minutes % 1440 === 0) {
        const d = minutes / 1440;
        return d === 1 ? '24 hours' : `${d} days`;
    }
    if (minutes >= 60 && minutes % 60 === 0) return `${minutes / 60}h`;
    return `${minutes}m`;
};
</script>

<template>
    <Head title="Vouchers & Codes" />
    <AuthenticatedLayout>
        <div class="p-6 space-y-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-stone-100">Vouchers &amp; Codes</h1>
                    <p class="text-sm text-stone-400 mt-0.5">WiFi vouchers, tickets, PINs, and access codes</p>
                </div>
                <button @click="showGenerateModal = true" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Generate Codes
                </button>
            </div>

            <!-- Summary Cards -->
            <div v-if="Object.keys(summary).length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <div v-for="(rows, category) in summary" :key="category" class="stat-card">
                    <p class="text-xl mb-1">{{ categoryEmoji(category) }}</p>
                    <p class="text-xs text-stone-400">{{ categoryLabel(category) }}</p>
                    <div class="mt-2 space-y-0.5">
                        <div v-for="row in rows" :key="row.status" class="flex justify-between text-xs">
                            <span class="text-stone-500 capitalize">{{ row.status }}</span>
                            <span class="font-semibold text-stone-200">{{ row.count }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Codes Table -->
            <div class="panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-stone-700/50">
                                <th class="th">Code</th>
                                <th class="th">Category</th>
                                <th class="th">Status</th>
                                <th class="th">Validity</th>
                                <th class="th">Customer</th>
                                <th class="th">Expires</th>
                                <th class="th">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-700/30">
                            <tr v-if="!codes.data.length">
                                <td colspan="7" class="td text-center text-stone-500 py-10">
                                    No codes yet. Click <strong>Generate Codes</strong> to create a batch.
                                </td>
                            </tr>
                            <tr v-for="code in codes.data" :key="code.id" class="hover:bg-white/[0.02] transition-colors">
                                <td class="td font-mono font-semibold text-stone-100 tracking-wider">{{ code.code }}</td>
                                <td class="td">
                                    <span class="text-sm">{{ categoryEmoji(code.category) }}</span>
                                    <span class="ml-1.5 text-stone-400">{{ categoryLabel(code.category) }}</span>
                                </td>
                                <td class="td">
                                    <span :class="['badge', statusClass(code.status)]">{{ code.status }}</span>
                                </td>
                                <td class="td text-stone-400">{{ validityLabel(code.valid_duration_minutes) }}</td>
                                <td class="td text-stone-400">{{ code.customer?.name ?? '—' }}</td>
                                <td class="td text-xs text-stone-500 whitespace-nowrap">
                                    {{ code.expires_at ? new Date(code.expires_at).toLocaleString() : '—' }}
                                </td>
                                <td class="td">
                                    <button
                                        v-if="code.status === 'assigned'"
                                        @click="redeem(code)"
                                        class="text-xs text-stone-400 hover:text-stone-200 underline-offset-2 hover:underline"
                                    >
                                        Mark redeemed
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="codes.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-stone-700/40 text-xs text-stone-400">
                    <span>Showing {{ codes.from }}–{{ codes.to }} of {{ codes.total }}</span>
                </div>
            </div>
        </div>

        <!-- Generate Modal -->
        <Teleport to="body">
            <div v-if="showGenerateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showGenerateModal = false"/>
                <div class="relative bg-stone-900 border border-stone-700/60 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-5">
                    <h2 class="text-lg font-semibold text-stone-100">Generate Access Codes</h2>

                    <!-- Category -->
                    <div class="field">
                        <label class="field-label">Type</label>
                        <select v-model="form.category" class="input">
                            <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                                {{ cat.emoji }} {{ cat.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div class="field">
                        <label class="field-label">Quantity</label>
                        <input v-model.number="form.quantity" type="number" min="1" max="500" class="input" placeholder="e.g. 50"/>
                    </div>

                    <!-- Prefix -->
                    <div class="field">
                        <label class="field-label">Prefix <span class="text-stone-500">(optional)</span></label>
                        <input v-model="form.prefix" type="text" maxlength="10" class="input" placeholder="e.g. WIFI, EVT"/>
                    </div>

                    <!-- Validity -->
                    <div class="field">
                        <label class="field-label">Validity (minutes)</label>
                        <input v-model.number="form.valid_duration_minutes" type="number" min="0" class="input" placeholder="1440 = 24 hours, 0 = no expiry"/>
                        <p class="text-xs text-stone-500 mt-1">1440 = 24 h, 10080 = 7 days, 0 = no expiry</p>
                    </div>

                    <div class="flex gap-3 justify-end pt-1">
                        <button @click="showGenerateModal = false" class="btn-ghost px-4 py-2">Cancel</button>
                        <button @click="generate" :disabled="form.processing" class="btn-primary">
                            {{ form.processing ? 'Generating…' : `Generate ${form.quantity} codes` }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.stat-card { @apply bg-stone-800/60 border border-stone-700/50 rounded-xl p-4; }
.panel { @apply bg-stone-800/60 border border-stone-700/50 rounded-xl; }
.th { @apply px-4 py-3 text-xs font-medium text-stone-400 uppercase tracking-wide; }
.td { @apply px-4 py-3 text-stone-300; }
.badge { @apply inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize; }
.badge-success { @apply bg-emerald-500/15 text-emerald-400; }
.badge-info    { @apply bg-sky-500/15 text-sky-400; }
.badge-neutral { @apply bg-stone-600/30 text-stone-400; }
.badge-error   { @apply bg-red-500/15 text-red-400; }
.btn-primary { @apply inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50; }
.btn-ghost { @apply rounded-md hover:bg-stone-700/50 text-stone-400 hover:text-stone-200 transition-colors text-sm; }
.field { @apply space-y-1.5; }
.field-label { @apply text-xs font-medium text-stone-400; }
.input { @apply w-full bg-stone-800 border border-stone-700/60 rounded-lg px-3 py-2 text-sm text-stone-200 focus:outline-none focus:border-amber-600/60; }
</style>
