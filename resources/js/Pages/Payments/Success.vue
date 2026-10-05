<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
    payment: {
        type: Object,
        required: true,
    },
    order: {
        type: Object,
        default: null,
    },
    business: {
        type: Object,
        default: () => ({ name: 'Store' }),
    },
    return_info: {
        type: Object,
        default: () => ({
            channel: 'whatsapp',
            channel_name: 'WhatsApp',
            return_url: '/',
            tracking_code: '',
        }),
    },
});

const countdown = ref(4);
const isPaused = ref(false);
const copiedField = ref(null);
let timer = null;

const channelColor = computed(() => {
    if (props.return_info?.channel === 'whatsapp') {
        return {
            bg: 'bg-[#25D366] hover:bg-[#1ebd5a]',
            text: 'text-white',
            ring: 'focus:ring-[#25D366]/40',
            border: 'border-[#25D366]',
            badge: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        };
    }
    if (props.return_info?.channel === 'telegram') {
        return {
            bg: 'bg-[#229ED9] hover:bg-[#1d8ec4]',
            text: 'text-white',
            ring: 'focus:ring-[#229ED9]/40',
            border: 'border-[#229ED9]',
            badge: 'bg-sky-50 text-sky-800 border-sky-200',
        };
    }
    return {
        bg: 'bg-[#4a3324] hover:bg-[#38261a]',
        text: 'text-white',
        ring: 'focus:ring-[#4a3324]/40',
        border: 'border-[#4a3324]',
        badge: 'bg-stone-50 text-stone-800 border-stone-200',
    };
});

