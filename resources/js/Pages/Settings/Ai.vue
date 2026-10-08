<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    setting: Object,
    availableProviders: Array,
    deepseek_balance: Object,
});

const deepseekBalance = ref(props.deepseek_balance || null);
const checkingBalance = ref(false);

const checkDeepSeekBalance = async () => {
    checkingBalance.value = true;
    try {
        const res = await axios.get(route('settings.ai.balance'));
        if (res.data) {
            deepseekBalance.value = res.data;
        }
    } catch (err) {
        console.error('Failed to query DeepSeek balance:', err);
    } finally {
        checkingBalance.value = false;
    }
};

const form = useForm({
    provider: props.setting?.provider || 'deepseek',
    api_key: '',
    base_url: props.setting?.base_url || 'https://api.deepseek.com',
    model: props.setting?.model || 'deepseek-chat',
    temperature: props.setting?.temperature ?? 0.7,
    max_tokens: props.setting?.max_tokens ?? 2000,
    is_active: props.setting?.is_active ?? true,
});

const testing = ref(false);
const testResult = ref(null);

const onProviderChange = () => {
    const p = props.availableProviders.find((x) => x.id === form.provider);
    if (p) {
        form.model = p.defaultModel;
        form.base_url = p.id === 'deepseek' ? 'https://api.deepseek.com' : p.id === 'openai' ? 'https://api.openai.com/v1' : form.base_url;
    }
};

const submit = () => {
    form.post(route('settings.ai.update'), {
        preserveScroll: true,
        onSuccess: () => { testResult.value = null; },
    });
};

