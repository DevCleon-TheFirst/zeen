<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    channels: Array,
});

const activeModalChannel = ref(null);
const testingChannel = ref(null);
const testFeedback = ref({});
const copiedUrl = ref(null);

// WhatsApp Web QR & Pairing Modal
const qrModalOpen = ref(false);
const connectMode = ref('qr'); // 'qr' or 'code'
const qrStatus = ref('INITIALIZING');
const qrImage = ref(null);
const qrUser = ref(null);
let qrInterval = null;

const pairingPhone = ref('');
const pairingCode = ref(null);
const pairingLoading = ref(false);
const pairingError = ref(null);

const channelForm = useForm({
    is_active: false,
    credentials: {},
});

const openConfigModal = (channel) => {
    if (channel.type === 'whatsapp_web') {
        openQrModal();
        return;
    }
    activeModalChannel.value = channel;
    channelForm.is_active = channel.channel_data?.is_active ?? false;
    channelForm.credentials = {};
    channel.fields.forEach((f) => {
        channelForm.credentials[f.key] = channel.channel_data?.[f.key] || '';
    });
};

const openQrModal = () => {
    qrModalOpen.value = true;
    connectMode.value = 'qr';
    pairingCode.value = null;
    pairingError.value = null;
    fetchQrStatus();
    if (qrInterval) clearInterval(qrInterval);
    qrInterval = setInterval(fetchQrStatus, 3000);
};

const generatePairingCode = async () => {
    if (!pairingPhone.value) return;
    pairingLoading.value = true;
    pairingError.value = null;
    pairingCode.value = null;
    try {
        const res = await axios.post(route('channels.qr.pairing-code'), { phone: pairingPhone.value });
        if (res.data.success) {
            pairingCode.value = res.data.code;
        } else {
            pairingError.value = res.data.message;
        }
    } catch (err) {
        pairingError.value = err.response?.data?.message || 'Failed to generate code.';
    } finally {
        pairingLoading.value = false;
    }
};

const closeQrModal = () => {
    qrModalOpen.value = false;
    if (qrInterval) {
        clearInterval(qrInterval);
        qrInterval = null;
    }
};

