<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    health: Object,
    app_url: String,
    channel_webhooks: Array,
    queue_connection: String,
    inbound_automation_url: String,
});

const currentAppUrl = ref(props.app_url || '');
const savingAppUrl = ref(false);
const appUrlNotice = ref(null);

const registeringTelegram = ref(false);
const telegramNotice = ref(null);

const checkingTelegram = ref(false);
const telegramInfo = ref(null);

const refreshingHealth = ref(false);
const liveHealth = ref(props.health);

const runningMigration = ref(false);
const migrationOutput = ref(null);

const clearingCache = ref(false);
const cacheNotice = ref(null);

const copiedMap = ref({});

const copyText = (text, key) => {
    navigator.clipboard.writeText(text);
    copiedMap.value[key] = true;
    setTimeout(() => {
        copiedMap.value[key] = false;
    }, 2000);
};

const saveAppUrl = async () => {
    if (!currentAppUrl.value) return;
    savingAppUrl.value = true;
    appUrlNotice.value = null;
    try {
        const res = await axios.post(route('setup.app-url'), { url: currentAppUrl.value });
        appUrlNotice.value = { success: true, message: res.data.message };
        refreshHealth();
    } catch (err) {
        appUrlNotice.value = {
            success: false,
            message: err.response?.data?.message || 'Failed to update App URL.',
        };
    } finally {
        savingAppUrl.value = false;
    }
};

const registerTelegram = async () => {
    registeringTelegram.value = true;
    telegramNotice.value = null;
    try {
        const res = await axios.post(route('setup.telegram-webhook'), {
            app_url: currentAppUrl.value,
        });
        telegramNotice.value = { success: true, message: res.data.message };
        fetchTelegramInfo();
    } catch (err) {
        telegramNotice.value = {
            success: false,
            message: err.response?.data?.message || 'Webhook registration failed.',
        };
    } finally {
        registeringTelegram.value = false;
    }
};

const fetchTelegramInfo = async () => {
    checkingTelegram.value = true;
    try {
        const res = await axios.get(route('setup.telegram-info'));
        telegramInfo.value = res.data.info;
    } catch (err) {
        telegramInfo.value = { error: err.response?.data?.message || 'Could not fetch bot webhook info' };
    } finally {
        checkingTelegram.value = false;
    }
};

const refreshHealth = async () => {
    refreshingHealth.value = true;
    try {
        const res = await axios.get(route('setup.health'));
        liveHealth.value = res.data;
    } catch (err) {
        console.error('Health check failed', err);
    } finally {
        refreshingHealth.value = false;
    }
};

const runMigration = async () => {
    runningMigration.value = true;
    migrationOutput.value = null;
    try {
        const res = await axios.post(route('setup.migrate'));
        migrationOutput.value = res.data.output;
    } catch (err) {
        migrationOutput.value = err.response?.data?.message || 'Migration failed.';
    } finally {
        runningMigration.value = false;
    }
};

