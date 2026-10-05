<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, nextTick, onMounted, watch, computed } from 'vue';

const props = defineProps({
    business_id: Number,
    conversations: Array,
    activeConversation: Object,
    catalogItems: {
        type: Array,
        default: () => [],
    },
    filters: Object,
});

const replyForm = useForm({ content: '' });
const noteForm = useForm({ content: '' });
const messagesContainer = ref(null);
const searchQuery = ref('');
const mobileShowChat = ref(false);
const showCustomerDetails = ref(false);
const isNoteMode = ref(false); // toggle between reply and internal note

// ─── In-Chat Catalog & Inventory Admin ──────────────────────────────────────
const activeRightTab = ref('catalog'); // 'catalog' | 'customer'
const showAddProduct = ref(false);
const catalogSearch = ref('');
const imagePreview = ref(null);
const fileInputRef = ref(null);

const productForm = useForm({
    name: '',
    price: '',
    currency: 'NGN',
    category: '',
    stock_quantity: '',
    description: '',
    image_file: null,
});

const filteredCatalog = computed(() => {
    const list = props.catalogItems || [];
    if (!catalogSearch.value.trim()) return list;
    const q = catalogSearch.value.toLowerCase();
    return list.filter(item =>
        item.name.toLowerCase().includes(q) ||
        (item.category && item.category.toLowerCase().includes(q))
    );
});

const onImageSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
        productForm.image_file = file;
        const reader = new FileReader();
        reader.onload = (event) => {
            imagePreview.value = event.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const cancelAddProduct = () => {
    showAddProduct.value = false;
    productForm.reset();
    imagePreview.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const submitQuickProduct = () => {
    if (!productForm.name || !productForm.price) return;
    productForm.post(route('inbox.quick-product'), {
        preserveScroll: true,
        onSuccess: () => {
            cancelAddProduct();
        },
    });
};

const adjustStock = (item, delta) => {
    router.patch(route('inbox.quick-stock', item.id), { delta }, {
        preserveScroll: true,
    });
};

const shareProduct = (item) => {
    if (!props.activeConversation) return;
    router.post(route('inbox.send-product', [props.activeConversation.id, item.id]), {}, {
        preserveScroll: true,
    });
};

const openCatalogDrawer = () => {
    activeRightTab.value = 'catalog';
    showCustomerDetails.value = true;
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

onMounted(() => {
    scrollToBottom();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('selected')) {
        mobileShowChat.value = true;
    }

    if (window.Echo && props.business_id) {
        window.Echo.private(`business.${props.business_id}.inbox`)
            .listen('MessageReceived', () => {
                router.reload({
                    only: ['conversations', 'activeConversation'],
                    preserveScroll: true,
                    onSuccess: () => scrollToBottom(),
                });
            });
    }
});

watch(() => props.activeConversation?.id, () => {
    scrollToBottom();
    isNoteMode.value = false;
});

// ─── Actions ─────────────────────────────────────────────────────────────────
const sendReply = () => {
    if (!replyForm.content.trim() || !props.activeConversation) return;
    replyForm.post(route('inbox.reply', props.activeConversation.id), {
        preserveScroll: true,
        onSuccess: () => { replyForm.reset(); scrollToBottom(); },
    });
};

const sendNote = () => {
    if (!noteForm.content.trim() || !props.activeConversation) return;
    noteForm.post(route('inbox.note', props.activeConversation.id), {
        preserveScroll: true,
        onSuccess: () => { noteForm.reset(); scrollToBottom(); },
    });
};

const toggleHandover = () => {
    if (!props.activeConversation) return;
    router.post(route('inbox.handover', props.activeConversation.id), {}, { preserveScroll: true });
};

const resolveConversation = () => {
    if (!props.activeConversation) return;
    router.post(route('inbox.resolve', props.activeConversation.id), {}, { preserveScroll: true });
};

// ─── Filters ─────────────────────────────────────────────────────────────────
const filterByChannel = (channel) => {
    router.get(route('inbox.index'), {
        channel: channel === 'all' ? null : channel,
        status: props.filters.status === 'all' ? null : props.filters.status,
    }, { preserveState: true });
};

const filterByStatus = (status) => {
    router.get(route('inbox.index'), {
        channel: props.filters.channel === 'all' ? null : props.filters.channel,
        status: status === 'all' ? null : status,
    }, { preserveState: true });
};

const selectConversation = (id) => {
    mobileShowChat.value = true;
    router.get(route('inbox.index'), {
        channel: props.filters.channel === 'all' ? null : props.filters.channel,
        status: props.filters.status === 'all' ? null : props.filters.status,
        selected: id,
    }, { preserveState: true });
};

const closeChatOnMobile = () => {
    mobileShowChat.value = false;
    showCustomerDetails.value = false;
};

// ─── Computed ─────────────────────────────────────────────────────────────────
const filteredConversations = computed(() => {
    if (!searchQuery.value) return props.conversations;
    const q = searchQuery.value.toLowerCase();
    return props.conversations.filter((c) =>
        c.customer.name.toLowerCase().includes(q) ||
        (c.last_message?.content || '').toLowerCase().includes(q)
    );
});

const channelBadgeColor = (ch) => {
    if (ch === 'whatsapp' || ch === 'whatsapp_web') return '#25D366';
    if (ch === 'telegram') return '#229ED9';
    return '#006AFF';
};

const priorityClass = (p) => {
    if (p === 'urgent') return 'bg-red-100 text-red-700 border-red-200';
    if (p === 'high') return 'bg-amber-100 text-amber-700 border-amber-200';
    return '';
};

const STATUS_TABS = [
    { value: 'all', label: 'All' },
    { value: 'new', label: 'Open' },
    { value: 'human_handling', label: 'Human' },
    { value: 'escalated', label: 'Escalated' },
    { value: 'resolved', label: 'Resolved' },
];
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Live Inbox" />

        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-[#241e19]">Live Inbox</h2>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Live
                </span>
            </div>
        </template>

        <div class="flex overflow-hidden bg-[#faf8f5] h-[calc(100dvh-3.5rem)] md:h-[calc(100vh-3.5rem)]">

            <!-- Left: Conversation List -->
            <div
                class="flex flex-col flex-shrink-0 bg-white border-r border-[#e8e2d9] transition-all"
                :class="[
                    mobileShowChat ? 'hidden md:flex' : 'flex w-full',
                    'md:w-80 lg:w-96'
                ]"
            >
                <!-- Filter bar -->
                <div class="p-3 space-y-2 border-b border-[#e8e2d9]">
                    <!-- Channel Tabs -->
                    <div class="flex items-center p-0.5 rounded-md bg-[#faf8f5] border border-[#e8e2d9]">
                        <button
                            v-for="ch in ['all', 'whatsapp', 'telegram', 'messenger']"
                            :key="ch"
                            @click="filterByChannel(ch)"
                            class="flex-1 py-1 rounded text-[11px] font-medium transition-colors capitalize text-center"
                            :class="filters.channel === ch
                                ? 'bg-[#4a3324] text-white'
                                : 'text-stone-600 hover:text-[#241e19]'"
                        >
                            {{ ch === 'all' ? 'All' : ch }}
                        </button>
                    </div>

                    <!-- Status Tabs -->
                    <div class="flex items-center gap-1 overflow-x-auto pb-0.5 scrollbar-hide">
                        <button
                            v-for="tab in STATUS_TABS"
                            :key="tab.value"
                            @click="filterByStatus(tab.value)"
                            class="flex-shrink-0 px-2.5 py-1 rounded-full text-[10px] font-semibold transition-colors border"
                            :class="filters.status === tab.value
                                ? 'bg-[#4a3324] text-white border-[#4a3324]'
                                : 'bg-white text-stone-600 border-[#e8e2d9] hover:border-[#7b5537] hover:text-[#241e19]'"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- Search -->
                    <div class="relative">
                        <svg class="absolute left-2.5 top-2 w-3.5 h-3.5 text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Search customer or message..."
                            class="w-full text-xs pl-8 pr-3 py-1.5 rounded-md border border-[#e8e2d9] bg-[#faf8f5] text-[#241e19] placeholder-stone-400 focus:outline-none focus:border-[#7b5537] focus:ring-1 focus:ring-[#7b5537]"
                        />
                    </div>
                </div>

                <!-- Conversation items -->
                <div class="flex-1 overflow-y-auto divide-y divide-[#e8e2d9]">
                    <div v-if="filteredConversations.length === 0" class="p-8 text-center text-xs text-stone-500">
                        No conversations found in this view.
                    </div>

                    <div
                        v-for="conv in filteredConversations"
                        :key="conv.id"
                        @click="selectConversation(conv.id)"
                        class="px-4 py-3 cursor-pointer transition-colors relative flex items-start gap-3"
                        :class="activeConversation?.id === conv.id ? 'bg-[#f5efe6]/60 border-l-2 border-[#4a3324]' : 'hover:bg-[#faf8f5]'"
                    >
                        <!-- Avatar -->
                        <div class="relative flex-shrink-0">
                            <div class="w-8 h-8 rounded-full bg-[#f5efe6] text-[#4a3324] border border-[#e8e2d9] flex items-center justify-center font-medium text-xs">
                                {{ conv.customer.name[0]?.toUpperCase() }}
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-semibold text-[#241e19] truncate">{{ conv.customer.name }}</h4>
                                <span class="text-[10px] text-stone-400 whitespace-nowrap ml-2">{{ conv.last_message_at || '' }}</span>
                            </div>

                            <p class="text-[11px] text-stone-500 truncate mt-0.5">
                                <span v-if="conv.last_message?.is_internal" class="font-medium text-amber-700">📝 Note: </span>
                                <span v-else-if="conv.last_message?.sender_type === 'ai'" class="font-medium text-[#7b5537]">AI: </span>
                                <span v-else-if="conv.last_message?.sender_type === 'human'" class="font-medium text-emerald-700">Staff: </span>
                                {{ conv.last_message?.content || 'No messages yet' }}
                            </p>

                            <div class="flex items-center gap-1.5 mt-1.5">
                                <span class="badge-neutral text-[10px] py-0 px-1.5 uppercase font-mono">
                                    {{ conv.channel }}
                                </span>
                                <span
                                    v-if="conv.priority && conv.priority !== 'normal'"
                                    class="px-1.5 py-0 rounded text-[10px] font-semibold border capitalize"
                                    :class="priorityClass(conv.priority)"
                                >
                                    {{ conv.priority }}
                                </span>
                                <span
                                    v-if="conv.status === 'escalated'"
                                    class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-red-50 text-red-700 border border-red-200"
                                >
                                    Escalated
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Center: Chat thread -->
            <div 
                v-if="activeConversation" 
                class="flex-1 flex flex-col overflow-hidden bg-white"
                :class="mobileShowChat ? 'flex w-full' : 'hidden md:flex'"
            >
                <!-- Thread top bar -->
                <div class="h-14 px-3 sm:px-6 flex items-center justify-between flex-shrink-0 border-b border-[#e8e2d9] bg-white gap-2">
                    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                        <!-- Mobile back button -->
                        <button
                            @click="closeChatOnMobile"
                            type="button"
                            class="md:hidden p-1.5 -ml-1 text-stone-600 hover:text-[#241e19] hover:bg-stone-100 rounded-md transition-colors flex items-center gap-0.5 flex-shrink-0"
                            title="Back to conversation list"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <div class="w-8 h-8 rounded-full bg-[#f5efe6] text-[#4a3324] border border-[#e8e2d9] flex items-center justify-center font-semibold text-xs flex-shrink-0">
                            {{ activeConversation.customer.name[0]?.toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <h3 class="text-xs font-semibold text-[#241e19] truncate max-w-[110px] sm:max-w-[200px]">
                                    {{ activeConversation.customer.name }}
                                </h3>
                                <span class="badge-neutral text-[9px] sm:text-[10px] uppercase font-mono py-0 px-1">
                                    {{ activeConversation.channel }}
                                </span>
                            </div>
                            <div class="text-[10px] text-stone-400 font-mono truncate">
                                {{ activeConversation.customer.phone || 'Thread #' + activeConversation.id }}
                            </div>
                        </div>
                    </div>

                    <!-- Controls -->
                    <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                        <button
                            @click="toggleHandover"
                            type="button"
                            class="btn-secondary py-1 px-2 text-[11px] sm:px-3 sm:text-xs"
                            :title="activeConversation.handler === 'ai' ? 'Take Over from AI' : 'Hand Back to AI'"
                        >
                            {{ activeConversation.handler === 'ai' ? 'Take Over' : 'To AI' }}
                        </button>

                        <button
                            @click="resolveConversation"
                            type="button"
                            class="btn-secondary py-1 px-2 text-[11px] sm:px-3 sm:text-xs text-stone-600"
                        >
                            Resolve
                        </button>

                        <!-- In-Chat Catalog & Inventory Button -->
                        <button
                            @click="openCatalogDrawer"
                            type="button"
                            class="btn-secondary py-1 px-2 text-[11px] sm:px-2.5 sm:text-xs flex items-center gap-1.5 text-[#7b5537] border-[#d8c8b8] bg-[#fbf8f5] hover:bg-[#f5efe6]"
                            title="Manage products and stock in chat"
                        >
                            <span>📦</span>
                            <span class="font-medium hidden sm:inline">Products</span>
                            <span v-if="catalogItems.length" class="text-[9px] px-1.5 py-0.2 rounded-full bg-[#e8ded1] text-[#4a3324] font-mono">
                                {{ catalogItems.length }}
                            </span>
                        </button>

                        <!-- Mobile Customer Details Toggle -->
                        <button
                            @click="showCustomerDetails = !showCustomerDetails"
                            type="button"
                            class="xl:hidden p-1.5 text-stone-500 hover:text-[#241e19] hover:bg-stone-100 rounded-md transition-colors"
                            :class="showCustomerDetails ? 'bg-[#f5efe6] text-[#7b5537]' : ''"
                            title="Customer details"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div ref="messagesContainer" class="flex-1 p-3 sm:p-6 overflow-y-auto space-y-3 sm:space-y-4 bg-[#faf8f5]">
                    <div v-if="activeConversation.messages.length === 0" class="p-8 text-center text-xs text-stone-500">
                        Conversation started.
                    </div>

                    <div
                        v-for="msg in activeConversation.messages"
                        :key="msg.id"
                        class="flex flex-col"
                        :class="[
                            msg.is_internal
                                ? 'max-w-[92%] sm:max-w-xl mx-auto'
                                : msg.direction === 'inbound'
                                    ? 'max-w-[88%] sm:max-w-md items-start mr-auto'
                                    : 'max-w-[88%] sm:max-w-md items-end ml-auto'
                        ]"
                    >
                        <!-- Internal Note bubble -->
                        <div
                            v-if="msg.is_internal"
                            class="w-full px-3.5 py-2.5 rounded-lg text-xs leading-relaxed shadow-xs bg-amber-50 border border-amber-200"
                        >
                            <div class="flex items-center gap-1.5 mb-1.5 text-[10px] font-semibold text-amber-700">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Internal Note · Team only
                            </div>
                            <p class="text-amber-900 whitespace-pre-wrap break-words">{{ msg.content }}</p>
                        </div>

                        <!-- Regular message bubble -->
                        <div
                            v-else
                            class="px-3.5 py-2.5 rounded-lg text-xs leading-relaxed shadow-xs"
                            :class="msg.direction === 'inbound'
                                ? 'bg-white border border-[#e8e2d9] text-[#241e19]'
                                : 'bg-[#4a3324] text-white'"
                        >
                            <div class="flex items-center gap-1.5 mb-1 text-[10px] opacity-75">
                                <span v-if="msg.direction === 'inbound'">{{ activeConversation.customer.name }}</span>
                                <span v-else-if="msg.sender_type === 'ai'">AI Assistant</span>
                                <span v-else>Staff</span>
                            </div>

                            <!-- Media attachments -->
                            <div v-if="msg.media && msg.media.length > 0" class="mb-2 space-y-2">
                                <template v-for="(item, idx) in msg.media" :key="idx">
                                    <img v-if="item.type === 'image'" :src="item.url" class="max-w-full h-auto rounded-md" alt="Attached Image" />
                                    <audio v-else-if="item.type === 'audio'" :src="item.url" controls class="w-full max-w-[240px]"></audio>
                                    <a v-else :href="item.url" target="_blank" class="underline text-blue-500">Attachment ({{ item.type }})</a>
                                </template>
                            </div>

                            <p v-if="msg.content" class="whitespace-pre-wrap break-words">{{ msg.content }}</p>
                        </div>

                        <span
                            v-if="!msg.is_internal"
                            class="text-[10px] text-stone-400 mt-1 font-mono flex items-center gap-1"
                        >
                            {{ msg.created_at }}
                            <!-- Delivery ticks for outbound messages -->
                            <template v-if="msg.direction === 'outbound'">
                                <svg v-if="msg.status === 'pending'" class="w-3 h-3 text-stone-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" title="Pending">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <svg v-else-if="msg.status === 'sent'" class="w-3.5 h-3.5 text-stone-400" viewBox="0 0 16 16" fill="currentColor" title="Sent">
                                    <path d="M13.854 4.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 .708-.708L6.5 10.793l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                                </svg>
                                <span v-else-if="msg.status === 'delivered'" class="flex -space-x-1.5" title="Delivered">
                                    <svg class="w-3.5 h-3.5 text-stone-400" viewBox="0 0 16 16" fill="currentColor"><path d="M13.854 4.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 .708-.708L6.5 10.793l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg>
                                    <svg class="w-3.5 h-3.5 text-stone-400" viewBox="0 0 16 16" fill="currentColor"><path d="M13.854 4.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 .708-.708L6.5 10.793l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg>
                                </span>
                                <span v-else-if="msg.status === 'read'" class="flex -space-x-1.5" title="Read">
                                    <svg class="w-3.5 h-3.5 text-blue-400" viewBox="0 0 16 16" fill="currentColor"><path d="M13.854 4.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 .708-.708L6.5 10.793l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg>
                                    <svg class="w-3.5 h-3.5 text-blue-400" viewBox="0 0 16 16" fill="currentColor"><path d="M13.854 4.146a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 .708-.708L6.5 10.793l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg>
                                </span>
                                <svg v-else-if="msg.status === 'failed'" class="w-3 h-3 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" title="Failed to send">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </template>
                        </span>
                    </div>
                </div>

                <!-- Composer: Reply + Internal Note -->
                <div
                    class="p-2.5 sm:p-4 flex-shrink-0 border-t bg-white transition-colors"
                    :class="isNoteMode ? 'border-amber-200 bg-amber-50/40' : 'border-[#e8e2d9]'"
                >
                    <!-- Mode toggle tabs -->
                    <div class="flex gap-1 mb-2">
                        <button
                            type="button"
                            @click="isNoteMode = false"
                            class="flex-1 py-1 rounded-md text-[11px] font-semibold transition-colors"
                            :class="!isNoteMode ? 'bg-[#4a3324] text-white' : 'text-stone-500 hover:text-[#241e19] hover:bg-stone-100'"
                        >
                            Reply to Customer
                        </button>
                        <button
                            type="button"
                            @click="isNoteMode = true"
                            class="flex-1 py-1 rounded-md text-[11px] font-semibold transition-colors flex items-center justify-center gap-1"
                            :class="isNoteMode ? 'bg-amber-500 text-white' : 'text-stone-500 hover:text-amber-700 hover:bg-amber-50'"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Internal Note
                        </button>
                    </div>

                    <!-- Reply form -->
                    <form v-if="!isNoteMode" @submit.prevent="sendReply" class="flex gap-2 items-end">
                        <textarea
                            v-model="replyForm.content"
                            @keydown.enter.exact.prevent="sendReply"
                            rows="1"
                            placeholder="Type a response..."
                            class="flex-1 text-xs rounded-md border border-[#e8e2d9] bg-[#faf8f5] text-[#241e19] focus:outline-none focus:border-[#7b5537] focus:ring-1 focus:ring-[#7b5537] resize-none p-2 sm:p-2.5 min-h-[38px] max-h-32"
                        ></textarea>
                        <button
                            type="submit"
                            :disabled="replyForm.processing || !replyForm.content.trim()"
                            class="btn-primary py-2 px-3 sm:px-4 disabled:opacity-40 flex items-center justify-center flex-shrink-0"
                        >
                            <span>Send</span>
                        </button>
                    </form>

                    <!-- Note form -->
                    <form v-else @submit.prevent="sendNote" class="flex gap-2 items-end">
                        <textarea
                            v-model="noteForm.content"
                            @keydown.enter.exact.prevent="sendNote"
                            rows="2"
                            placeholder="Write a note for your team (not sent to customer)..."
                            class="flex-1 text-xs rounded-md border border-amber-300 bg-amber-50 text-amber-900 placeholder-amber-400 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-300 resize-none p-2 sm:p-2.5 min-h-[38px] max-h-32"
                        ></textarea>
                        <button
                            type="submit"
                            :disabled="noteForm.processing || !noteForm.content.trim()"
                            class="py-2 px-3 sm:px-4 rounded-md bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold disabled:opacity-40 transition-colors flex-shrink-0"
                        >
                            Save Note
                        </button>
                    </form>

                    <div class="flex items-center justify-between mt-1.5 text-[10px] text-stone-400">
                        <span v-if="!isNoteMode" class="truncate">Enter to send · Staff handled</span>
                        <span v-else class="text-amber-600 font-medium">🔒 Visible to your team only</span>
                        <span v-if="activeConversation.handler === 'ai' && !isNoteMode" class="text-stone-500 font-medium ml-2 flex-shrink-0">
                            AI Active
                        </span>
                    </div>
                </div>
            </div>

            <!-- Empty: no conversation selected -->
            <div 
                v-else 
                class="flex-1 hidden md:flex items-center justify-center p-8 text-center bg-[#faf8f5]"
            >
                <div>
                    <div class="w-10 h-10 rounded-full bg-[#f5efe6] text-[#7b5537] flex items-center justify-center mx-auto mb-3">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-medium text-[#241e19] mb-1">Select a conversation</h3>
                    <p class="text-xs text-stone-500">Choose a thread from the left to read messages and reply.</p>
                </div>
            </div>

            <!-- Right: Interactive Products & Inventory / Customer sidebar (Desktop xl: screens) -->
            <div v-if="activeConversation" class="w-80 hidden xl:flex flex-col overflow-hidden flex-shrink-0 bg-white border-l border-[#e8e2d9]">
                <!-- Tab Headers -->
                <div class="flex border-b border-[#e8e2d9] bg-[#faf8f5] p-1.5 gap-1 flex-shrink-0">
                    <button
                        type="button"
                        @click="activeRightTab = 'catalog'"
                        :class="activeRightTab === 'catalog' ? 'bg-white shadow-xs font-semibold text-[#7b5537] border border-[#e8e2d9]' : 'text-stone-500 hover:text-stone-800'"
                        class="flex-1 py-1.5 px-2 text-xs rounded-md flex items-center justify-center gap-1.5 transition-all"
                    >
                        <span>📦 Products</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-stone-200 text-stone-700 font-mono">
                            {{ catalogItems.length }}
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="activeRightTab = 'customer'"
                        :class="activeRightTab === 'customer' ? 'bg-white shadow-xs font-semibold text-[#7b5537] border border-[#e8e2d9]' : 'text-stone-500 hover:text-stone-800'"
                        class="flex-1 py-1.5 px-2 text-xs rounded-md flex items-center justify-center gap-1.5 transition-all"
                    >
                        <span>👤 Customer</span>
                    </button>
                </div>

                <!-- Tab 1: Products & Inventory Panel -->
                <div v-if="activeRightTab === 'catalog'" class="flex-1 flex flex-col overflow-y-auto p-4 space-y-4">
                    <!-- Top Search & Add Product Trigger -->
                    <div class="space-y-2 flex-shrink-0">
                        <div class="flex items-center gap-2">
                            <input
                                v-model="catalogSearch"
                                type="text"
                                placeholder="Search products & stock..."
                                class="flex-1 text-xs rounded-md border border-[#e8e2d9] bg-[#faf8f5] text-[#241e19] focus:outline-none focus:border-[#7b5537] px-2.5 py-1.5"
                            />
                            <button
                                @click="showAddProduct = !showAddProduct"
                                type="button"
                                class="btn-primary py-1.5 px-2.5 text-xs flex items-center gap-1 flex-shrink-0"
                            >
                                <span v-if="!showAddProduct">+ Add</span>
                                <span v-else>Close</span>
                            </button>
                        </div>
                    </div>

                    <!-- Quick Add Product Form -->
                    <div v-if="showAddProduct" class="p-3.5 rounded-lg border border-[#e8ded1] bg-[#fbf8f5] shadow-xs space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-semibold text-[#241e19]">New Product</h4>
                            <span class="text-[10px] text-stone-400">Directly adds to catalog</span>
                        </div>

                        <!-- Image Upload Box -->
                        <div>
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="onImageSelect"
                            />
                            <div
                                @click="$refs.fileInputRef && $refs.fileInputRef.click()"
                                class="h-24 rounded-md border-2 border-dashed border-[#d8c8b8] hover:border-[#7b5537] bg-white cursor-pointer flex flex-col items-center justify-center p-2 text-center transition-colors overflow-hidden relative group"
                            >
                                <img
                                    v-if="imagePreview"
                                    :src="imagePreview"
                                    class="w-full h-full object-contain"
                                    alt="Preview"
                                />
                                <div v-else class="text-stone-400 flex flex-col items-center gap-1">
                                    <span class="text-xl">📸</span>
                                    <span class="text-[11px] font-medium text-stone-600">Click to upload photo</span>
                                    <span class="text-[9px] text-stone-400">PNG, JPG, WebP up to 5MB</span>
                                </div>
                            </div>
                        </div>

                        <!-- Product Fields -->
                        <div class="space-y-2">
                            <div>
                                <label class="block text-[10px] uppercase font-semibold text-stone-500 mb-0.5">Product Name *</label>
                                <input
                                    v-model="productForm.name"
                                    type="text"
                                    placeholder="e.g. Leather Oxford Shoes"
                                    class="w-full text-xs rounded-md border border-[#e8e2d9] bg-white text-[#241e19] focus:outline-none focus:border-[#7b5537] px-2.5 py-1.5"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] uppercase font-semibold text-stone-500 mb-0.5">Price (NGN) *</label>
                                    <input
                                        v-model="productForm.price"
                                        type="number"
                                        step="0.01"
                                        placeholder="25000"
                                        class="w-full text-xs rounded-md border border-[#e8e2d9] bg-white text-[#241e19] focus:outline-none focus:border-[#7b5537] px-2.5 py-1.5 font-mono"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase font-semibold text-stone-500 mb-0.5">Stock Count</label>
                                    <input
                                        v-model="productForm.stock_quantity"
                                        type="number"
                                        placeholder="10"
                                        class="w-full text-xs rounded-md border border-[#e8e2d9] bg-white text-[#241e19] focus:outline-none focus:border-[#7b5537] px-2.5 py-1.5 font-mono"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] uppercase font-semibold text-stone-500 mb-0.5">Category</label>
                                <input
                                    v-model="productForm.category"
                                    type="text"
                                    placeholder="e.g. Footwear / Accessories"
                                    class="w-full text-xs rounded-md border border-[#e8e2d9] bg-white text-[#241e19] focus:outline-none focus:border-[#7b5537] px-2.5 py-1.5"
                                />
                            </div>

                            <div>
                                <label class="block text-[10px] uppercase font-semibold text-stone-500 mb-0.5">Short Description</label>
                                <textarea
                                    v-model="productForm.description"
                                    rows="2"
                                    placeholder="Brief customer-facing details..."
                                    class="w-full text-xs rounded-md border border-[#e8e2d9] bg-white text-[#241e19] focus:outline-none focus:border-[#7b5537] px-2.5 py-1.5 resize-none"
                                ></textarea>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button
                                @click="cancelAddProduct"
                                type="button"
                                class="px-2.5 py-1.5 text-xs text-stone-600 hover:text-stone-800"
                            >
                                Cancel
                            </button>
                            <button
                                @click="submitQuickProduct"
                                :disabled="productForm.processing || !productForm.name || !productForm.price"
                                type="button"
                                class="btn-primary py-1.5 px-3 text-xs disabled:opacity-40"
                            >
                                Save Product
                            </button>
                        </div>
                    </div>

                    <!-- Products List -->
                    <div class="space-y-2.5 flex-1">
                        <div v-if="filteredCatalog.length === 0" class="text-center py-8 text-xs text-stone-400">
                            No products match. Click <strong>+ Add</strong> above to add one.
                        </div>

                        <div
                            v-for="item in filteredCatalog"
                            :key="item.id"
                            class="p-2.5 rounded-lg border border-[#e8e2d9] bg-[#faf8f5] hover:border-[#d8c8b8] transition-all space-y-2"
                        >
                            <div class="flex items-start gap-2.5">
                                <div class="w-12 h-12 rounded-md bg-stone-200 border border-stone-300 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                    <img
                                        v-if="item.image_url"
                                        :src="item.image_url"
                                        :alt="item.name"
                                        class="w-full h-full object-cover"
                                    />
                                    <span v-else class="text-xl">🛍️</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-xs text-[#241e19] truncate" :title="item.name">
                                        {{ item.name }}
                                    </div>
                                    <div class="font-semibold text-xs text-[#7b5537]">
                                        {{ item.formatted_price }}
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span
                                            :class="item.stock_quantity > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                            class="text-[9px] font-medium px-1.5 py-0.5 rounded-full"
                                        >
                                            {{ item.stock_quantity > 0 ? item.stock_quantity + ' in stock' : (item.stock_quantity === 0 ? 'Out of stock' : 'Available') }}
                                        </span>
                                        <span v-if="item.category" class="text-[9px] text-stone-400 truncate">
                                            {{ item.category }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Inventory Controls & Send to Chat -->
                            <div class="flex items-center justify-between pt-1.5 border-t border-[#e8e2d9]/60 gap-1.5">
                                <div class="flex items-center gap-1">
                                    <span class="text-[10px] text-stone-400">Stock:</span>
                                    <button
                                        @click="adjustStock(item, -1)"
                                        title="Decrease stock by 1"
                                        class="w-5 h-5 rounded bg-white border border-stone-300 text-stone-700 hover:bg-stone-100 flex items-center justify-center text-xs font-bold leading-none"
                                    >
                                        -
                                    </button>
                                    <span class="font-mono text-xs font-semibold px-1 text-stone-700">
                                        {{ item.stock_quantity ?? 0 }}
                                    </span>
                                    <button
                                        @click="adjustStock(item, 1)"
                                        title="Increase stock by 1"
                                        class="w-5 h-5 rounded bg-white border border-stone-300 text-stone-700 hover:bg-stone-100 flex items-center justify-center text-xs font-bold leading-none"
                                    >
                                        +
                                    </button>
                                </div>

                                <button
                                    @click="shareProduct(item)"
                                    title="Send product card & details to customer in chat"
                                    class="btn-primary py-1 px-2.5 text-[10px] flex items-center gap-1 flex-shrink-0"
                                >
                                    <span>Send</span>
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Customer Profile Panel -->
                <div v-else class="flex-1 overflow-y-auto p-5 space-y-5">
                    <div>
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-stone-500 mb-3">Customer Profile</h4>
                        <div class="space-y-2.5 text-xs">
                            <div>
                                <span class="block text-[10px] text-stone-400 uppercase tracking-wider">Full Name</span>
                                <span class="font-medium text-[#241e19]">{{ activeConversation.customer.name }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] text-stone-400 uppercase tracking-wider">Phone / Handle</span>
                                <span class="font-mono text-[#241e19]">{{ activeConversation.customer.phone || '—' }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] text-stone-400 uppercase tracking-wider mb-1">Status</span>
                                <span class="badge-neutral capitalize">
                                    {{ activeConversation.customer.lead_status || 'Customer' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Scheduled bookings -->
                    <div class="pt-4 border-t border-[#e8e2d9]">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-stone-500 mb-2">Bookings</h4>
                        <div v-if="!activeConversation.customer.appointments?.length" class="text-xs text-stone-400">
                            No appointments scheduled.
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="apt in activeConversation.customer.appointments"
                                :key="apt.id"
                                class="p-2.5 rounded-md bg-[#faf8f5] border border-[#e8e2d9] text-xs"
                            >
                                <div class="font-medium text-[#241e19] mb-0.5">{{ apt.title }}</div>
                                <div class="text-[10px] text-stone-500">{{ apt.scheduled_at }}</div>
                                <span class="badge-neutral text-[9px] mt-1.5">
                                    {{ apt.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide-over for Mobile & Tablet with Dual Tabs -->
            <div
                v-if="showCustomerDetails && activeConversation"
                class="xl:hidden fixed inset-0 z-40 flex justify-end bg-black/40 backdrop-blur-xs"
                @click.self="showCustomerDetails = false"
            >
                <div class="w-84 max-w-[90vw] h-full bg-white shadow-2xl flex flex-col overflow-hidden">
                    <div class="flex items-center justify-between p-3 border-b border-[#e8e2d9] bg-[#faf8f5] flex-shrink-0">
                        <div class="flex gap-1">
                            <button
                                @click="activeRightTab = 'catalog'"
                                :class="activeRightTab === 'catalog' ? 'bg-white shadow-xs font-semibold text-[#7b5537] border border-[#e8e2d9]' : 'text-stone-500'"
                                class="py-1 px-2.5 text-xs rounded-md transition-all"
                            >
                                📦 Products ({{ catalogItems.length }})
                            </button>
                            <button
                                @click="activeRightTab = 'customer'"
                                :class="activeRightTab === 'customer' ? 'bg-white shadow-xs font-semibold text-[#7b5537] border border-[#e8e2d9]' : 'text-stone-500'"
                                class="py-1 px-2.5 text-xs rounded-md transition-all"
                            >
                                👤 Customer
                            </button>
                        </div>
                        <button
                            @click="showCustomerDetails = false"
                            class="p-1.5 rounded-md text-stone-400 hover:text-stone-700 hover:bg-stone-100 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Mobile Products Tab -->
                    <div v-if="activeRightTab === 'catalog'" class="flex-1 overflow-y-auto p-4 space-y-4">
                        <div class="flex items-center gap-2">
                            <input
                                v-model="catalogSearch"
                                type="text"
                                placeholder="Search products..."
                                class="flex-1 text-xs rounded-md border border-[#e8e2d9] bg-[#faf8f5] text-[#241e19] px-2.5 py-1.5"
                            />
                            <button
                                @click="showAddProduct = !showAddProduct"
                                type="button"
                                class="btn-primary py-1.5 px-2.5 text-xs"
                            >
                                {{ showAddProduct ? 'Close' : '+ Add' }}
                            </button>
                        </div>

                        <!-- Mobile Add Form -->
                        <div v-if="showAddProduct" class="p-3 rounded-lg border border-[#e8ded1] bg-[#fbf8f5] space-y-2.5">
                            <h4 class="text-xs font-semibold text-[#241e19]">Quick Add Product</h4>
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/*"
                                class="w-full text-xs text-stone-600 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-[#7b5537] file:text-white"
                                @change="onImageSelect"
                            />
                            <input
                                v-model="productForm.name"
                                type="text"
                                placeholder="Product Name *"
                                class="w-full text-xs rounded-md border border-[#e8e2d9] px-2 py-1.5"
                            />
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    v-model="productForm.price"
                                    type="number"
                                    step="0.01"
                                    placeholder="Price *"
                                    class="w-full text-xs rounded-md border border-[#e8e2d9] px-2 py-1.5 font-mono"
                                />
                                <input
                                    v-model="productForm.stock_quantity"
                                    type="number"
                                    placeholder="Stock"
                                    class="w-full text-xs rounded-md border border-[#e8e2d9] px-2 py-1.5 font-mono"
                                />
                            </div>
                            <div class="flex justify-end gap-2 pt-1">
                                <button
                                    @click="cancelAddProduct"
                                    type="button"
                                    class="px-2 py-1 text-xs text-stone-600"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="submitQuickProduct"
                                    :disabled="productForm.processing || !productForm.name || !productForm.price"
                                    type="button"
                                    class="btn-primary py-1 px-3 text-xs"
                                >
                                    Save
                                </button>
                            </div>
                        </div>

                        <!-- Mobile Product Items -->
                        <div class="space-y-2">
                            <div
                                v-for="item in filteredCatalog"
                                :key="item.id"
                                class="p-2.5 rounded-lg border border-[#e8e2d9] bg-[#faf8f5] space-y-2"
                            >
                                <div class="flex items-center gap-2">
                                    <div class="w-10 h-10 rounded bg-stone-200 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                        <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover" />
                                        <span v-else class="text-base">🛍️</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-xs text-[#241e19] truncate">{{ item.name }}</div>
                                        <div class="font-semibold text-xs text-[#7b5537]">{{ item.formatted_price }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-1 border-t border-[#e8e2d9]/60">
                                    <div class="flex items-center gap-1">
                                        <button @click="adjustStock(item, -1)" class="w-5 h-5 rounded bg-white border border-stone-300 text-xs font-bold leading-none">-</button>
                                        <span class="font-mono text-xs px-1">{{ item.stock_quantity ?? 0 }}</span>
                                        <button @click="adjustStock(item, 1)" class="w-5 h-5 rounded bg-white border border-stone-300 text-xs font-bold leading-none">+</button>
                                    </div>
                                    <button
                                        @click="shareProduct(item); showCustomerDetails = false;"
                                        class="btn-primary py-1 px-2.5 text-[10px] flex items-center gap-1"
                                    >
                                        Send to Chat ↗
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Customer Tab -->
                    <div v-else class="flex-1 overflow-y-auto p-5 space-y-5 text-xs">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-[#f5efe6] text-[#4a3324] border border-[#e8e2d9] flex items-center justify-center font-semibold text-sm flex-shrink-0">
                                {{ activeConversation.customer.name[0]?.toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-semibold text-sm text-[#241e19] truncate">{{ activeConversation.customer.name }}</h4>
                                <span class="badge-neutral text-[10px] capitalize">
                                    {{ activeConversation.customer.lead_status || 'Customer' }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2.5 pt-2 border-t border-[#e8e2d9]">
                            <div>
                                <span class="block text-[10px] text-stone-400 uppercase tracking-wider">Phone / Handle</span>
                                <span class="font-mono text-[#241e19]">{{ activeConversation.customer.phone || '—' }}</span>
                            </div>
                            <div v-if="activeConversation.customer.email">
                                <span class="block text-[10px] text-stone-400 uppercase tracking-wider">Email</span>
                                <span class="text-[#241e19]">{{ activeConversation.customer.email }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] text-stone-400 uppercase tracking-wider">Channel</span>
                                <span class="font-medium text-[#241e19] uppercase">{{ activeConversation.channel }}</span>
                            </div>
                        </div>

                        <!-- Bookings -->
                        <div class="pt-4 border-t border-[#e8e2d9]">
                            <h4 class="text-[10px] font-semibold uppercase tracking-wider text-stone-500 mb-2">Bookings</h4>
                            <div v-if="!activeConversation.customer.appointments?.length" class="text-xs text-stone-400">
                                No appointments scheduled.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="apt in activeConversation.customer.appointments"
                                    :key="apt.id"
                                    class="p-2.5 rounded-md bg-[#faf8f5] border border-[#e8e2d9] text-xs"
                                >
                                    <div class="font-medium text-[#241e19] mb-0.5">{{ apt.title }}</div>
                                    <div class="text-[10px] text-stone-500">{{ apt.scheduled_at }}</div>
                                    <span class="badge-neutral text-[9px] mt-1.5">
                                        {{ apt.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