const fetchQrStatus = async () => {
    try {
        const res = await axios.get(route('channels.qr.status'));
        if (res.data.success) {
            qrStatus.value = res.data.status;
            qrImage.value = res.data.qr;
            qrUser.value = res.data.user;

            if (res.data.status === 'CONNECTED') {
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        }
    } catch (e) {
        qrStatus.value = 'OFFLINE';
    }
};

const disconnectQr = async () => {
    try {
        await axios.post(route('channels.qr.disconnect'));
        qrStatus.value = 'DISCONNECTED';
        qrImage.value = null;
        qrUser.value = null;
        window.location.reload();
    } catch (e) {}
};

const closeModal = () => {
    activeModalChannel.value = null;
    channelForm.reset();
};

const saveChannelConfig = () => {
    if (!activeModalChannel.value) return;
    channelForm.post(route('channels.update', activeModalChannel.value.type), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const copyWebhook = (url) => {
    if (navigator?.clipboard?.writeText) {
        navigator.clipboard.writeText(url).catch(() => {});
    }
    copiedUrl.value = url;
    setTimeout(() => { copiedUrl.value = null; }, 2500);
};

const testConnection = async (type) => {
    testingChannel.value = type;
    testFeedback.value[type] = null;
    try {
        const res = await axios.post(route('channels.test', type));
        testFeedback.value[type] = { success: res.data.success, message: res.data.message };
    } catch (err) {
        testFeedback.value[type] = { success: false, message: err.response?.data?.message || 'Verification failed.' };
    } finally {
        testingChannel.value = null;
    }
};

// SVG icons for channels
const channelIcons = {
    whatsapp_web: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h6v6H3V3zm2 2v2h2V5H5zm8-2h6v6h-6V3zm2 2v2h2V5h-2zM3 13h6v6H3v-6zm2 2v2h2v-2H5zm13-2h3v2h-3v-2zm-3 0h2v3h-2v-3zm3 3h3v5h-2v-3h-1v-2zm-6-3h2v2h-2v-2zm0 3h3v2h-3v-2zm2 2h3v3h-3v-3zm-5-2h2v5h-2v-5z"/></svg>`,
    whatsapp: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>`,
    telegram: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.94z"/></svg>`,
    messenger: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.374 0 0 4.975 0 11.111c0 3.497 1.745 6.616 4.472 8.652V24l4.086-2.242c1.09.301 2.246.464 3.442.464 6.626 0 12-4.974 12-11.111C24 4.975 18.626 0 12 0zm1.193 14.963l-3.056-3.259-5.963 3.259L10.986 8.5l3.13 3.259L20 8.5l-6.807 6.463z"/></svg>`,
    email: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>`,
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Channels" />

        <template #header>
            <h2 class="text-sm font-semibold text-[#241e19]">Channels</h2>
        </template>

        <div class="p-6 md:p-8 space-y-6 max-w-7xl mx-auto">

            <!-- Page Header -->
            <div class="pb-5 border-b border-[#e8e2d9]">
                <h1 class="text-xl font-semibold text-[#241e19] tracking-tight">
                    Messaging Channels
                </h1>
                <p class="text-xs text-stone-500 mt-1">
                    Connect and configure WhatsApp Cloud API, Telegram Bots, and Meta Messenger integrations.
                </p>
            </div>

            <!-- Channel Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div
                    v-for="c in channels"
                    :key="c.type"
                    class="card flex flex-col justify-between overflow-hidden"
                >
                    <div class="p-5">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="w-8 h-8 rounded-md flex items-center justify-center flex-shrink-0 text-white"
                                    :style="`background:${c.color};`"
                                >
                                    <div class="w-4 h-4" v-html="channelIcons[c.type] || ''"></div>
                                </div>
                                <div>
                                    <h3 class="text-xs font-semibold text-[#241e19]">{{ c.name }}</h3>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span
                                            class="w-1.5 h-1.5 rounded-full"
                                            :class="c.channel_data?.is_active ? 'bg-emerald-500' : 'bg-stone-300'"
                                        ></span>
                                        <span class="text-[11px] text-stone-500">
                                            {{ c.channel_data?.is_active ? 'Connected' : 'Disconnected' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-stone-600 leading-relaxed mb-4">{{ c.description }}</p>

                        <!-- Webhook URL -->
                        <div class="rounded-md p-2.5 mb-4 bg-[#faf8f5] border border-[#e8e2d9]">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-stone-500">Webhook URL</span>
                                <button
                                    @click="copyWebhook(c.webhook_url)"
                                    type="button"
                                    class="text-[11px] font-medium text-[#7b5537] hover:underline"
                                >
                                    {{ copiedUrl === c.webhook_url ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                            <div class="font-mono text-[11px] text-stone-600 truncate select-all" :title="c.webhook_url">
                                {{ c.webhook_url }}
                            </div>
                        </div>

                        <!-- Test feedback -->
                        <div
                            v-if="testFeedback[c.type]"
                            class="mb-3 text-xs p-2.5 rounded-md"
                            :class="testFeedback[c.type].success
                                ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                : 'bg-red-50 text-red-800 border border-red-200'"
                        >
                            {{ testFeedback[c.type].message }}
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="px-5 pb-5 pt-3 border-t border-[#e8e2d9] flex items-center justify-between">
                        <button
                            @click="testConnection(c.type)"
                            :disabled="testingChannel === c.type || !c.channel_data"
                            type="button"
                            class="text-xs font-medium text-stone-600 hover:text-stone-900 disabled:opacity-40 flex items-center gap-1.5 transition-colors"
                        >
                            <svg v-if="testingChannel === c.type" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            {{ testingChannel === c.type ? 'Verifying...' : 'Ping Test' }}
                        </button>

                        <button
                            @click="openConfigModal(c)"
                            type="button"
                            class="btn-primary"
                        >
                            {{ c.channel_data?.has_token ? 'Configure' : 'Connect' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Config Modal -->
            <div v-if="activeModalChannel" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/40">
                <div class="card max-w-lg w-full p-6 shadow-lg bg-white">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-[#e8e2d9]">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="w-7 h-7 rounded flex items-center justify-center text-white"
                                :style="`background:${activeModalChannel.color};`"
                            >
                                <div class="w-3.5 h-3.5" v-html="channelIcons[activeModalChannel.type] || ''"></div>
                            </div>
                            <h3 class="font-semibold text-sm text-[#241e19]">
                                Configure {{ activeModalChannel.name }}
                            </h3>
                        </div>
                        <button @click="closeModal" class="text-stone-400 hover:text-stone-700 text-lg leading-none">✕</button>
                    </div>

                    <form @submit.prevent="saveChannelConfig" class="space-y-4">
                        <div v-for="field in activeModalChannel.fields" :key="field.key">
                            <label class="block text-xs font-medium text-[#241e19] mb-1">{{ field.label }}</label>
                            <input
                                :id="field.key"
                                :type="field.type"
                                class="w-full text-xs rounded-md border border-[#e8e2d9] bg-white text-[#241e19] font-mono focus:outline-none focus:border-[#7b5537] focus:ring-1 focus:ring-[#7b5537] p-2"
                                v-model="channelForm.credentials[field.key]"
                                :placeholder="field.placeholder"
                            />
                        </div>

                        <label class="flex items-center gap-2 cursor-pointer pt-1">
                            <input
                                id="channel_active"
                                type="checkbox"
                                v-model="channelForm.is_active"
                                class="rounded border-[#e8e2d9] text-[#4a3324] focus:ring-[#7b5537]"
                            />
                            <span class="text-xs text-[#241e19]">Enable this channel to receive incoming messages</span>
                        </label>

                        <div class="pt-4 border-t border-[#e8e2d9] flex justify-end gap-2">
                            <button
                                type="button"
                                @click="closeModal"
                                class="btn-secondary"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="channelForm.processing"
                                class="btn-primary"
                            >
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- WhatsApp Web QR & Pairing Modal -->
            <div v-if="qrModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                <div class="card max-w-md w-full p-6 shadow-xl bg-white rounded-xl">
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-[#e8e2d9]">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-[#128C7E] flex items-center justify-center text-white">
                                <div class="w-4 h-4" v-html="channelIcons.whatsapp_web"></div>
                            </div>
                            <div>
                                <h3 class="font-semibold text-sm text-[#241e19]">Link WhatsApp Account</h3>
                                <p class="text-[11px] text-stone-500">Connect via QR Code or 8-digit Pairing Code</p>
                            </div>
                        </div>
                        <button @click="closeQrModal" class="text-stone-400 hover:text-stone-700 text-xl font-bold">✕</button>
                    </div>

                    <!-- Mode Selector Tabs (if not connected) -->
                    <div v-if="qrStatus !== 'CONNECTED'" class="flex border-b border-stone-200 mb-4 text-xs font-medium">
                        <button
                            @click="connectMode = 'qr'"
                            type="button"
                            class="flex-1 py-2 text-center border-b-2 transition-colors"
                            :class="connectMode === 'qr' ? 'border-[#128C7E] text-[#128C7E] font-semibold' : 'border-transparent text-stone-500 hover:text-stone-800'"
                        >
                            📷 Scan QR Code
                        </button>
                        <button
                            @click="connectMode = 'code'"
                            type="button"
                            class="flex-1 py-2 text-center border-b-2 transition-colors"
                            :class="connectMode === 'code' ? 'border-[#128C7E] text-[#128C7E] font-semibold' : 'border-transparent text-stone-500 hover:text-stone-800'"
                        >
                            🔢 Phone Number Code
                        </button>
                    </div>

                    <!-- Status: CONNECTED -->
                    <div v-if="qrStatus === 'CONNECTED'" class="text-center py-6 space-y-3">
                        <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-2xl font-bold">
                            ✓
                        </div>
                        <h4 class="text-base font-semibold text-stone-900">WhatsApp Connected!</h4>
                        <p class="text-xs text-stone-500">
                            Linked phone number: <span class="font-mono font-bold text-stone-800">+{{ qrUser }}</span>
                        </p>
                        <div class="pt-4 flex justify-center gap-3">
                            <button @click="disconnectQr" type="button" class="btn-secondary text-red-600 border-red-200 hover:bg-red-50">
                                Disconnect Number
                            </button>
                            <button @click="closeQrModal" type="button" class="btn-primary">
                                Done
                            </button>
                        </div>
                    </div>

                    <!-- Mode: QR SCAN -->
                    <div v-else-if="connectMode === 'qr'">
                        <div v-if="qrStatus === 'SCAN_QR' && qrImage" class="text-center py-2 space-y-4">
                            <div class="p-3 bg-stone-50 rounded-xl border border-stone-200 inline-block shadow-inner">
                                <img :src="qrImage" alt="WhatsApp QR Code" class="w-52 h-52 mx-auto rounded-lg" />
                            </div>

                            <div class="text-left bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900 space-y-1">
                                <div class="font-semibold flex items-center gap-1.5">
                                    <span>📱</span> How to scan:
                                </div>
                                <ol class="list-decimal list-inside text-[11px] text-amber-800 space-y-0.5">
                                    <li>Open <strong>WhatsApp</strong> on phone</li>
                                    <li>Tap <strong>Settings / Menu ⚙️</strong> → <strong>Linked Devices</strong></li>
                                    <li>Tap <strong>Link a Device</strong> and point camera</li>
                                </ol>
                            </div>
                        </div>

                        <div v-else class="text-center py-10 space-y-3">
                            <div class="w-8 h-8 border-4 border-[#128C7E] border-t-transparent rounded-full animate-spin mx-auto"></div>
                            <p class="text-xs font-medium text-stone-600">Generating WhatsApp QR Code...</p>
                        </div>
                    </div>

                    <!-- Mode: PHONE NUMBER PAIRING CODE -->
                    <div v-else-if="connectMode === 'code'" class="space-y-4 py-2">
                        <div v-if="!pairingCode" class="space-y-3">
                            <p class="text-xs text-stone-600">
                                Enter your (or your client's) WhatsApp phone number with country code. An 8-character pairing code will be generated to enter on the phone.
                            </p>
                            <div>
                                <label class="block text-xs font-medium text-stone-700 mb-1">WhatsApp Phone Number</label>
                                <input
                                    type="text"
                                    v-model="pairingPhone"
                                    placeholder="e.g. 2348012345678"
                                    class="w-full text-xs rounded-md border border-[#e8e2d9] p-2.5 font-mono text-stone-800 focus:ring-[#128C7E] focus:border-[#128C7E]"
                                />
                            </div>
                            <button
                                @click="generatePairingCode"
                                :disabled="pairingLoading || !pairingPhone"
                                type="button"
                                class="w-full btn-primary py-2.5 text-xs flex items-center justify-center gap-2"
                            >
                                <svg v-if="pairingLoading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                {{ pairingLoading ? 'Generating Pairing Code...' : 'Get 8-Digit Pairing Code' }}
                            </button>
                            <p v-if="pairingError" class="text-xs text-red-600 bg-red-50 p-2 rounded border border-red-200">
                                {{ pairingError }}
                            </p>
                        </div>

                        <!-- Generated Code Display -->
                        <div v-else class="text-center py-4 space-y-4">
                            <p class="text-xs text-stone-600">Enter this 8-character code in WhatsApp on your phone:</p>

                            <div class="text-3xl font-mono font-extrabold tracking-widest text-[#128C7E] bg-emerald-50 border-2 border-[#128C7E] py-4 rounded-xl shadow-inner select-all">
                                {{ pairingCode }}
                            </div>

                            <div class="text-left bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-900 space-y-1">
                                <div class="font-semibold flex items-center gap-1.5">
                                    <span>📲</span> How to enter code on WhatsApp:
                                </div>
                                <ol class="list-decimal list-inside text-[11px] text-blue-800 space-y-1">
                                    <li>Open <strong>WhatsApp</strong> on phone → <strong>Linked Devices</strong></li>
                                    <li>Tap <strong>Link a Device</strong></li>
                                    <li>Tap <strong>"Link with phone number instead"</strong> at the bottom</li>
                                    <li>Enter the code above: <strong class="font-mono text-blue-950">{{ pairingCode }}</strong></li>
                                </ol>
                            </div>

                            <button @click="pairingCode = null" type="button" class="text-xs text-stone-500 underline">
                                Request another code
                            </button>
                        </div>
                    </div>

                    <div v-if="qrStatus !== 'CONNECTED'" class="mt-4 pt-3 border-t border-[#e8e2d9] flex justify-between items-center text-xs">
                        <span class="text-stone-400 text-[11px]">Auto-checking connection status...</span>
                        <button @click="closeQrModal" type="button" class="btn-secondary">
                            Close
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
