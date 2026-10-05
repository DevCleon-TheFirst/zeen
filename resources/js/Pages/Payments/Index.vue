<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    payments: Object,   // paginated
    stats: Object,
    gatewaySettings: {
        type: Object,
        default: () => ({
            active_gateway: 'test',
            paystack: {},
            monnify: {},
        }),
    },
});

const showSettingsModal = ref(false);
const copiedWebhook = ref(null);

const form = useForm({
    active_gateway: props.gatewaySettings?.active_gateway || 'test',
    paystack_public_key: props.gatewaySettings?.paystack?.public_key || '',
    paystack_secret_key: '',
    monnify_api_key: props.gatewaySettings?.monnify?.api_key || '',
    monnify_secret_key: '',
    monnify_contract_code: props.gatewaySettings?.monnify?.contract_code || '',
    monnify_mode: props.gatewaySettings?.monnify?.mode || 'test',
    owner_telegram_chat_id: '',
});

const saveGatewaySettings = () => {
    form.post(route('payments.gateway-settings'), {
        preserveScroll: true,
        onSuccess: () => {
            showSettingsModal.value = false;
        },
    });
};

const copyToClipboard = (text, id) => {
    navigator.clipboard.writeText(text);
    copiedWebhook.value = id;
    setTimeout(() => { copiedWebhook.value = null; }, 2000);
};

const statusClass = (status) => {
    return {
        completed: 'badge-success',
        pending:   'badge-warning',
        failed:    'badge-error',
        refunded:  'badge-neutral',
    }[status] ?? 'badge-neutral';
};

const statusLabel = (status) => {
    return { completed: 'Paid', pending: 'Pending', failed: 'Failed', refunded: 'Refunded' }[status] ?? status;
};

const channelIcon = (channel) => ({
    telegram:  '✈️',
    whatsapp:  '💬',
    messenger: '📱',
})[channel] ?? '🔗';

const formatAmount = (kobo, currency) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: currency || 'NGN' })
        .format(kobo / 100);
</script>