const runConnectionTest = async () => {
    testing.value = true;
    testResult.value = null;
    try {
        const res = await axios.post(route('settings.ai.test'));
        testResult.value = res.data;
    } catch (err) {
        testResult.value = {
            success: false,
            message: err.response?.data?.message || 'Connection test failed.',
            latency_ms: 0,
        };
    } finally {
        testing.value = false;
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="AI Settings" />

        <template #header>
            <div class="flex items-center gap-2 text-xs font-semibold text-[#291e17]">
                AI Settings
            </div>
        </template>

        <div class="p-6 md:p-8 space-y-6 bg-[#faf8f5] min-h-screen text-[#291e17]">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-lg font-semibold tracking-tight text-[#291e17]">
                        AI Provider & Intelligence
                    </h1>
                    <p class="text-xs text-[#7b5537] mt-0.5">
                        Configure your LLM connection — DeepSeek (default), OpenAI, Claude, or custom providers.
                    </p>
                </div>
                <button
                    type="button"
                    @click="runConnectionTest"
                    :disabled="testing || !setting.has_api_key"
                    class="btn-secondary inline-flex items-center gap-2 self-start md:self-auto disabled:opacity-40"
                >
                    <svg v-if="testing" class="animate-spin w-3.5 h-3.5 text-[#4a3324]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5 text-[#4a3324]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    {{ testing ? 'Testing...' : 'Test Connection' }}
                </button>
            </div>

            <!-- Test Result Alert -->
            <div
                v-if="testResult"
                class="p-3.5 rounded-md flex items-start justify-between text-xs"
                :class="testResult.success
                    ? 'bg-[#ecfdf5] border border-[#a7f3d0] text-[#065f46]'
                    : 'bg-[#fef2f2] border border-[#fecaca] text-[#991b1b]'"
            >
                <div class="flex items-start gap-2.5">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path v-if="testResult.success" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <div>
                        <h4 class="font-semibold">{{ testResult.success ? 'Connection Successful' : 'Connection Failed' }}</h4>
                        <p class="mt-0.5 text-[11px] opacity-90">{{ testResult.message }}</p>
                    </div>
                </div>
                <span v-if="testResult.latency_ms" class="text-[11px] px-2 py-0.5 rounded font-mono bg-white/70 border border-current/20 tabular-nums">
                    {{ testResult.latency_ms }} ms
                </span>
            </div>

            <!-- Main Form Card -->
            <div class="card overflow-hidden">
                <!-- Card header -->
                <div class="px-6 py-4 bg-[#f8f5f0] border-b border-[#e8e2d9]">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-md bg-[#f2ece4] border border-[#e8e2d9] flex items-center justify-center text-[#4a3324]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-[#291e17]">LLM Provider Configuration</h3>
                            <p class="text-xs text-[#7b5537] mt-0.5">Select the intelligence engine that powers your customer chat, classification, and tools.</p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-6">
                    <!-- Provider Selection -->
                    <div>
                        <label class="block text-xs font-medium text-[#4a3324] mb-2.5">Select AI Provider</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label
                                v-for="p in availableProviders"
                                :key="p.id"
                                class="relative flex flex-col p-4 rounded-md cursor-pointer transition-colors border"
                                :class="form.provider === p.id
                                    ? 'border-[#4a3324] bg-[#f8f5f0]'
                                    : 'border-[#e8e2d9] bg-white hover:border-[#b89f7e]'"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            v-model="form.provider"
                                            :value="p.id"
                                            @change="onProviderChange"
                                            class="h-4 w-4 accent-[#4a3324]"
                                        />
                                        <span class="font-semibold text-xs text-[#291e17]">{{ p.name }}</span>
                                    </div>
                                    <span v-if="p.id === 'deepseek'" class="badge-neutral text-[10px] font-mono">
                                        Recommended
                                    </span>
                                </div>
                                <p class="text-[11px] text-[#7b5537] ml-6 leading-relaxed">{{ p.description }}</p>
                            </label>
                        </div>
                    </div>

                    <!-- API Key -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-medium text-[#4a3324]">API Key</label>
                            <span v-if="setting.has_api_key" class="text-[11px] font-mono text-[#065f46]">
                                Saved: {{ setting.masked_api_key }}
                            </span>
                        </div>
                        <input
                            id="api_key"
                            type="password"
                            class="w-full rounded-md px-3 py-2 font-mono text-xs border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                            v-model="form.api_key"
                            :placeholder="setting.has_api_key ? 'Enter new key to replace existing' : 'sk-...'"
                            autocomplete="off"
                        />
                        <p class="mt-1 text-[11px] text-[#7b5537]">
                            Your API key is encrypted with AES-256 before storage and never exposed in frontend bundles.
                        </p>
                        <InputError class="mt-1" :message="form.errors.api_key" />

                        <!-- DeepSeek Live Account Balance Widget -->
                        <div
                            v-if="form.provider === 'deepseek'"
                            class="mt-3 p-3.5 rounded-lg border border-amber-300/80 bg-amber-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                        >
                            <div class="flex items-start gap-2.5">
                                <div class="w-7 h-7 rounded-md bg-amber-700 text-white flex items-center justify-center font-bold text-xs flex-shrink-0 shadow-xs">
                                    💳
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-stone-900 text-xs">DeepSeek Live Balance</h4>
                                        <span
                                            v-if="deepseekBalance?.is_available"
                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800"
                                        >
                                            ● API Active &amp; Funded
                                        </span>
                                        <span
                                            v-else-if="deepseekBalance && !deepseekBalance.is_available"
                                            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800"
                                        >
                                            ● Inactive / Unfunded
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-stone-600 mt-0.5">
                                        Queried directly from <code class="font-mono bg-white border border-stone-200 px-1 py-0.2 rounded text-[10px]">https://api.deepseek.com/user/balance</code>
                                    </p>
                                    <div v-if="deepseekBalance?.success" class="mt-0.5 text-[11px] text-stone-500">
                                        Cash Top-up: <strong>${{ Number(deepseekBalance.topped_up_balance || 0).toFixed(2) }}</strong> &middot; Promotional Grant: <strong>${{ Number(deepseekBalance.granted_balance || 0).toFixed(2) }}</strong>
                                    </div>
                                    <div v-else-if="deepseekBalance?.error" class="mt-0.5 text-[11px] text-rose-600 font-medium">
                                        {{ deepseekBalance.error }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 self-end sm:self-center">
                                <div v-if="deepseekBalance?.success" class="text-right">
                                    <span class="text-[10px] uppercase font-bold text-stone-500 tracking-wider block">Remaining Funds</span>
                                    <span class="text-lg font-black text-emerald-700">
                                        {{ deepseekBalance.formatted || ('$' + Number(deepseekBalance.total_balance || 0).toFixed(2) + ' USD') }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    @click="checkDeepSeekBalance"
                                    :disabled="checkingBalance || !setting.has_api_key"
                                    class="px-2.5 py-1.5 rounded-md border border-stone-300 bg-white hover:bg-stone-50 text-stone-800 font-semibold text-xs flex items-center gap-1.5 transition-colors cursor-pointer disabled:opacity-40 shadow-xs"
                                >
                                    <svg class="w-3 h-3 text-stone-600" :class="checkingBalance ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <span>{{ checkingBalance ? 'Querying...' : 'Check Live Balance' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Base URL -->
                    <div>
                        <label class="block text-xs font-medium text-[#4a3324] mb-1">API Endpoint / Base URL</label>
                        <input
                            id="base_url"
                            type="text"
                            class="w-full rounded-md px-3 py-2 font-mono text-xs border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                            v-model="form.base_url"
                            placeholder="https://api.deepseek.com"
                        />
                        <p class="mt-1 text-[11px] text-[#7b5537]">Default for DeepSeek is https://api.deepseek.com. Customize for custom proxies or private deployments.</p>
                        <InputError class="mt-1" :message="form.errors.base_url" />
                    </div>

                    <!-- Model, Temperature, Max Tokens -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-medium text-[#4a3324] mb-1">Model Name</label>
                            <input
                                id="model"
                                type="text"
                                class="w-full rounded-md px-3 py-2 font-mono text-xs border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                v-model="form.model"
                                placeholder="deepseek-chat"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.model" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-[#4a3324] mb-1">Temperature ({{ form.temperature }})</label>
                            <input
                                id="temperature"
                                type="range"
                                min="0"
                                max="1.5"
                                step="0.05"
                                v-model.number="form.temperature"
                                class="mt-2 block w-full cursor-pointer accent-[#4a3324]"
                            />
                            <div class="flex justify-between text-[10px] text-[#a89078] mt-1 font-mono">
                                <span>Precise</span>
                                <span>Balanced</span>
                                <span>Creative</span>
                            </div>
                            <InputError class="mt-1" :message="form.errors.temperature" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-[#4a3324] mb-1">Max Output Tokens</label>
                            <input
                                id="max_tokens"
                                type="number"
                                min="50"
                                max="8192"
                                step="50"
                                class="w-full rounded-md px-3 py-2 font-mono text-xs border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                v-model.number="form.max_tokens"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.max_tokens" />
                        </div>
                    </div>

                    <!-- Active Toggle + Submit -->
                    <div class="pt-4 border-t border-[#e8e2d9] flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input
                                id="is_active"
                                type="checkbox"
                                v-model="form.is_active"
                                class="rounded h-4 w-4 accent-[#4a3324]"
                            />
                            <span class="text-xs font-medium text-[#291e17]">Enable AI Auto-Pilot on Incoming Messages</span>
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="btn-primary inline-flex items-center gap-2 disabled:opacity-50"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
