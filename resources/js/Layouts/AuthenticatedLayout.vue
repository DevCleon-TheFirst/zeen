<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const business = computed(() => user.value?.business);
const businesses = computed(() => page.props.auth.businesses || []);
const sidebarOpen = ref(false);

const workspaceDropdownOpen = ref(false);
const showNewWorkspaceModal = ref(false);
const showDominantModeModal = ref(false);

const industryMeta = {
    real_estate: { label: 'Real Estate', icon: '🏡', badge: 'bg-amber-900/40 text-amber-300' },
    retail: { label: 'Online Store', icon: '🏪', badge: 'bg-emerald-900/40 text-emerald-300' },
    hospitality: { label: 'Hotels / Shortlets', icon: '🏨', badge: 'bg-blue-900/40 text-blue-300' },
    healthcare: { label: 'Healthcare', icon: '🩺', badge: 'bg-rose-900/40 text-rose-300' },
    services: { label: 'Services', icon: '💼', badge: 'bg-purple-900/40 text-purple-300' },
    general: { label: 'General Store', icon: '⚡', badge: 'bg-stone-800 text-stone-300' },
};

const newBizForm = ref({
    name: '',
    industry: 'retail',
    description: '',
    processing: false,
    errors: {},
});

function switchWorkspace(bizId) {
    workspaceDropdownOpen.value = false;
    router.post(route('businesses.switch', bizId), {}, {
        preserveScroll: false,
    });
}

function createWorkspace() {
    if (!newBizForm.value.name.trim()) return;
    newBizForm.value.processing = true;
    router.post(route('businesses.store'), {
        name: newBizForm.value.name,
        industry: newBizForm.value.industry,
        description: newBizForm.value.description,
    }, {
        onSuccess: () => {
            showNewWorkspaceModal.value = false;
            newBizForm.value.name = '';
            newBizForm.value.description = '';
            newBizForm.value.processing = false;
        },
        onError: (errs) => {
            newBizForm.value.errors = errs;
            newBizForm.value.processing = false;
        },
        onFinish: () => {
            newBizForm.value.processing = false;
        },
    });
}