<template>
    <Head title="Payments &amp; Gateways" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-sm font-semibold text-[#241e19]">Payments &amp; Gateways</h2>
        </template>

        <div class="p-6 md:p-8 space-y-6 max-w-7xl mx-auto">

            <!-- Header with Gateway Switcher Status -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-xl font-semibold text-[#241e19] tracking-tight">Payments &amp; Checkout Gateways</h1>
                    <p class="text-xs text-stone-500 mt-0.5">Switch active payment providers and view live customer transactions.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        @click="showSettingsModal = true"
                        type="button"
                        class="px-3.5 py-1.5 rounded-md text-xs font-semibold bg-[#4a3324] hover:bg-[#3b2c22] text-white flex items-center gap-1.5 transition-colors shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>Configure Gateways</span>
                    </button>
                </div>
            </div>

            <!-- Active Gateway Badge Banner -->
            <div class="card p-4 bg-[#faf8f5] border border-[#e8e2d9] rounded-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-white text-xs"
                        :class="{
                            'bg-emerald-600': gatewaySettings.active_gateway === 'paystack',
                            'bg-blue-600': gatewaySettings.active_gateway === 'monnify',
                            'bg-amber-600': gatewaySettings.active_gateway === 'test',
                        }"
                    >
                        {{ (gatewaySettings.active_gateway || 'T')[0].toUpperCase() }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-[#241e19] capitalize">
                                Active Gateway: {{ gatewaySettings.active_gateway === 'test' ? 'Sandbox (Test Mode)' : gatewaySettings.active_gateway }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.2 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                ACTIVE
                            </span>
                        </div>
                        <p class="text-[11px] text-stone-500 mt-0.5">
                            Customer checkout links in WhatsApp and Telegram are processed via this provider.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="showSettingsModal = true"
                        type="button"
                        class="text-xs font-medium text-[#7b5537] hover:underline"
                    >
                        Switch Gateway →
                    </button>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Total Revenue</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1">
                        {{ new Intl.NumberFormat('en-NG', { style: 'currency', currency: stats.currency }).format(stats.total_revenue) }}
                    </p>
                    <p class="text-[11px] text-stone-500 mt-1">Completed purchases</p>
                </div>
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Completed Transactions</p>
                    <p class="text-2xl font-bold text-[#241e19] mt-1">{{ stats.total_transactions }}</p>
                    <p class="text-[11px] text-emerald-600 font-medium mt-1">Successful receipts issued</p>
                </div>
                <div class="card p-4 bg-white border border-[#e8e2d9] rounded-lg">
                    <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Pending Orders</p>
                    <p class="text-2xl font-bold text-amber-700 mt-1">{{ stats.pending }}</p>
                    <p class="text-[11px] text-amber-600 font-medium mt-1">Awaiting customer payment</p>
                </div>
            </div>

            <!-- Table -->
            <div class="card bg-white border border-[#e8e2d9] rounded-lg overflow-hidden">
                <div class="p-4 border-b border-[#e8e2d9]">
                    <h3 class="text-xs font-semibold text-[#241e19] uppercase tracking-wider">Payment Ledger</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#faf8f5] text-stone-600 border-b border-[#e8e2d9] font-medium text-[11px]">
                            <tr>
                                <th class="p-3.5">Customer</th>
                                <th class="p-3.5">Amount</th>
                                <th class="p-3.5">Gateway</th>
                                <th class="p-3.5">Description</th>
                                <th class="p-3.5">Reference</th>
                                <th class="p-3.5">Status</th>
                                <th class="p-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0ece6]">
                            <tr v-if="!payments.data.length">
                                <td colspan="7" class="p-8 text-center text-stone-400">
                                    No payments recorded yet. They will appear here when a customer completes an order.
                                </td>
                            </tr>
                            <tr v-for="payment in payments.data" :key="payment.id" class="hover:bg-[#faf8f5]/50 transition-colors">
                                <td class="p-3.5">
                                    <div class="flex items-center gap-2">
                                        <span>{{ channelIcon(payment.conversation?.channel) }}</span>
                                        <div>
                                            <p class="font-medium text-[#241e19]">{{ payment.customer?.name ?? '—' }}</p>
                                            <p class="text-[11px] text-stone-400 font-mono">{{ payment.customer?.phone ?? payment.customer?.email ?? '' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3.5 font-bold text-[#241e19]">
                                    {{ formatAmount(payment.amount_kobo, payment.currency) }}
                                </td>
                                <td class="p-3.5 capitalize">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-stone-100 text-stone-700">
                                        {{ payment.gateway }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-stone-600 max-w-[200px] truncate">
                                    {{ payment.description || 'Order Checkout' }}
                                </td>
                                <td class="p-3.5 font-mono text-[11px] text-stone-500">
                                    {{ payment.reference }}
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                        :class="payment.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                    >
                                        {{ statusLabel(payment.status) }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right">
                                    <a
                                        v-if="payment.status === 'pending'"
                                        :href="route('payments.test-fulfill', { payment: payment.id })"
                                        class="text-xs text-amber-700 font-semibold hover:underline"
                                    >
                                        Simulate Paid ⚡
                                    </a>
                                    <span v-else class="text-[11px] text-stone-400">Confirmed</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- GATEWAY CONFIGURATION MODAL -->
            <div
                v-if="showSettingsModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
                @click.self="showSettingsModal = false"
            >
                <div class="bg-white rounded-xl shadow-xl border border-[#e8e2d9] w-full max-w-xl overflow-hidden max-h-[90vh] flex flex-col">
                    <div class="p-5 border-b border-[#e8e2d9] flex items-center justify-between bg-[#faf8f5]">
                        <div>
                            <h3 class="text-sm font-semibold text-[#241e19]">Configure Payment Gateways</h3>
                            <p class="text-xs text-stone-500 mt-0.5">Switch active gateway and configure API keys for checkouts.</p>
                        </div>
                        <button
                            @click="showSettingsModal = false"
                            class="text-stone-400 hover:text-stone-700 p-1"
                        >
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="saveGatewaySettings" class="p-5 overflow-y-auto space-y-5 text-xs flex-1">
                        <!-- Provider Selection Radio -->
                        <div>
                            <label class="block text-xs font-semibold text-[#241e19] mb-2 uppercase tracking-wider text-[11px]">Select Active Gateway</label>
                            <div class="grid grid-cols-3 gap-2.5">
                                <label
                                    class="border rounded-lg p-3 cursor-pointer flex flex-col justify-between transition-colors"
                                    :class="form.active_gateway === 'paystack' ? 'border-[#7b5537] bg-[#faf8f5] ring-1 ring-[#7b5537]' : 'border-[#e8e2d9] hover:bg-stone-50'"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-bold text-xs text-[#241e19]">Paystack</span>
                                        <input type="radio" value="paystack" v-model="form.active_gateway" class="text-[#7b5537] focus:ring-[#7b5537]" />
                                    </div>
                                    <span class="text-[10px] text-stone-500">Cards, Apple Pay, Bank Transfer</span>
                                </label>

                                <label
                                    class="border rounded-lg p-3 cursor-pointer flex flex-col justify-between transition-colors"
                                    :class="form.active_gateway === 'monnify' ? 'border-[#7b5537] bg-[#faf8f5] ring-1 ring-[#7b5537]' : 'border-[#e8e2d9] hover:bg-stone-50'"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-bold text-xs text-[#241e19]">Monnify</span>
                                        <input type="radio" value="monnify" v-model="form.active_gateway" class="text-[#7b5537] focus:ring-[#7b5537]" />
                                    </div>
                                    <span class="text-[10px] text-stone-500">Virtual Accounts &amp; Direct Transfer</span>
                                </label>

                                <label
                                    class="border rounded-lg p-3 cursor-pointer flex flex-col justify-between transition-colors"
                                    :class="form.active_gateway === 'test' ? 'border-[#7b5537] bg-[#faf8f5] ring-1 ring-[#7b5537]' : 'border-[#e8e2d9] hover:bg-stone-50'"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-bold text-xs text-[#241e19]">Sandbox Test</span>
                                        <input type="radio" value="test" v-model="form.active_gateway" class="text-[#7b5537] focus:ring-[#7b5537]" />
                                    </div>
                                    <span class="text-[10px] text-stone-500">Simulated free test checkout</span>
                                </label>
                            </div>
                        </div>

                        <!-- Paystack Fields -->
                        <div v-show="form.active_gateway === 'paystack'" class="p-3.5 bg-[#faf8f5] border border-[#e8e2d9] rounded-lg space-y-3">
                            <h4 class="font-semibold text-xs text-[#241e19]">Paystack Credentials</h4>
                            <div>
                                <label class="block text-[11px] font-medium text-stone-600 mb-1">Public Key</label>
                                <input
                                    v-model="form.paystack_public_key"
                                    type="text"
                                    placeholder="pk_live_... or pk_test_..."
                                    class="w-full text-xs rounded border border-[#e8e2d9] px-2.5 py-1.5 focus:ring-1 focus:ring-[#7b5537]"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-stone-600 mb-1">Secret Key</label>
                                <input
                                    v-model="form.paystack_secret_key"
                                    type="password"
                                    :placeholder="gatewaySettings.paystack?.has_secret ? '•••••••••••••••• (Leave blank to keep current)' : 'sk_live_... or sk_test_...'"
                                    class="w-full text-xs rounded border border-[#e8e2d9] px-2.5 py-1.5 focus:ring-1 focus:ring-[#7b5537]"
                                />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-stone-500 uppercase tracking-wider mb-1">Webhook URL</label>
                                <div class="flex items-center justify-between p-2 rounded bg-white border border-[#e8e2d9]">
                                    <span class="font-mono text-[11px] text-stone-600 truncate">{{ gatewaySettings.paystack?.webhook_url }}</span>
                                    <button
                                        type="button"
                                        @click="copyToClipboard(gatewaySettings.paystack?.webhook_url, 'paystack')"
                                        class="text-[11px] font-medium text-[#7b5537] hover:underline flex-shrink-0 ml-2"
                                    >
                                        {{ copiedWebhook === 'paystack' ? 'Copied!' : 'Copy' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Monnify Fields -->
                        <div v-show="form.active_gateway === 'monnify'" class="p-3.5 bg-[#faf8f5] border border-[#e8e2d9] rounded-lg space-y-3">
                            <h4 class="font-semibold text-xs text-[#241e19]">Monnify Credentials</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[11px] font-medium text-stone-600 mb-1">API Key</label>
                                    <input
                                        v-model="form.monnify_api_key"
                                        type="text"
                                        placeholder="MK_PROD_..."
                                        class="w-full text-xs rounded border border-[#e8e2d9] px-2.5 py-1.5 focus:ring-1 focus:ring-[#7b5537]"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-stone-600 mb-1">Contract Code</label>
                                    <input
                                        v-model="form.monnify_contract_code"
                                        type="text"
                                        placeholder="e.g. 1928374619"
                                        class="w-full text-xs rounded border border-[#e8e2d9] px-2.5 py-1.5 focus:ring-1 focus:ring-[#7b5537]"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-stone-600 mb-1">Secret Key</label>
                                <input
                                    v-model="form.monnify_secret_key"
                                    type="password"
                                    :placeholder="gatewaySettings.monnify?.has_secret ? '•••••••••••••••• (Leave blank to keep current)' : 'Secret key from Monnify dashboard'"
                                    class="w-full text-xs rounded border border-[#e8e2d9] px-2.5 py-1.5 focus:ring-1 focus:ring-[#7b5537]"
                                />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-stone-500 uppercase tracking-wider mb-1">Webhook URL</label>
                                <div class="flex items-center justify-between p-2 rounded bg-white border border-[#e8e2d9]">
                                    <span class="font-mono text-[11px] text-stone-600 truncate">{{ gatewaySettings.monnify?.webhook_url }}</span>
                                    <button
                                        type="button"
                                        @click="copyToClipboard(gatewaySettings.monnify?.webhook_url, 'monnify')"
                                        class="text-[11px] font-medium text-[#7b5537] hover:underline flex-shrink-0 ml-2"
                                    >
                                        {{ copiedWebhook === 'monnify' ? 'Copied!' : 'Copy' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Store Owner Telegram Notifications -->
                        <div class="p-3.5 bg-[#faf8f5] border border-[#e8e2d9] rounded-lg space-y-2">
                            <h4 class="font-semibold text-xs text-[#241e19]">Instant Order Telegram Alerts (Optional)</h4>
                            <p class="text-[11px] text-stone-500">Receive order summaries and dispatch alerts on your Telegram when payments clear.</p>
                            <div>
                                <label class="block text-[11px] font-medium text-stone-600 mb-1">Your Personal Telegram Chat ID</label>
                                <input
                                    v-model="form.owner_telegram_chat_id"
                                    type="text"
                                    placeholder="e.g. 192837461 (from @userinfobot)"
                                    class="w-full text-xs rounded border border-[#e8e2d9] px-2.5 py-1.5 focus:ring-1 focus:ring-[#7b5537]"
                                />
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="pt-3 border-t border-[#e8e2d9] flex items-center justify-end gap-2">
                            <button
                                type="button"
                                @click="showSettingsModal = false"
                                class="px-3 py-1.5 text-xs text-stone-600 hover:text-stone-900"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-1.5 rounded-md text-xs font-semibold bg-[#4a3324] hover:bg-[#3b2c22] text-white transition-colors"
                            >
                                Save Gateway Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