const clearAllCaches = async () => {
    clearingCache.value = true;
    cacheNotice.value = null;
    try {
        const res = await axios.post(route('setup.clear-caches'));
        cacheNotice.value = { success: true, message: res.data.message };
    } catch (err) {
        cacheNotice.value = { success: false, message: 'Failed to clear caches.' };
    } finally {
        clearingCache.value = false;
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="System Setup & Health" />

        <template #header>
            <div class="flex items-center gap-2 text-xs font-semibold text-[#291e17]">
                <span>Settings</span>
                <span class="text-stone-300">/</span>
                <span>System &amp; Webhooks</span>
            </div>
        </template>

        <div class="p-6 md:p-8 space-y-6 bg-[#faf8f5] min-h-screen text-[#291e17] max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-[#291e17] flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        System Setup, Webhooks &amp; Automation
                    </h1>
                    <p class="text-xs text-[#7b5537] mt-1">
                        Self-service command center. Configure webhooks, manage background processes, and check system health with one click without needing terminal commands.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="refreshHealth"
                        :disabled="refreshingHealth"
                        class="btn-secondary text-xs flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#e8e2d9] rounded-md shadow-sm hover:bg-[#f4efe8] transition-colors"
                    >
                        <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': refreshingHealth }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Refresh Health
                    </button>
                    <button
                        @click="clearAllCaches"
                        :disabled="clearingCache"
                        class="text-xs flex items-center gap-1.5 px-3 py-1.5 bg-[#4a3324] text-white rounded-md shadow-sm hover:bg-[#3b281c] transition-colors disabled:opacity-50"
                    >
                        <svg v-if="clearingCache" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Clear Caches
                    </button>
                </div>
            </div>

            <div v-if="cacheNotice" class="p-3 text-xs rounded-md border" :class="cacheNotice.success ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'">
                {{ cacheNotice.message }}
            </div>

            <!-- System Health Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Database -->
                <div class="bg-white p-4 rounded-xl border border-[#e8e2d9] shadow-xs flex items-start gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" :class="liveHealth?.database?.ok ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7M4 7c0-2 1.5-3 3.5-3h9c2 0 3.5 1 3.5 3M4 7c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3m-16 5c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Database</p>
                        <p class="text-sm font-semibold mt-0.5" :class="liveHealth?.database?.ok ? 'text-emerald-700' : 'text-rose-700'">
                            {{ liveHealth?.database?.ok ? 'Connected' : 'Offline' }}
                        </p>
                        <p class="text-[10px] text-stone-400 truncate mt-0.5">SQLite / MySQL Engine</p>
                    </div>
                </div>

                <!-- Background Queue Worker -->
                <div class="bg-white p-4 rounded-xl border border-[#e8e2d9] shadow-xs flex items-start gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" :class="liveHealth?.queue?.ok ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Queue Driver</p>
                        <p class="text-sm font-semibold text-stone-800 mt-0.5 uppercase">
                            {{ liveHealth?.queue?.driver || 'database' }}
                        </p>
                        <p class="text-[10px] text-stone-500 mt-0.5">
                            Pending: <span class="font-bold text-stone-700">{{ liveHealth?.queue?.pending_jobs ?? 0 }}</span> | Failed: <span class="font-bold text-rose-600">{{ liveHealth?.queue?.failed_jobs ?? 0 }}</span>
                        </p>
                    </div>
                </div>

                <!-- Environment & URL -->
                <div class="bg-white p-4 rounded-xl border border-[#e8e2d9] shadow-xs flex items-start gap-3">
                    <div class="w-9 h-9 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">App Environment</p>
                        <p class="text-sm font-semibold text-stone-800 mt-0.5 capitalize">
                            {{ liveHealth?.app_env }} (Debug: {{ liveHealth?.app_debug ? 'On' : 'Off' }})
                        </p>
                        <p class="text-[10px] text-stone-400 truncate mt-0.5">{{ liveHealth?.app_url }}</p>
                    </div>
                </div>

                <!-- Real-time WebSockets / Reverb -->
                <div class="bg-white p-4 rounded-xl border border-[#e8e2d9] shadow-xs flex items-start gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" :class="liveHealth?.reverb?.ok ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium text-stone-500 uppercase tracking-wider">Reverb WebSockets</p>
                        <p class="text-sm font-semibold mt-0.5" :class="liveHealth?.reverb?.ok ? 'text-emerald-700' : 'text-stone-600'">
                            {{ liveHealth?.reverb?.ok ? 'Online (8080)' : 'Inactive / Optional' }}
                        </p>
                        <p class="text-[10px] text-stone-400 truncate mt-0.5">Live inbox notifications</p>
                    </div>
                </div>
            </div>

            <!-- Public URL / Webhook Domain Setting -->
            <div class="bg-white rounded-xl border border-[#e8e2d9] p-5 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-[#291e17] flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#4a3324]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                            Application Public Base URL (Webhook Domain)
                        </h2>
                        <p class="text-xs text-stone-500 mt-0.5">
                            External services like Telegram, WhatsApp, and Stripe need a public HTTPS URL (like an Ngrok domain or production URL) to send webhook payloads to your app.
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <div class="relative flex-1">
                        <input
                            v-model="currentAppUrl"
                            type="url"
                            placeholder="https://your-domain.com or https://xyz.ngrok-free.app"
                            class="w-full text-xs font-mono rounded-lg border-[#e8e2d9] bg-[#faf8f5] px-3.5 py-2.5 focus:border-[#4a3324] focus:ring-[#4a3324] text-stone-800"
                        />
                    </div>
                    <button
                        @click="saveAppUrl"
                        :disabled="savingAppUrl || !currentAppUrl"
                        class="px-4 py-2.5 bg-[#4a3324] text-white text-xs font-medium rounded-lg hover:bg-[#3b281c] transition-colors flex items-center justify-center gap-2 disabled:opacity-50 flex-shrink-0"
                    >
                        <svg v-if="savingAppUrl" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Save App URL
                    </button>
                </div>

                <div v-if="appUrlNotice" class="mt-3 p-3 text-xs rounded-md border" :class="appUrlNotice.success ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'">
                    {{ appUrlNotice.message }}
                </div>
            </div>

            <!-- Channels & Webhook Auto-Registration Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-[#291e17]">Channel Webhook Connectors</h2>
                        <p class="text-xs text-stone-500">Live webhook endpoints configured for this business.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Telegram Card -->
                    <div class="bg-white rounded-xl border border-[#e8e2d9] p-5 shadow-xs flex flex-col justify-between space-y-4">
                        <div>
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-500 flex items-center justify-center font-bold">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-xs font-semibold text-stone-900">Telegram Bot</h3>
                                        <p class="text-[11px] text-stone-500">
                                            {{ channel_webhooks[0]?.bot_username ? '@' + channel_webhooks[0]?.bot_username : 'Bot Token Configured' }}
                                        </p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full" :class="channel_webhooks[0]?.connected ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-600'">
                                    {{ channel_webhooks[0]?.connected ? 'Connected' : 'Not Setup' }}
                                </span>
                            </div>

                            <div class="mt-4">
                                <label class="text-[10px] font-medium text-stone-500 uppercase tracking-wider block mb-1">Webhook URL</label>
                                <div class="flex items-center gap-1.5 bg-[#faf8f5] p-2 rounded-lg border border-[#e8e2d9] text-[11px] font-mono text-stone-700 truncate">
                                    <span class="truncate flex-1">{{ channel_webhooks[0]?.webhook_url }}</span>
                                    <button @click="copyText(channel_webhooks[0]?.webhook_url, 'tg_url')" class="text-stone-400 hover:text-stone-800 text-[10px] font-sans px-1.5 py-0.5 rounded bg-white border border-[#e8e2d9]">
                                        {{ copiedMap['tg_url'] ? 'Copied!' : 'Copy' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Live Bot Info if checked -->
                            <div v-if="telegramInfo" class="mt-3 p-2.5 bg-[#faf8f5] rounded-lg border border-[#e8e2d9] text-[11px] space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-stone-500">URL Set:</span>
                                    <span class="font-mono text-stone-800 truncate max-w-[160px]">{{ telegramInfo.url || 'None' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-stone-500">Pending Updates:</span>
                                    <span class="font-bold text-stone-800">{{ telegramInfo.pending_update_count ?? 0 }}</span>
                                </div>
                                <div v-if="telegramInfo.last_error_message" class="text-rose-600 text-[10px] mt-1">
                                    Error: {{ telegramInfo.last_error_message }}
                                </div>
                            </div>

                            <div v-if="telegramNotice" class="mt-3 p-2 text-xs rounded border" :class="telegramNotice.success ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'">
                                {{ telegramNotice.message }}
                            </div>
                        </div>

                        <div class="pt-3 border-t border-[#f0ede6] flex items-center gap-2">
                            <button
                                @click="registerTelegram"
                                :disabled="registeringTelegram || !channel_webhooks[0]?.connected"
                                class="flex-1 py-2 px-3 bg-sky-600 text-white rounded-lg text-xs font-medium hover:bg-sky-700 transition-colors flex items-center justify-center gap-1.5 disabled:opacity-40"
                            >
                                <svg v-if="registeringTelegram" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                {{ registeringTelegram ? 'Registering...' : '1-Click Register Webhook' }}
                            </button>
                            <button
                                @click="fetchTelegramInfo"
                                :disabled="checkingTelegram"
                                class="py-2 px-2.5 bg-white border border-[#e8e2d9] text-stone-700 rounded-lg text-xs hover:bg-stone-50 transition-colors"
                                title="Check Live Telegram Bot Webhook Status"
                            >
                                <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': checkingTelegram }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- WhatsApp Cloud Card -->
                    <div class="bg-white rounded-xl border border-[#e8e2d9] p-5 shadow-xs flex flex-col justify-between space-y-4">
                        <div>
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91C2.13 13.66 2.59 15.36 3.45 16.86L2.05 22L7.3 20.62C8.75 21.41 10.38 21.83 12.04 21.83C17.5 21.83 21.95 17.38 21.95 11.92C21.95 9.27 20.92 6.78 19.05 4.91C17.18 3.03 14.69 2 12.04 2M12.05 3.67C14.25 3.67 16.31 4.53 17.87 6.09C19.42 7.65 20.28 9.72 20.28 11.92C20.28 16.46 16.58 20.15 12.04 20.15C10.56 20.15 9.11 19.76 7.85 19L7.55 18.83L4.43 19.65L5.26 16.61L5.06 16.29C4.24 15 3.8 13.47 3.8 11.91C3.81 7.37 7.5 3.67 12.05 3.67Z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-xs font-semibold text-stone-900">WhatsApp Cloud</h3>
                                        <p class="text-[11px] text-stone-500">Meta Graph API</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full" :class="channel_webhooks[1]?.connected ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-600'">
                                    {{ channel_webhooks[1]?.connected ? 'Connected' : 'Not Setup' }}
                                </span>
                            </div>

                            <div class="mt-4 space-y-2.5">
                                <div>
                                    <label class="text-[10px] font-medium text-stone-500 uppercase tracking-wider block mb-1">Callback URL (Meta Console)</label>
                                    <div class="flex items-center gap-1.5 bg-[#faf8f5] p-2 rounded-lg border border-[#e8e2d9] text-[11px] font-mono text-stone-700 truncate">
                                        <span class="truncate flex-1">{{ channel_webhooks[1]?.webhook_url }}</span>
                                        <button @click="copyText(channel_webhooks[1]?.webhook_url, 'wa_url')" class="text-stone-400 hover:text-stone-800 text-[10px] font-sans px-1.5 py-0.5 rounded bg-white border border-[#e8e2d9]">
                                            {{ copiedMap['wa_url'] ? 'Copied!' : 'Copy' }}
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[10px] font-medium text-stone-500 uppercase tracking-wider block mb-1">Verify Token</label>
                                    <div class="flex items-center gap-1.5 bg-[#faf8f5] p-2 rounded-lg border border-[#e8e2d9] text-[11px] font-mono text-stone-700 truncate">
                                        <span class="truncate flex-1">{{ channel_webhooks[1]?.verify_token || 'wh_token_intern_system' }}</span>
                                        <button @click="copyText(channel_webhooks[1]?.verify_token || 'wh_token_intern_system', 'wa_tok')" class="text-stone-400 hover:text-stone-800 text-[10px] font-sans px-1.5 py-0.5 rounded bg-white border border-[#e8e2d9]">
                                            {{ copiedMap['wa_tok'] ? 'Copied!' : 'Copy' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-[#f0ede6]">
                            <a
                                href="https://developers.facebook.com/apps/"
                                target="_blank"
                                class="w-full py-2 px-3 bg-stone-100 text-stone-700 rounded-lg text-xs font-medium hover:bg-stone-200 transition-colors flex items-center justify-center gap-1.5"
                            >
                                Open Meta Dev Portal
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Inbound Automation Webhook Trigger (n8n/Zapier style) -->
                    <div class="bg-white rounded-xl border border-[#e8e2d9] p-5 shadow-xs flex flex-col justify-between space-y-4">
                        <div>
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-xs font-semibold text-stone-900">Automation Webhook Trigger</h3>
                                        <p class="text-[11px] text-stone-500">n8n / Zapier / Custom POST</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-purple-100 text-purple-700">
                                    Ready
                                </span>
                            </div>

                            <p class="text-xs text-stone-600 mt-3 leading-relaxed">
                                Any external system, CRM, or script can trigger your workflows by sending an HTTP POST request to your webhook URL.
                            </p>

                            <div class="mt-3">
                                <label class="text-[10px] font-medium text-stone-500 uppercase tracking-wider block mb-1">Webhook URL Format</label>
                                <div class="bg-[#faf8f5] p-2 rounded-lg border border-[#e8e2d9] text-[10px] font-mono text-stone-700 break-all">
                                    {{ currentAppUrl }}/api/webhooks/automation/{id}/{secret}
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-[#f0ede6]">
                            <a
                                :href="route('automations.index')"
                                class="w-full py-2 px-3 bg-[#4a3324] text-white rounded-lg text-xs font-medium hover:bg-[#3b281c] transition-colors flex items-center justify-center gap-1.5"
                            >
                                Open Visual Automation Builder
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Single-Command Developer Workflow Guide -->
            <div class="bg-[#291e17] text-stone-200 rounded-xl p-5 md:p-6 shadow-sm border border-[#3b2c22]">
                <div class="flex items-start gap-4">
                    <div class="w-9 h-9 rounded-lg bg-[#4a3324] text-[#d4b094] flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-white">How To Run Everything in 1 Command</h3>
                        <p class="text-xs text-stone-400 mt-1 leading-relaxed">
                            You never have to run 4 separate terminal windows or ask for commands! The project is configured with a unified runner. When starting your day, simply run:
                        </p>

                        <div class="mt-3 flex items-center gap-2 bg-[#1b1410] border border-[#4a3324] rounded-lg p-2.5 font-mono text-xs text-emerald-400">
                            <span class="text-stone-500">$</span>
                            <span class="flex-1">composer run dev</span>
                            <button @click="copyText('composer run dev', 'comp_dev')" class="text-[10px] text-stone-400 hover:text-white px-2 py-0.5 rounded bg-[#291e17] border border-[#3b2c22]">
                                {{ copiedMap['comp_dev'] ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>

                        <p class="text-[11px] text-stone-400 mt-2">
                            This single command automatically orchestrates:
                            <span class="text-white font-medium">Laravel Server</span>,
                            <span class="text-white font-medium">Vite Assets compiler</span>,
                            <span class="text-white font-medium">Queue Worker (for AI &amp; automations)</span>, and
                            <span class="text-white font-medium">Scheduler</span> in real-time.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Maintenance & Database Operations -->
            <div class="bg-white rounded-xl border border-[#e8e2d9] p-5 shadow-xs">
                <h2 class="text-sm font-semibold text-[#291e17]">Maintenance &amp; Quick Fix Actions</h2>
                <p class="text-xs text-stone-500 mt-0.5">Click any button below to execute standard maintenance routines without opening a terminal.</p>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button
                        @click="runMigration"
                        :disabled="runningMigration"
                        class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-800 text-xs font-medium rounded-lg transition-colors flex items-center gap-2 disabled:opacity-50"
                    >
                        <svg v-if="runningMigration" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Run Database Migrations
                    </button>

                    <button
                        @click="clearAllCaches"
                        :disabled="clearingCache"
                        class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-800 text-xs font-medium rounded-lg transition-colors flex items-center gap-2 disabled:opacity-50"
                    >
                        Clear Route, Config &amp; View Caches
                    </button>
                </div>

                <div v-if="migrationOutput" class="mt-3 p-3 bg-stone-900 text-stone-200 font-mono text-xs rounded-lg whitespace-pre-wrap">
                    {{ migrationOutput }}
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