function setDominantMode(mode) {
    router.patch(route('businesses.dominant-mode'), {
        industry: mode,
    }, {
        onSuccess: () => {
            showDominantModeModal.value = false;
        },
    });
}

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-[#faf8f5] text-[#241e19]">

        <!-- Mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-20 bg-black/40 lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- ── SIDEBAR ─────────────────────────────────── -->
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed top-0 left-0 z-30 h-screen w-60 flex flex-col transition-transform duration-200 lg:translate-x-0 bg-[#291e17] border-r border-[#3b2c22] text-[#e8e2d9]"
        >
            <!-- Platform Brand Logo Header -->
            <div class="px-4 py-3 border-b border-[#3b2c22] bg-[#1a110b] flex items-center justify-center">
                <Link :href="route('dashboard')" class="group">
                    <img
                        src="/images/logo.png"
                        alt="ZEEN"
                        class="h-14 w-auto object-contain group-hover:scale-105 transition-transform duration-200"
                        style="filter: brightness(0) invert(1) sepia(0.35) saturate(4) brightness(1.6) drop-shadow(0 0 24px rgba(251,191,36,1)) drop-shadow(0 0 8px rgba(255,255,255,0.4));"
                    />
                </Link>
            </div>

            <!-- Brand & Workspace Header with Switcher Dropdown -->
            <div class="relative px-3 py-2.5 border-b border-[#3b2c22]">
                <button
                    @click="workspaceDropdownOpen = !workspaceDropdownOpen"
                    class="w-full flex items-center justify-between gap-2.5 px-2 py-1.5 rounded-lg hover:bg-[#3b2c22]/70 text-left transition-colors group cursor-pointer"
                    title="Switch Workspace or Change Focus Mode"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-[#4a3324] text-[#faf8f5] border border-[#6b4e37] flex items-center justify-center font-bold text-xs flex-shrink-0 shadow-sm">
                            {{ (business?.name || 'A')[0].toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-white truncate leading-tight">
                                {{ business?.name || 'Omnichannel Suite' }}
                            </p>
                            <div class="flex items-center gap-1 mt-0.5">
                                <span class="text-[10px] text-amber-200/90 truncate font-medium">
                                    {{ industryMeta[business?.industry]?.icon || '⚡' }} {{ industryMeta[business?.industry]?.label || (business?.industry || 'Store') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <svg
                        class="w-4 h-4 text-stone-400 group-hover:text-stone-200 transition-transform duration-150 flex-shrink-0"
                        :class="workspaceDropdownOpen ? 'rotate-180' : ''"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Workspace Switcher Dropdown Menu -->
                <div
                    v-if="workspaceDropdownOpen"
                    class="absolute left-2 right-2 top-full mt-1.5 z-50 rounded-xl bg-[#1d140e] border border-[#4a3324] shadow-2xl p-2 text-xs"
                >
                    <div class="px-2 py-1 flex items-center justify-between text-[10px] font-semibold uppercase tracking-wider text-stone-400 border-b border-[#3b2c22] pb-1.5 mb-1.5">
                        <span>Workspaces ({{ businesses.length }})</span>
                        <button
                            @click="showDominantModeModal = true; workspaceDropdownOpen = false;"
                            class="text-amber-400 hover:text-amber-300 normal-case font-normal flex items-center gap-1 cursor-pointer"
                        >
                            ⚙️ Focus Mode
                        </button>
                    </div>

                    <div class="max-h-48 overflow-y-auto space-y-1">
                        <button
                            v-for="biz in businesses"
                            :key="biz.id"
                            @click="switchWorkspace(biz.id)"
                            class="w-full flex items-center justify-between px-2.5 py-2 rounded-lg text-left transition-colors cursor-pointer"
                            :class="biz.id === business?.id ? 'bg-[#3b2c22] text-white font-medium' : 'text-stone-300 hover:bg-[#2e2017] hover:text-white'"
                        >
                            <div class="min-w-0 flex items-center gap-2 flex-1">
                                <span class="text-sm flex-shrink-0">{{ industryMeta[biz.industry]?.icon || '🏢' }}</span>
                                <div class="min-w-0 flex-1 truncate">
                                    <p class="truncate text-xs leading-none">{{ biz.name }}</p>
                                    <p class="text-[10px] text-stone-400 mt-1 truncate capitalize">
                                        {{ industryMeta[biz.industry]?.label || biz.industry }}
                                    </p>
                                </div>
                            </div>
                            <span v-if="biz.id === business?.id" class="w-2 h-2 rounded-full bg-emerald-400 flex-shrink-0 ml-2" title="Active"></span>
                        </button>
                    </div>

                    <!-- Add New Business -->
                    <div class="mt-2 pt-1.5 border-t border-[#3b2c22]">
                        <button
                            @click="showNewWorkspaceModal = true; workspaceDropdownOpen = false;"
                            class="w-full flex items-center justify-center gap-1.5 px-2 py-1.5 rounded-lg text-amber-300 hover:text-white hover:bg-[#3b2c22] transition-colors font-medium text-xs cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            + Create New Business
                        </button>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-5 text-xs">

                <!-- Overview -->
                <div>
                    <p class="px-2.5 mb-1.5 text-[10px] font-medium uppercase tracking-wider text-stone-400">Overview</p>
                    <Link
                        :href="route('dashboard')"
                        class="nav-link"
                        :class="route().current('dashboard') ? 'nav-link-active' : ''"
                    >
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor"/>
                            <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor"/>
                            <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor"/>
                            <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor"/>
                        </svg>
                        <span>Dashboard</span>
                    </Link>
                </div>

                <!-- Communications -->
                <div>
                    <p class="px-2.5 mb-1.5 text-[10px] font-medium uppercase tracking-wider text-stone-400">Communications</p>
                    <div class="space-y-0.5">
                        <Link :href="route('inbox.index')" class="nav-link" :class="route().current('inbox.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                            <span>Live Inbox</span>
                        </Link>
                        <Link :href="route('automations.index')" class="nav-link" :class="route().current('automations.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span>Automations</span>
                        </Link>
                    </div>
                </div>

                <!-- Integrations -->
                <div>
                    <p class="px-2.5 mb-1.5 text-[10px] font-medium uppercase tracking-wider text-stone-400">Integrations</p>
                    <div class="space-y-0.5">
                        <Link :href="route('channels.index')" class="nav-link" :class="route().current('channels.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                            </svg>
                            <span>Connected Channels</span>
                        </Link>
                        <Link :href="route('settings.ai')" class="nav-link" :class="route().current('settings.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            <span>AI Settings</span>
                        </Link>
                        <Link :href="route('setup.index')" class="nav-link" :class="route().current('setup.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>System &amp; Webhooks</span>
                        </Link>
                    </div>
                </div>

                <!-- Operations -->
                <div>
                    <p class="px-2.5 mb-1.5 text-[10px] font-medium uppercase tracking-wider text-stone-400">Operations</p>
                    <div class="space-y-0.5">
                        <Link :href="route('orders.index')" class="nav-link" :class="route().current('orders.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <span>Orders &amp; Tracking</span>
                        </Link>
                        <Link :href="route('catalog.index')" class="nav-link" :class="route().current('catalog.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <span>Catalog &amp; Stock</span>
                        </Link>
                        <Link :href="route('appointments.index')" class="nav-link" :class="route().current('appointments.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>Appointments</span>
                        </Link>
                        <Link :href="route('payments.index')" class="nav-link" :class="route().current('payments.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            <span>Gateways &amp; Ledger</span>
                        </Link>
                        <Link :href="route('vouchers.index')" class="nav-link" :class="route().current('vouchers.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                            <span>Vouchers &amp; Codes</span>
                        </Link>
                        <Link :href="route('staff.index')" class="nav-link" :class="route().current('staff.*') ? 'nav-link-active' : ''">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <span>Staff</span>
                        </Link>
                    </div>
                </div>

                <!-- Super Admin Section (Only for platform super admins) -->
                <div v-if="user?.is_super_admin" class="pt-2 border-t border-[#3b2c22]">
                    <p class="px-2.5 mb-1.5 text-[10px] font-medium uppercase tracking-wider text-amber-400">Platform Super Admin</p>
                    <Link :href="route('admin.dashboard')" class="nav-link text-amber-200 hover:text-white" :class="route().current('admin.*') ? 'nav-link-active !bg-amber-900/40 !text-amber-100' : ''">
                        <svg class="w-4 h-4 flex-shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span>Platform Command</span>
                    </Link>
                </div>

            </nav>

            <!-- User Footer -->
            <div class="p-3 border-t border-[#3b2c22]">
                <div class="flex items-center gap-2.5 px-2 py-1.5 rounded-md hover:bg-white/5 transition-colors">
                    <div class="w-7 h-7 rounded bg-[#4a3324] text-stone-200 border border-[#6b4e37] flex items-center justify-center text-xs font-semibold flex-shrink-0">
                        {{ (user?.name || 'U')[0].toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-white truncate">{{ user?.name }}</p>
                        <p class="text-[10px] text-stone-400 capitalize truncate">{{ user?.role || 'Staff' }}</p>
                    </div>
                    <button
                        @click="logout"
                        class="p-1 rounded text-stone-400 hover:text-white transition-colors"
                        title="Sign Out"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- ── MAIN AREA ─────────────────────────────────── -->
        <div class="flex flex-col flex-1 min-w-0 lg:ml-60">

            <!-- Top bar -->
            <header class="h-14 flex items-center justify-between px-3.5 sm:px-6 sticky top-0 z-10 flex-shrink-0 bg-white border-b border-[#e8e2d9]">
                <!-- Hamburger (mobile) -->
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden mr-2 sm:mr-3 p-1.5 rounded-md text-stone-500 hover:text-stone-900 hover:bg-stone-100 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <div class="flex items-center min-w-0">
                    <slot name="header">
                        <h2 class="text-sm font-semibold text-[#241e19]">Dashboard</h2>
                    </slot>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Dominant Focus Mode Pill / Quick Switcher -->
                    <button
                        @click="showDominantModeModal = true"
                        class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border border-amber-300/80 bg-amber-50 text-amber-900 hover:bg-amber-100 transition-colors shadow-xs cursor-pointer"
                        title="Click to change the dominant operating focus mode of this business"
                    >
                        <span>{{ industryMeta[business?.industry]?.icon || '⚡' }}</span>
                        <span class="text-stone-600 font-normal">Focus:</span>
                        <strong class="font-semibold text-amber-900">{{ industryMeta[business?.industry]?.label || (business?.industry || 'Store') }}</strong>
                        <span class="text-[10px] text-amber-700 underline ml-0.5 font-normal">Change</span>
                    </button>

                    <!-- Global Search -->
                    <div class="hidden sm:flex items-center gap-2 px-2.5 py-1.5 rounded-md text-xs w-44 bg-[#faf8f5] border border-[#e8e2d9] text-stone-500">
                        <svg class="w-3.5 h-3.5 flex-shrink-0 text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <span class="text-stone-400">Search...</span>
                        <kbd class="ml-auto text-[10px] px-1 rounded font-mono bg-white text-stone-400 border border-[#e8e2d9]">⌘K</kbd>
                    </div>

                    <!-- User Initials -->
                    <div class="w-7 h-7 rounded-full bg-[#faf8f5] text-[#4a3324] border border-[#e8e2d9] flex items-center justify-center text-xs font-semibold">
                        {{ (user?.name || 'U')[0].toUpperCase() }}
                    </div>
                </div>
            </header>

            <!-- Flash alerts -->
            <div v-if="$page.props.flash?.success" class="mx-6 mt-4">
                <div class="flex items-center gap-2 px-3 py-2 rounded-md text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200">
                    <svg class="w-4 h-4 flex-shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    {{ $page.props.flash.success }}
                </div>
            </div>
            <div v-if="$page.props.flash?.error" class="mx-6 mt-4">
                <div class="flex items-center gap-2 px-3 py-2 rounded-md text-xs font-medium bg-red-50 text-red-800 border border-red-200">
                    <svg class="w-4 h-4 flex-shrink-0 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    {{ $page.props.flash.error }}
                </div>
            </div>

            <!-- Page content -->
            <main class="relative flex-1 overflow-y-auto">
                <!-- Watermark -->
                <div class="pointer-events-none fixed inset-0 lg:left-60 flex items-center justify-center z-0" aria-hidden="true">
                    <img
                        src="/images/logo.png"
                        alt=""
                        class="w-72 h-72 object-contain opacity-[0.04] select-none"
                        draggable="false"
                    />
                </div>
                <!-- Slot content sits above watermark -->
                <div class="relative z-10">
                    <slot />
                </div>
            </main>
        </div>

        <!-- ── MODAL: Create New Business Workspace ─────────────────── -->
        <div v-if="showNewWorkspaceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden border border-[#e8e2d9] animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-[#291e17] text-white flex items-center justify-between border-b border-[#3b2c22]">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🏢</span>
                        <div>
                            <h3 class="text-sm font-bold text-white">Create New Business Workspace</h3>
                            <p class="text-[11px] text-stone-300">Add another store or company under your account</p>
                        </div>
                    </div>
                    <button @click="showNewWorkspaceModal = false" class="text-stone-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <form @submit.prevent="createWorkspace" class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block font-medium text-stone-800 mb-1">Business / Store Name <span class="text-red-500">*</span></label>
                        <input
                            v-model="newBizForm.name"
                            type="text"
                            required
                            placeholder="e.g. Apex Apparel Store, Grand Heights Realty"
                            class="w-full rounded-lg border-stone-300 shadow-xs focus:border-amber-600 focus:ring-amber-600 text-xs px-3 py-2"
                        />
                        <p v-if="newBizForm.errors?.name" class="text-red-600 text-[11px] mt-1">{{ newBizForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block font-medium text-stone-800 mb-1">Dominant Industry / Mode <span class="text-red-500">*</span></label>
                        <select
                            v-model="newBizForm.industry"
                            class="w-full rounded-lg border-stone-300 shadow-xs focus:border-amber-600 focus:ring-amber-600 text-xs px-3 py-2 cursor-pointer"
                        >
                            <option value="retail">🏪 Online Store &amp; Retail (Products, Sizes, Cart, Tracking)</option>
                            <option value="real_estate">🏡 Real Estate &amp; Properties (Listings, Bedrooms, Tour Booking)</option>
                            <option value="hospitality">🏨 Hotels &amp; Shortlets (Suites, Check-in, Reservations)</option>
                            <option value="services">💼 Professional Services &amp; Consultations</option>
                            <option value="healthcare">🩺 Healthcare &amp; Medical Consultations</option>
                            <option value="general">⚡ Multi-domain / General Store</option>
                        </select>
                        <p class="text-[10px] text-stone-500 mt-1">
                            Sets the default catalog structure and AI agent persona. You can adjust this anytime.
                        </p>
                    </div>

                    <div>
                        <label class="block font-medium text-stone-800 mb-1">Short Description / Tagline (Optional)</label>
                        <textarea
                            v-model="newBizForm.description"
                            rows="2"
                            placeholder="e.g. Trendy urban fashion and accessories delivered across Nigeria"
                            class="w-full rounded-lg border-stone-300 shadow-xs focus:border-amber-600 focus:ring-amber-600 text-xs px-3 py-2"
                        ></textarea>
                    </div>

                    <div class="pt-3 border-t border-stone-200 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="showNewWorkspaceModal = false"
                            class="px-3 py-1.5 rounded-lg border border-stone-300 text-stone-700 hover:bg-stone-50 cursor-pointer font-medium"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="newBizForm.processing"
                            class="px-4 py-1.5 rounded-lg bg-[#4a3324] hover:bg-[#38261a] text-white font-medium cursor-pointer shadow-xs disabled:opacity-50"
                        >
                            {{ newBizForm.processing ? 'Creating...' : 'Create &amp; Switch' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── MODAL: Change Dominant Focus Mode ──────────────────────── -->
        <div v-if="showDominantModeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden border border-[#e8e2d9] animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-[#291e17] text-white flex items-center justify-between border-b border-[#3b2c22]">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">⚙️</span>
                        <div>
                            <h3 class="text-sm font-bold text-white">Dominant Operating Mode</h3>
                            <p class="text-[11px] text-stone-300">Set the active business focus for {{ business?.name }}</p>
                        </div>
                    </div>
                    <button @click="showDominantModeModal = false" class="text-stone-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <p class="text-stone-600 leading-relaxed">
                        Choose which business mode is dominant right now. The <strong>AI sales agent</strong>, prompts, catalog emphasis, and messaging will dynamically prioritize this mode:
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <!-- Retail / Online Store -->
                        <div
                            @click="setDominantMode('retail')"
                            class="p-3 rounded-xl border-2 cursor-pointer transition-all flex flex-col justify-between"
                            :class="business?.industry === 'retail' ? 'border-emerald-600 bg-emerald-50/50 shadow-xs' : 'border-stone-200 hover:border-emerald-300 hover:bg-stone-50'"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-lg">🏪</span>
                                    <span v-if="business?.industry === 'retail'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-600 text-white uppercase">Active</span>
                                </div>
                                <h4 class="font-bold text-stone-900 mt-1">Online Store &amp; Retail</h4>
                                <p class="text-[11px] text-stone-500 mt-1">Sizes, colors, shopping cart, instant checkout &amp; order tracking codes.</p>
                            </div>
                        </div>

                        <!-- Real Estate -->
                        <div
                            @click="setDominantMode('real_estate')"
                            class="p-3 rounded-xl border-2 cursor-pointer transition-all flex flex-col justify-between"
                            :class="business?.industry === 'real_estate' ? 'border-amber-600 bg-amber-50/50 shadow-xs' : 'border-stone-200 hover:border-amber-300 hover:bg-stone-50'"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-lg">🏡</span>
                                    <span v-if="business?.industry === 'real_estate'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-600 text-white uppercase">Active</span>
                                </div>
                                <h4 class="font-bold text-stone-900 mt-1">Real Estate &amp; Property</h4>
                                <p class="text-[11px] text-stone-500 mt-1">Listings, bedrooms, price/rent, property tour &amp; inspection bookings.</p>
                            </div>
                        </div>

                        <!-- Hospitality -->
                        <div
                            @click="setDominantMode('hospitality')"
                            class="p-3 rounded-xl border-2 cursor-pointer transition-all flex flex-col justify-between"
                            :class="business?.industry === 'hospitality' ? 'border-blue-600 bg-blue-50/50 shadow-xs' : 'border-stone-200 hover:border-blue-300 hover:bg-stone-50'"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-lg">🏨</span>
                                    <span v-if="business?.industry === 'hospitality'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-600 text-white uppercase">Active</span>
                                </div>
                                <h4 class="font-bold text-stone-900 mt-1">Hotels &amp; Shortlets</h4>
                                <p class="text-[11px] text-stone-500 mt-1">Suites, nightly rates, check-in dates, guest amenities &amp; room reservation.</p>
                            </div>
                        </div>

                        <!-- Services -->
                        <div
                            @click="setDominantMode('services')"
                            class="p-3 rounded-xl border-2 cursor-pointer transition-all flex flex-col justify-between"
                            :class="business?.industry === 'services' ? 'border-purple-600 bg-purple-50/50 shadow-xs' : 'border-stone-200 hover:border-purple-300 hover:bg-stone-50'"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-lg">💼</span>
                                    <span v-if="business?.industry === 'services'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-600 text-white uppercase">Active</span>
                                </div>
                                <h4 class="font-bold text-stone-900 mt-1">Services &amp; Booking</h4>
                                <p class="text-[11px] text-stone-500 mt-1">Consultation tiers, session bookings, retainer invoices &amp; appointments.</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-stone-200 flex items-center justify-end">
                        <button
                            type="button"
                            @click="showDominantModeModal = false"
                            class="px-4 py-1.5 rounded-lg border border-stone-300 text-stone-700 hover:bg-stone-50 cursor-pointer font-medium"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.nav-link {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.45rem 0.625rem;
    border-radius: 0.375rem;
    color: #c9bfb5;
    transition: all 0.15s ease;
    text-decoration: none;
    font-weight: 400;
}

.nav-link:hover {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.05);
}

.nav-link-active {
    color: #ffffff !important;
    background-color: rgba(255, 255, 255, 0.08) !important;
    font-weight: 500 !important;
    border-left: 2px solid #c59b6d;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    padding-left: 0.5rem;
}
</style>