const formatCurrency = (amount, currency = 'NGN') => {
    if (amount === undefined || amount === null) return '';
    const symbol = currency === 'NGN' ? '₦' : (currency + ' ');
    return `${symbol}${Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const redirectToDestination = () => {
    if (props.return_info?.return_url) {
        window.location.href = props.return_info.return_url;
    }
};

const togglePause = () => {
    isPaused.value = !isPaused.value;
};

const copyToClipboard = (text, fieldName) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copiedField.value = fieldName;
    setTimeout(() => {
        copiedField.value = null;
    }, 2000);
};

onMounted(() => {
    // Only auto-redirect if we have a valid return_url
    if (props.return_info?.return_url && props.return_info.return_url !== '/') {
        timer = setInterval(() => {
            if (!isPaused.value) {
                if (countdown.value > 1) {
                    countdown.value -= 1;
                } else {
                    countdown.value = 0;
                    clearInterval(timer);
                    redirectToDestination();
                }
            }
        }, 1000);
    }
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <div class="min-h-screen bg-[#faf8f5] text-[#241e19] flex flex-col justify-between font-sans selection:bg-emerald-500 selection:text-white antialiased">
        <Head :title="`Payment Successful — ${business?.name || 'Order Confirmed'}`" />

        <!-- Top Header Navigation -->
        <header class="w-full border-b border-[#e8e2d9] bg-white/80 backdrop-blur-md sticky top-0 z-30">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-full bg-[#4a3324] text-[#faf8f5] flex items-center justify-center font-bold text-sm tracking-wider shadow-sm">
                        {{ business?.name ? business.name.charAt(0).toUpperCase() : 'S' }}
                    </div>
                    <div>
                        <h1 class="font-semibold text-sm sm:text-base leading-tight text-[#241e19]">
                            {{ business?.name || 'Verified Merchant' }}
                        </h1>
                        <span class="text-[11px] text-[#241e19]/60 font-medium">Secured Checkout</span>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                        Paid & Verified
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Card Section -->
        <main class="flex-1 max-w-xl w-full mx-auto px-4 py-8 sm:py-12 flex flex-col justify-center">
            <div class="bg-white rounded-2xl border border-[#e8e2d9] shadow-xl shadow-stone-200/50 overflow-hidden">
                <!-- Success Hero Banner -->
                <div class="bg-gradient-to-b from-emerald-50 via-emerald-50/40 to-white pt-8 pb-6 px-6 text-center border-b border-emerald-100/60">
                    <div class="mx-auto w-16 h-16 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-lg shadow-emerald-500/25 ring-8 ring-emerald-100 mb-4 animate-bounce duration-1000">
                        <svg class="w-8 h-8 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#241e19]">
                        Payment Successful!
                    </h2>
                    <p class="mt-2 text-sm text-[#241e19]/70 max-w-sm mx-auto">
                        Thank you! We've received your payment and your receipt has been dispatched to your chat.
                    </p>

                    <!-- Amount Display -->
                    <div class="mt-4 inline-block px-4 py-2 bg-white rounded-xl border border-emerald-200/80 shadow-sm">
                        <span class="text-xs font-semibold text-emerald-700 uppercase tracking-wider block">Total Amount Paid</span>
                        <span class="text-2xl sm:text-3xl font-extrabold text-[#241e19]">
                            {{ formatCurrency(payment.amount, payment.currency) }}
                        </span>
                    </div>
                </div>

                <!-- Auto-Redirect Alert & Primary Action Button -->
                <div class="p-6 bg-[#fbf9f6] border-b border-[#e8e2d9]">
                    <!-- Countdown status -->
                    <div v-if="return_info?.return_url && return_info.return_url !== '/'" class="mb-4">
                        <div class="flex items-center justify-between text-xs text-[#241e19]/70 mb-1.5">
                            <div class="flex items-center space-x-1.5">
                                <span v-if="!isPaused" class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                                <span class="font-medium">
                                    {{ isPaused ? 'Auto-redirect paused' : `Returning to ${return_info.channel_name} in ${countdown}s...` }}
                                </span>
                            </div>
                            <button
                                type="button"
                                @click="togglePause"
                                class="text-xs font-semibold text-[#4a3324] hover:underline cursor-pointer"
                            >
                                {{ isPaused ? 'Resume timer' : 'Stay on page' }}
                            </button>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full bg-[#e8e2d9] rounded-full h-1.5 overflow-hidden">
                            <div
                                class="bg-emerald-500 h-1.5 transition-all duration-1000 ease-linear rounded-full"
                                :style="{ width: `${(countdown / 4) * 100}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- Giant CTA Button -->
                    <a
                        :href="return_info?.return_url || '#'"
                        class="w-full inline-flex items-center justify-center px-6 py-4 rounded-xl font-bold text-base shadow-lg transition-all duration-200 transform active:scale-[0.98] cursor-pointer"
                        :class="[channelColor.bg, channelColor.text, channelColor.ring]"
                    >
                        <!-- WhatsApp Icon -->
                        <svg v-if="return_info?.channel === 'whatsapp'" class="w-6 h-6 mr-2.5 fill-current" viewBox="0 0 24 24">
                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.585 1.77.893 2.796.893 3.183 0 5.77-2.587 5.77-5.766.001-3.187-2.575-5.78-5.77-5.78zm3.394 8.211c-.144.405-.838.774-1.17.825-.311.05-.712.072-2.312-.589-1.341-.554-2.197-1.921-2.264-2.01-.067-.09-1.008-1.34-1.008-2.556 0-1.216.634-1.813.86-2.06.226-.247.493-.309.658-.309.165 0 .33.002.474.009.153.007.359-.059.562.428.209.502.713 1.741.775 1.867.062.126.104.273.019.442-.083.168-.124.273-.248.419-.124.146-.262.327-.374.439-.124.124-.253.259-.109.506.144.247.64 1.057 1.374 1.711.944.841 1.74 1.101 1.987 1.225.247.124.391.109.536-.058.144-.167.618-.722.783-.97.165-.248.33-.206.556-.124.226.082 1.433.676 1.68.799.247.123.412.185.474.288.062.103.062.598-.082 1.003z"/>
                            <path d="M12 2C6.477 2 2 6.477 2 12c0 1.821.487 3.53 1.338 5.003L2 22l5.122-1.334A9.957 9.957 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18.062c-1.614 0-3.136-.452-4.436-1.238l-.318-.189-3.29.858.878-3.208-.207-.33A7.994 7.994 0 0 1 4 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8.062-8 8.062z"/>
                        </svg>

                        <!-- Telegram Icon -->
                        <svg v-else-if="return_info?.channel === 'telegram'" class="w-6 h-6 mr-2.5 fill-current" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 0 0-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                        </svg>

                        <!-- Fallback / Store Icon -->
                        <svg v-else class="w-5 h-5 mr-2.5 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>

                        <span>Return to {{ return_info?.channel_name || 'Chat' }}</span>
                    </a>

                    <p class="mt-3 text-center text-xs text-[#241e19]/60">
                        Chat is restored! You can continue speaking directly with our assistant on WhatsApp.
                    </p>
                </div>

                <!-- Transaction Details & Receipt Summary -->
                <div class="p-6 space-y-5">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[#4a3324]/80">
                        Transaction Receipt
                    </h3>

                    <!-- Key-Value Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <!-- Reference -->
                        <div class="bg-[#faf8f5] p-3 rounded-lg border border-[#e8e2d9] flex flex-col justify-between">
                            <span class="text-[#241e19]/60 font-medium">Payment Reference</span>
                            <div class="flex items-center justify-between mt-1">
                                <span class="font-mono font-semibold text-[#241e19] truncate pr-2">
                                    {{ payment.reference }}
                                </span>
                                <button
                                    type="button"
                                    @click="copyToClipboard(payment.reference, 'ref')"
                                    class="text-[#4a3324] hover:text-black shrink-0 font-medium cursor-pointer"
                                >
                                    {{ copiedField === 'ref' ? '✓ Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>

                        <!-- Tracking Code / Order -->
                        <div class="bg-[#faf8f5] p-3 rounded-lg border border-[#e8e2d9] flex flex-col justify-between">
                            <span class="text-[#241e19]/60 font-medium">Order Tracking Code</span>
                            <div class="flex items-center justify-between mt-1">
                                <span class="font-mono font-semibold text-emerald-700 truncate pr-2">
                                    #{{ order?.tracking_code || return_info?.tracking_code || payment.reference }}
                                </span>
                                <button
                                    type="button"
                                    @click="copyToClipboard(order?.tracking_code || return_info?.tracking_code || payment.reference, 'track')"
                                    class="text-[#4a3324] hover:text-black shrink-0 font-medium cursor-pointer"
                                >
                                    {{ copiedField === 'track' ? '✓ Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Itemized Order Items (if applicable) -->
                    <div v-if="order && order.items && order.items.length > 0" class="pt-2">
                        <div class="text-xs font-semibold text-[#241e19]/80 mb-2">Purchased Items</div>
                        <div class="border border-[#e8e2d9] rounded-lg divide-y divide-[#e8e2d9] overflow-hidden text-xs">
                            <div
                                v-for="(item, idx) in order.items"
                                :key="idx"
                                class="p-3 bg-white flex items-center justify-between"
                            >
                                <div class="pr-2">
                                    <div class="font-medium text-[#241e19]">{{ item.name }}</div>
                                    <div v-if="item.size || item.color" class="text-[11px] text-[#241e19]/60">
                                        {{ [item.size, item.color].filter(Boolean).join(' • ') }}
                                    </div>
                                    <div class="text-[11px] text-[#241e19]/60">Qty: {{ item.quantity }}</div>
                                </div>
                                <div class="font-semibold text-[#241e19] shrink-0">
                                    {{ formatCurrency(item.total_price, order.currency) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Address (if applicable) -->
                    <div v-if="order?.shipping_address" class="bg-[#faf8f5] p-3 rounded-lg border border-[#e8e2d9] text-xs">
                        <span class="text-[#241e19]/60 font-medium block">Delivery Destination</span>
                        <div class="mt-1 font-medium text-[#241e19]">
                            {{ order.shipping_address }}
                        </div>
                        <div v-if="order.customer_name" class="mt-0.5 text-[11px] text-[#241e19]/70">
                            Recipient: {{ order.customer_name }}
                        </div>
                    </div>
                </div>

                <!-- Footer Reassurance Note -->
                <div class="px-6 py-4 bg-emerald-50/50 border-t border-emerald-100 flex items-start space-x-3 text-xs text-emerald-900">
                    <span class="text-base select-none">💬</span>
                    <div>
                        <span class="font-semibold">Chat Confirmation Sent</span>
                        <p class="text-emerald-800/80 mt-0.5">
                            A complete receipt with tracking instructions has also been delivered directly to your chat window.
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer Branding -->
        <footer class="w-full py-6 text-center text-xs text-[#241e19]/50">
            <p>Powered by {{ business?.name || 'Store Platform' }} • Omnichannel AI Commerce</p>
        </footer>
    </div>
</template>
