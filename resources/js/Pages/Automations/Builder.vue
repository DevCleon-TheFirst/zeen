<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

// ─── Constants ────────────────────────────────────────────────────────────────
const NODE_W = 220;  // card pixel width — must match w-[220px] on the node div
const NODE_H = 80;   // approximate card height (port is at top-8 = 2rem = 32px)
const PORT_Y_OFFSET = 32; // distance from node top to port center

const props = defineProps({
    workflow: Object,
    nodeTypes: Object,
    businessPlan: Object,
    currentBusiness: Object,
    allBusinesses: {
        type: Array,
        default: () => [],
    },
    isSuperAdmin: {
        type: Boolean,
        default: false,
    },
});

const isEditing = computed(() => !!props.workflow?.id);

const showTemplateModal = ref(false);
const templateForm = useForm({
    name: props.workflow?.name || '',
    industry: 'general',
    description: props.workflow?.description || '',
});

const openSaveAsTemplate = () => {
    templateForm.name = form.name;
    templateForm.description = form.description;
    showTemplateModal.value = true;
};

const submitSaveTemplate = () => {
    if (!props.workflow?.id) return;
    templateForm.post(route('automations.saveAsTemplate', props.workflow.id), {
        onSuccess: () => {
            showTemplateModal.value = false;
        },
    });
};

const form = useForm({
    name: props.workflow?.name || 'Untitled Automation',
    description: props.workflow?.description || '',
    trigger_type: props.workflow?.trigger_type || 'new_message',
    trigger_config: props.workflow?.trigger_config || {},
    is_active: props.workflow?.is_active ?? false,
    business_id: props.workflow?.business_id || props.currentBusiness?.id || (props.allBusinesses[0]?.id ?? null),
    nodes: props.workflow?.nodes || [
        {
            id: 'node_1',
            type: 'trigger_whatsapp',
            label: 'Incoming WhatsApp Message',
            position: { x: 80, y: 120 },
            config: { channel: 'whatsapp' },
        },
        {
            id: 'node_2',
            type: 'ai_intent',
            label: 'Intent Analysis',
            position: { x: 380, y: 120 },
            config: { prompt: 'Analyze customer intent and recommend actions' },
        },
        {
            id: 'node_3',
            type: 'action_search_catalog',
            label: 'Search Catalog & Reply',
            position: { x: 680, y: 120 },
            config: { query: 'available products' },
        },
    ],
    edges: props.workflow?.edges || [
        { id: 'edge_1_2', source: 'node_1', target: 'node_2', label: 'Next' },
        { id: 'edge_2_3', source: 'node_2', target: 'node_3', label: 'Match' },
    ],
});

const selectedNodeId = ref(null);
const draggingNodeId = ref(null);
const dragOffset = ref({ x: 0, y: 0 });
const connectTargetId = ref('');

// ─── Port-to-port wire drawing state ─────────────────────────────────────────
const wiringFromId = ref(null);    // source node id when dragging a wire
const wireEnd = ref(null);          // { x, y } current mouse position in canvas coords
const canvasEl = ref(null);         // ref to the canvas scroll container inner div
const hoveredPortNodeId = ref(null); // node id the wire-end is hovering over

const selectedNode = computed(() => {
    const node = form.nodes.find((n) => n.id === selectedNodeId.value);
    if (node && !node.config) {
        node.config = {};
    }
    return node || null;
});

const saveWorkflow = () => {
    // Sync trigger config from first trigger node if available
    const triggerNode = form.nodes.find((n) => n.type && n.type.startsWith('trigger_'));
    if (triggerNode && triggerNode.config) {
        form.trigger_config = { ...form.trigger_config, ...triggerNode.config };
    }

    if (isEditing.value) {
        form.put(route('automations.update', props.workflow.id), { preserveScroll: true });
    } else {
        form.post(route('automations.store'));
    }
};

const getDefaultConfigForType = (type, desc) => {
    if (type === 'delay') return { delay_minutes: 15 };
    if (type === 'action_http_request') return { method: 'POST', url: '', headers: '{\n  "Content-Type": "application/json"\n}', body: '{\n  "name": "{{customer.name}}"\n}' };
    if (type === 'action_send_email') return { to: '{{customer.email}}', subject: 'Follow up', body: 'Hello {{customer.name}},\n\n' };
    if (type === 'action_send_message') return { message: 'Hello {{customer.name}}!' };
    if (type === 'action_add_tag') return { tag: 'VIP' };
    if (type === 'action_update_lead') return { field: 'stage', value: 'qualified' };
    if (type === 'action_assign_staff') return { strategy: 'round_robin' };
    if (type === 'action_update_conversation') return { status: 'resolved', priority: 'medium' };
    if (type === 'condition_check_field') return { subject: 'customer', field: 'tags', operator: 'contains', value: 'VIP' };
    if (type === 'condition_time_window') return { from: '09:00', to: '17:00', timezone: 'UTC' };
    if (type === 'action_search_catalog') return { query: '', limit: 5 };
    if (type === 'ai_intent') {
        return {
            intents: [
                { name: 'inquire', description: 'Customer asking questions or requesting product information' },
                { name: 'order', description: 'Customer wants to buy, place an order, or checkout' },
            ],
            default_intent: 'inquire',
            prompt: 'Classify customer inquiry intent',
        };
    }
    if (type.startsWith('trigger_')) {
        return {
            keyword_filter: '',
            keyword_match_mode: 'contains',
            channel_filter: type.replace('trigger_', ''),
        };
    }
    return { description: desc || '' };
};

const addNodeFromPalette = (item) => {
    const newId = 'node_' + Math.random().toString(36).substring(2, 9);
    const offsetCount = form.nodes.length;

    // Auto-sync workflow trigger_type when adding a trigger node
    if (item.type.startsWith('trigger_')) {
        const triggerMap = {
            trigger_order: 'order_created',
            trigger_abandoned_checkout: 'abandoned_checkout',
            trigger_inventory_low: 'inventory_low',
            trigger_inventory_out_of_stock: 'inventory_out_of_stock',
            trigger_payment: 'payment_received',
            trigger_appointment: 'appointment_created',
            trigger_lead: 'lead_stage_changed',
            trigger_scheduled: 'scheduled',
            trigger_webhook: 'webhook_received',
            trigger_whatsapp: 'new_message',
            trigger_telegram: 'new_message',
            trigger_messenger: 'new_message',
        };
        if (triggerMap[item.type]) {
            form.trigger_type = triggerMap[item.type];
        }
    }

    form.nodes.push({
        id: newId,
        type: item.type,
        label: item.label,
        position: { x: 100 + (offsetCount % 4) * 60, y: 120 + (offsetCount % 5) * 50 },
        config: getDefaultConfigForType(item.type, item.desc),
    });
    selectedNodeId.value = newId;
};

const removeSelectedNode = () => {
    if (!selectedNodeId.value) return;
    const id = selectedNodeId.value;
    form.nodes = form.nodes.filter((n) => n.id !== id);
    form.edges = form.edges.filter((e) => e.source !== id && e.target !== id);
    selectedNodeId.value = null;
};

const addEdgeConnection = () => {
    if (!selectedNodeId.value || !connectTargetId.value) return;
    if (selectedNodeId.value === connectTargetId.value) return;
    const exists = form.edges.some((e) => e.source === selectedNodeId.value && e.target === connectTargetId.value);
    if (!exists) {
        form.edges.push({
            id: `edge_${selectedNodeId.value}_${connectTargetId.value}`,
            source: selectedNodeId.value,
            target: connectTargetId.value,
            label: 'Next',
        });
    }
    connectTargetId.value = '';
};

const removeEdge = (edgeId) => {
    form.edges = form.edges.filter((e) => e.id !== edgeId);
};

// ─── Node card dragging ───────────────────────────────────────────────────────
const startDrag = (node, event) => {
    // Ignore if clicking on a port handle (those have their own handler)
    if (event.target.closest('[data-port]')) return;
    selectedNodeId.value = node.id;
    draggingNodeId.value = node.id;
    const canvasRect = event.currentTarget.parentElement.getBoundingClientRect();
    dragOffset.value = {
        x: event.clientX - (node.position.x + canvasRect.left),
        y: event.clientY - (node.position.y + canvasRect.top),
    };
    window.addEventListener('mousemove', onDrag);
    window.addEventListener('mouseup', stopDrag);
};

const onDrag = (event) => {
    if (!draggingNodeId.value) return;
    const node = form.nodes.find((n) => n.id === draggingNodeId.value);
    if (!node) return;
    node.position.x = Math.max(20, Math.min(1800, node.position.x + event.movementX));
    node.position.y = Math.max(20, Math.min(1200, node.position.y + event.movementY));
};

const stopDrag = () => {
    draggingNodeId.value = null;
    window.removeEventListener('mousemove', onDrag);
    window.removeEventListener('mouseup', stopDrag);
};

// ─── Port-to-port wiring ──────────────────────────────────────────────────────
/**
 * Get the absolute canvas-space coordinates of a node's output (right) port.
 */
const outputPortPos = (node) => ({
    x: node.position.x + NODE_W,
    y: node.position.y + PORT_Y_OFFSET,
});

/**
 * Get the absolute canvas-space coordinates of a node's input (left) port.
 */
const inputPortPos = (node) => ({
    x: node.position.x,
    y: node.position.y + PORT_Y_OFFSET,
});

/**
 * Build a smooth cubic bezier path string between two canvas points.
 */
const bezierPath = (x1, y1, x2, y2) => {
    const dx = Math.abs(x2 - x1);
    const cp = Math.max(60, dx * 0.45);
    return `M ${x1} ${y1} C ${x1 + cp} ${y1}, ${x2 - cp} ${y2}, ${x2} ${y2}`;
};

/**
 * Start drawing a wire from the output port of a node.
 */
const startWiring = (node, event) => {
    event.stopPropagation();
    event.preventDefault();
    wiringFromId.value = node.id;
    const canvasRect = canvasEl.value?.getBoundingClientRect();
    if (!canvasRect) return;
    wireEnd.value = {
        x: event.clientX - canvasRect.left,
        y: event.clientY - canvasRect.top,
    };
    window.addEventListener('mousemove', onWireDrag);
    window.addEventListener('mouseup', stopWiring);
};

const onWireDrag = (event) => {
    if (!wiringFromId.value) return;
    const canvasRect = canvasEl.value?.getBoundingClientRect();
    if (!canvasRect) return;
    const mx = event.clientX - canvasRect.left;
    const my = event.clientY - canvasRect.top;
    wireEnd.value = { x: mx, y: my };

    // Detect which node's input port the cursor is hovering near (snap zone: 20px)
    const SNAP_RADIUS = 20;
    const hovered = form.nodes.find((n) => {
        if (n.id === wiringFromId.value) return false;
        const ip = inputPortPos(n);
        return Math.abs(mx - ip.x) < SNAP_RADIUS && Math.abs(my - ip.y) < SNAP_RADIUS;
    });
    hoveredPortNodeId.value = hovered?.id ?? null;

    // If snapping, lock wireEnd to port center
    if (hovered) {
        const ip = inputPortPos(hovered);
        wireEnd.value = { x: ip.x, y: ip.y };
    }
};

const stopWiring = (event) => {
    if (!wiringFromId.value) return;

    const targetId = hoveredPortNodeId.value;
    if (targetId && targetId !== wiringFromId.value) {
        const exists = form.edges.some(
            (e) => e.source === wiringFromId.value && e.target === targetId,
        );
        if (!exists) {
            form.edges.push({
                id: `edge_${wiringFromId.value}_${targetId}`,
                source: wiringFromId.value,
                target: targetId,
                label: '',
            });
        }
    }

    wiringFromId.value = null;
    wireEnd.value = null;
    hoveredPortNodeId.value = null;
    window.removeEventListener('mousemove', onWireDrag);
    window.removeEventListener('mouseup', stopWiring);
};

// Live ghost wire path (shown while dragging from an output port)
const ghostWirePath = computed(() => {
    if (!wiringFromId.value || !wireEnd.value) return null;
    const src = form.nodes.find((n) => n.id === wiringFromId.value);
    if (!src) return null;
    const op = outputPortPos(src);
    return bezierPath(op.x, op.y, wireEnd.value.x, wireEnd.value.y);
});

// Saved edge bezier path
const edgePath = (edge) => {
    const src = getNodeById(edge.source);
    const tgt = getNodeById(edge.target);
    if (!src || !tgt) return '';
    const op = outputPortPos(src);
    const ip = inputPortPos(tgt);
    return bezierPath(op.x, op.y, ip.x, ip.y);
};

// Label midpoint for edge label rendering
const edgeMidpoint = (edge) => {
    const src = getNodeById(edge.source);
    const tgt = getNodeById(edge.target);
    if (!src || !tgt) return { x: 0, y: 0 };
    const op = outputPortPos(src);
    const ip = inputPortPos(tgt);
    return { x: (op.x + ip.x) / 2, y: (op.y + ip.y) / 2 - 8 };
};

const getNodeById = (id) => form.nodes.find((n) => n.id === id);

const getNodeAccent = (type) => {
    if (type.startsWith('trigger_whatsapp')) return { badge: 'bg-emerald-100 text-emerald-800', bar: 'bg-emerald-600' };
    if (type.startsWith('trigger_telegram')) return { badge: 'bg-sky-100 text-sky-800', bar: 'bg-sky-600' };
    if (type.startsWith('trigger_messenger')) return { badge: 'bg-blue-100 text-blue-800', bar: 'bg-blue-600' };
    if (type === 'trigger_scheduled' || type === 'delay') return { badge: 'bg-purple-100 text-purple-800', bar: 'bg-purple-600' };
    if (type === 'action_send_email') return { badge: 'bg-rose-100 text-rose-800', bar: 'bg-rose-600' };
    if (type === 'action_http_request' || type === 'trigger_webhook') return { badge: 'bg-indigo-100 text-indigo-800', bar: 'bg-indigo-600' };
    if (type.startsWith('trigger')) return { badge: 'bg-stone-100 text-stone-800', bar: 'bg-stone-700' };
    if (type.startsWith('ai')) return { badge: 'bg-[#f5efe6] text-[#4a3324]', bar: 'bg-[#4a3324]' };
    if (type.startsWith('condition')) return { badge: 'bg-amber-100 text-amber-800', bar: 'bg-amber-600' };
    return { badge: 'bg-stone-100 text-stone-700', bar: 'bg-[#7b5537]' };
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Workflow Builder — ${form.name}`" />

        <!-- Studio Sub-Header -->
        <div class="px-6 h-12 flex items-center justify-between sticky top-14 z-20 bg-white border-b border-[#e8e2d9]">
            <div class="flex items-center gap-3">
                <Link
                    :href="route('automations.index')"
                    class="text-xs text-stone-500 hover:text-stone-900 flex items-center gap-1 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    <span>Back</span>
                </Link>
                <div class="h-4 w-px bg-[#e8e2d9]"></div>
                <input
                    type="text"
                    v-model="form.name"
                    class="text-xs font-semibold text-[#241e19] bg-transparent border-0 border-b border-transparent hover:border-[#e8e2d9] focus:border-[#7b5537] focus:ring-0 px-1 py-0.5 w-44 transition-colors"
                    placeholder="Workflow Name"
                />
                <div class="h-4 w-px bg-[#e8e2d9]"></div>
                <!-- Super Admin Target Store Selector -->
                <div v-if="isSuperAdmin && allBusinesses.length > 0" class="flex items-center gap-1.5">
                    <span class="text-[10px] uppercase font-bold text-amber-800 tracking-wider">Store:</span>
                    <select
                        v-model="form.business_id"
                        class="text-xs rounded-md border-[#e8e2d9] bg-amber-50/50 text-[#241e19] py-0.5 px-2 shadow-xs focus:border-[#7b5537] focus:ring-1 focus:ring-[#7b5537] max-w-[140px]"
                    >
                        <option v-for="b in allBusinesses" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                </div>
                <div v-if="isSuperAdmin && allBusinesses.length > 0" class="h-4 w-px bg-[#e8e2d9]"></div>
                <div class="flex items-center gap-1.5">
                    <span class="text-[10px] uppercase font-bold text-stone-500 tracking-wider">Trigger:</span>
                    <select
                        v-model="form.trigger_type"
                        class="text-xs rounded-md border-[#e8e2d9] bg-stone-50 text-[#241e19] py-0.5 px-2 shadow-xs focus:border-[#7b5537] focus:ring-1 focus:ring-[#7b5537]"
                    >
                        <option value="new_message">Incoming Message</option>
                        <option value="order_created">Order Placed / Paid</option>
                        <option value="order_status_updated">Order Status Changed</option>
                        <option value="abandoned_checkout">Abandoned Checkout</option>
                        <option value="inventory_low">Low Stock Warning</option>
                        <option value="inventory_out_of_stock">Out of Stock Alert</option>
                        <option value="payment_received">Payment Received</option>
                        <option value="appointment_created">Appointment Booked</option>
                        <option value="lead_stage_changed">Lead Stage Changed</option>
                        <option value="scheduled">Schedule (Cron)</option>
                        <option value="webhook_received">Inbound Webhook</option>
                        <option value="conversation_resolved">Conversation Resolved</option>
                    </select>
                </div>
                <Link
                    v-if="isEditing"
                    :href="route('automations.executions', workflow.id)"
                    class="text-xs font-medium text-[#7b5537] hover:underline flex items-center gap-1 ml-2 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Logs</span>
                </Link>
            </div>

            <div class="flex items-center gap-2.5">
                <!-- Super Admin: Save as Platform Blueprint -->
                <button
                    v-if="isSuperAdmin && isEditing"
                    @click="openSaveAsTemplate"
                    type="button"
                    class="text-xs text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 font-medium px-2.5 py-1 rounded-md transition-colors cursor-pointer"
                >
                    Publish as Blueprint
                </button>
                <!-- Active status switch -->
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        v-model="form.is_active"
                        class="sr-only"
                    />
                    <div
                        class="w-7 h-4 rounded-full transition-colors relative"
                        :class="form.is_active ? 'bg-emerald-600' : 'bg-stone-300'"
                    >
                        <div
                            class="w-3 h-3 rounded-full bg-white absolute top-0.5 left-0.5 transition-transform"
                            :class="form.is_active ? 'translate-x-3' : 'translate-x-0'"
                        ></div>
                    </div>
                    <span class="text-xs text-stone-600 font-medium">{{ form.is_active ? 'Active' : 'Draft' }}</span>
                </label>

                <button
                    @click="saveWorkflow"
                    :disabled="form.processing"
                    class="btn-primary"
                >
                    Save Workflow
                </button>
            </div>
        </div>

        <!-- Studio Body -->
        <div class="flex overflow-hidden" style="height:calc(100vh - 104px);">

            <!-- Left: Node Palette -->
            <div class="w-60 flex flex-col flex-shrink-0 z-10 overflow-y-auto bg-white border-r border-[#e8e2d9]">
                <div class="px-4 py-3 border-b border-[#e8e2d9]">
                    <h3 class="text-xs font-semibold text-[#241e19]">Node Library</h3>
                    <p class="text-[11px] text-stone-500">Click to add a step to the canvas</p>
                </div>

                <div class="p-3 space-y-4">
                    <div v-for="(items, category) in nodeTypes" :key="category">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-stone-500 mb-1.5 capitalize">
                            {{ category === 'flow_control' ? 'Flow & Logic' : (category === 'ai_logic' ? 'AI Reasoning' : category) }}
                        </h4>
                        <div class="space-y-1">
                            <button
                                v-for="item in items"
                                :key="item.type"
                                @click="addNodeFromPalette(item)"
                                type="button"
                                class="w-full text-left p-2 rounded-md border border-[#e8e2d9] bg-[#faf8f5] hover:bg-white hover:border-[#7b5537] text-xs transition-colors"
                            >
                                <div class="font-medium text-[#241e19]">{{ item.label }}</div>
                                <div class="text-[10px] text-stone-500 mt-0.5 leading-tight">{{ item.desc }}</div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Center: Canvas -->
            <div
                class="flex-1 relative overflow-auto bg-[#faf8f5]"
                style="background-image:radial-gradient(rgba(120,78,45,0.12) 1px, transparent 1px);background-size:20px 20px;"
            >
                <div ref="canvasEl" class="min-w-[2000px] min-h-[1400px] relative">

                    <!-- SVG layer: saved edges + ghost wire -->
                    <svg class="absolute inset-0 w-full h-full z-0" style="pointer-events:none;">
                        <defs>
                            <marker id="arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="5" markerHeight="5" orient="auto">
                                <path d="M 0 1.5 L 8 5 L 0 8.5 z" fill="#6b4b35" />
                            </marker>
                            <marker id="arrow-ghost" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="5" markerHeight="5" orient="auto">
                                <path d="M 0 1.5 L 8 5 L 0 8.5 z" fill="#7b5537" opacity="0.5" />
                            </marker>
                        </defs>

                        <!-- Saved edges -->
                        <g v-for="edge in form.edges" :key="edge.id">
                            <!-- Invisible wide hit area for clicking edge label -->
                            <path
                                v-if="getNodeById(edge.source) && getNodeById(edge.target)"
                                :d="edgePath(edge)"
                                fill="none"
                                stroke="transparent"
                                stroke-width="12"
                                style="pointer-events:stroke;"
                            />
                            <!-- Visible bezier cord -->
                            <path
                                v-if="getNodeById(edge.source) && getNodeById(edge.target)"
                                :d="edgePath(edge)"
                                fill="none"
                                stroke="#7b5537"
                                stroke-width="1.75"
                                stroke-dasharray="none"
                                marker-end="url(#arrow)"
                            />
                            <!-- Edge label badge -->
                            <g
                                v-if="getNodeById(edge.source) && getNodeById(edge.target) && edge.label"
                                style="pointer-events:all;cursor:pointer;"
                            >
                                <rect
                                    :x="edgeMidpoint(edge).x - 22"
                                    :y="edgeMidpoint(edge).y - 8"
                                    width="44"
                                    height="16"
                                    rx="8"
                                    fill="#fff"
                                    stroke="#d4b99a"
                                    stroke-width="1"
                                />
                                <text
                                    :x="edgeMidpoint(edge).x"
                                    :y="edgeMidpoint(edge).y + 4"
                                    text-anchor="middle"
                                    fill="#4a3324"
                                    font-size="9"
                                    font-weight="600"
                                    @click="removeEdge(edge.id)"
                                >
                                    {{ edge.label.length > 6 ? edge.label.slice(0, 5) + '…' : edge.label }} ✕
                                </text>
                            </g>
                        </g>

                        <!-- Ghost wire while drawing a new connection -->
                        <path
                            v-if="ghostWirePath"
                            :d="ghostWirePath"
                            fill="none"
                            stroke="#7b5537"
                            stroke-width="1.5"
                            stroke-dasharray="6 3"
                            opacity="0.65"
                            marker-end="url(#arrow-ghost)"
                        />
                    </svg>

                    <!-- Nodes -->
                    <div
                        v-for="node in form.nodes"
                        :key="node.id"
                        @mousedown="startDrag(node, $event)"
                        class="absolute w-[220px] rounded-lg cursor-move select-none z-10 bg-white border shadow-xs transition-shadow overflow-visible"
                        :class="[
                            selectedNodeId === node.id ? 'border-[#4a3324] ring-2 ring-[#4a3324]/10 shadow-md' : 'border-[#e8e2d9]',
                            hoveredPortNodeId === node.id ? 'border-[#7b5537] ring-2 ring-[#7b5537]/20' : '',
                        ]"
                        :style="`left:${node.position.x}px;top:${node.position.y}px;`"
                    >
                        <!-- Node accent bar -->
                        <div class="h-1 w-full rounded-t-lg" :class="getNodeAccent(node.type).bar"></div>

                        <!-- Node body -->
                        <div class="px-3 pt-2.5 pb-3">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10px] px-1.5 py-0.5 rounded-sm font-mono font-semibold" :class="getNodeAccent(node.type).badge">
                                    {{ node.type.split('_').slice(0, 1).join('') }}
                                </span>
                                <span class="text-[10px] text-stone-400 font-mono">#{{ node.id.slice(-4) }}</span>
                            </div>
                            <p class="text-xs font-semibold text-[#241e19] leading-snug truncate">{{ node.label }}</p>
                            <p class="text-[10px] text-stone-400 truncate mt-0.5">
                                {{ node.config?.message?.slice(0, 30) || node.config?.prompt?.slice(0, 30) || node.config?.query?.slice(0, 30) || 'Configured' }}
                            </p>
                        </div>

                        <!-- Input port (left) — visual only, connections snap here -->
                        <div
                            class="absolute -left-2.5 top-7 w-5 h-5 flex items-center justify-center z-20"
                            title="Input port"
                        >
                            <div class="w-3 h-3 rounded-full bg-white border-2 border-[#7b5537] shadow"></div>
                        </div>

                        <!-- Output port (right) — drag from here to draw a wire -->
                        <div
                            data-port="output"
                            class="absolute -right-2.5 top-7 w-5 h-5 flex items-center justify-center z-20 cursor-crosshair group"
                            title="Drag to connect"
                            @mousedown.stop="startWiring(node, $event)"
                        >
                            <div class="w-3 h-3 rounded-full bg-[#7b5537] border-2 border-[#7b5537] shadow transition-all group-hover:scale-150 group-hover:bg-[#4a3324]"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Inspector -->
            <div class="w-72 flex flex-col flex-shrink-0 z-10 overflow-y-auto bg-white border-left border-[#e8e2d9]">
                <div class="px-4 py-3 flex items-center justify-between border-b border-[#e8e2d9]">
                    <div>
                        <h3 class="text-xs font-semibold text-[#241e19]">Node Inspector</h3>
                        <p class="text-[11px] text-stone-500">Configure parameters</p>
                    </div>
                    <button
                        v-if="selectedNode"
                        @click="removeSelectedNode"
                        type="button"
                        class="text-[11px] text-red-600 hover:text-red-800 transition-colors"
                    >
                        Delete
                    </button>
                </div>

                <div v-if="selectedNode" class="p-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-[#241e19] mb-1">Title</label>
                        <input
                            type="text"
                            v-model="selectedNode.label"
                            class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                        />
                    </div>

                    <!-- Delay Node Inspector -->
                    <div v-if="selectedNode.type === 'delay'" class="space-y-2">
                        <label class="block text-xs font-medium text-[#241e19]">Wait Duration (Minutes)</label>
                        <input
                            type="number"
                            min="1"
                            v-model.number="selectedNode.config.delay_minutes"
                            class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                            placeholder="15"
                        />
                        <div class="flex items-center gap-1.5 pt-1">
                            <button
                                v-for="mins in [5, 15, 60, 1440]"
                                :key="mins"
                                type="button"
                                @click="selectedNode.config.delay_minutes = mins"
                                class="text-[10px] px-2 py-0.5 rounded bg-stone-100 text-stone-700 hover:bg-stone-200 transition-colors"
                            >
                                {{ mins < 60 ? `${mins}m` : mins === 60 ? '1h' : '1d' }}
                            </button>
                        </div>
                    </div>

                    <!-- HTTP Webhook Node Inspector -->
                    <div v-else-if="selectedNode.type === 'action_http_request'" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">HTTP Method</label>
                            <select
                                v-model="selectedNode.config.method"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                            >
                                <option value="POST">POST</option>
                                <option value="GET">GET</option>
                                <option value="PUT">PUT</option>
                                <option value="PATCH">PATCH</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Target URL</label>
                            <input
                                type="url"
                                v-model="selectedNode.config.url"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] font-mono shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="https://api.crm.com/webhook"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Headers (JSON)</label>
                            <textarea
                                v-model="selectedNode.config.headers"
                                rows="2"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20 font-mono"
                                placeholder='{"Authorization": "Bearer TOKEN"}'
                            ></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Payload Body (JSON)</label>
                            <textarea
                                v-model="selectedNode.config.body"
                                rows="3"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20 font-mono"
                                placeholder='{"name": "{{customer.name}}"}'
                            ></textarea>
                            <p class="text-[10px] text-stone-500 mt-1">Variables: &#123;&#123;customer.name&#125;&#125;, &#123;&#123;customer.email&#125;&#125;</p>
                        </div>
                    </div>

                    <!-- Send Email Node Inspector -->
                    <div v-else-if="selectedNode.type === 'action_send_email'" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Recipient</label>
                            <input
                                type="text"
                                v-model="selectedNode.config.to"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="{{customer.email}}"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Subject</label>
                            <input
                                type="text"
                                v-model="selectedNode.config.subject"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="Follow-up on inquiry"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Body Text</label>
                            <textarea
                                v-model="selectedNode.config.body"
                                rows="4"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="Hi {{customer.name}}..."
                            ></textarea>
                        </div>
                    </div>

                    <!-- Send Message Node Inspector -->
                    <div v-else-if="selectedNode.type === 'action_send_message'" class="space-y-2">
                        <label class="block text-xs font-medium text-[#241e19]">Message Content</label>
                        <textarea
                            v-model="selectedNode.config.message"
                            rows="4"
                            class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                            placeholder="Hello {{customer.name}}, thanks for reaching out!"
                        ></textarea>
                        <div>
                            <span class="text-[10px] text-stone-500 block mb-1">Insert variables:</span>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="tag in ['{{customer.name}}', '{{customer.phone}}', '{{order.tracking_code}}', '{{order.total_amount}}', '{{catalog.summary}}']"
                                    :key="tag"
                                    type="button"
                                    @click="selectedNode.config.message = (selectedNode.config.message || '') + ' ' + tag"
                                    class="text-[9px] px-1.5 py-0.5 rounded bg-stone-100 text-stone-700 hover:bg-stone-200 transition-colors"
                                >
                                    {{ tag }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Add Tag Node Inspector -->
                    <div v-else-if="selectedNode.type === 'action_add_tag'">
                        <label class="block text-xs font-medium text-[#241e19] mb-1">Tag Name</label>
                        <input
                            type="text"
                            v-model="selectedNode.config.tag"
                            class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                            placeholder="e.g. VIP, Hot Lead, Prospect"
                        />
                    </div>

                    <!-- Update Lead Node Inspector -->
                    <div v-else-if="selectedNode.type === 'action_update_lead'" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Lead Field</label>
                            <input
                                type="text"
                                v-model="selectedNode.config.field"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="stage"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">New Value</label>
                            <input
                                type="text"
                                v-model="selectedNode.config.value"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="qualified"
                            />
                        </div>
                    </div>

                    <!-- Assign Staff Node Inspector -->
                    <div v-else-if="selectedNode.type === 'action_assign_staff'">
                        <label class="block text-xs font-medium text-[#241e19] mb-1">Assignment Method</label>
                        <select
                            v-model="selectedNode.config.strategy"
                            class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                        >
                            <option value="round_robin">Round-Robin</option>
                            <option value="least_busy">Least Busy Agent</option>
                        </select>
                    </div>

                    <!-- Update Conversation Node Inspector -->
                    <div v-else-if="selectedNode.type === 'action_update_conversation'" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Status</label>
                            <select
                                v-model="selectedNode.config.status"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                            >
                                <option value="open">Open</option>
                                <option value="resolved">Resolved</option>
                                <option value="snoozed">Snoozed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Priority</label>
                            <select
                                v-model="selectedNode.config.priority"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <!-- Check Field Node Inspector -->
                    <div v-else-if="selectedNode.type === 'condition_check_field'" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Subject</label>
                            <select
                                v-model="selectedNode.config.subject"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                            >
                                <option value="customer">Customer</option>
                                <option value="lead">Lead</option>
                                <option value="conversation">Conversation</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Field Name</label>
                            <input
                                type="text"
                                v-model="selectedNode.config.field"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="tags"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Operator</label>
                            <select
                                v-model="selectedNode.config.operator"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                            >
                                <option value="contains">contains</option>
                                <option value="equals">equals</option>
                                <option value="not_equals">not equals</option>
                                <option value="greater_than">greater than</option>
                                <option value="less_than">less than</option>
                                <option value="is_empty">is empty</option>
                                <option value="is_not_empty">is not empty</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Target Value</label>
                            <input
                                type="text"
                                v-model="selectedNode.config.value"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="VIP"
                            />
                        </div>
                        <p class="text-[10px] text-stone-500">Links label: "yes" or "no"</p>
                    </div>

                    <!-- Business Hours Filter Inspector -->
                    <div v-else-if="selectedNode.type === 'condition_time_window'" class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-[#241e19] mb-1">From Time</label>
                                <input
                                    type="time"
                                    v-model="selectedNode.config.from"
                                    class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#241e19] mb-1">To Time</label>
                                <input
                                    type="time"
                                    v-model="selectedNode.config.to"
                                    class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Timezone</label>
                            <input
                                type="text"
                                v-model="selectedNode.config.timezone"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="UTC or Africa/Lagos"
                            />
                        </div>
                        <p class="text-[10px] text-stone-500">Links label: "in_window" or "out_of_window"</p>
                    </div>

                    <!-- AI Intent Node Inspector -->
                    <div v-else-if="selectedNode.type === 'ai_intent'" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Intent Categories</label>
                            <p class="text-[10px] text-stone-500 mb-2">Classify user message. Connect outgoing edges labeled with the intent name.</p>
                            <div class="space-y-2 mb-2">
                                <div
                                    v-for="(intent, idx) in (selectedNode.config.intents || [])"
                                    :key="idx"
                                    class="p-2 rounded border border-[#e8e2d9] bg-[#faf8f5] space-y-1.5"
                                >
                                    <div class="flex items-center justify-between gap-1.5">
                                        <input
                                            type="text"
                                            v-model="intent.name"
                                            placeholder="Intent Name (e.g. order)"
                                            class="flex-1 text-[11px] py-1 px-1.5 rounded border-[#e8e2d9] bg-white font-mono"
                                        />
                                        <button
                                            type="button"
                                            @click="selectedNode.config.intents.splice(idx, 1)"
                                            class="text-red-500 hover:text-red-700 text-xs px-1"
                                        >✕</button>
                                    </div>
                                    <input
                                        type="text"
                                        v-model="intent.description"
                                        placeholder="Description for LLM classification"
                                        class="w-full text-[10px] py-0.5 px-1.5 rounded border-[#e8e2d9] bg-white"
                                    />
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="selectedNode.config.intents = selectedNode.config.intents || []; selectedNode.config.intents.push({ name: 'new_intent', description: '' })"
                                class="text-[10px] font-medium text-[#7b5537] hover:underline"
                            >
                                + Add Intent Category
                            </button>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Fallback Default Intent</label>
                            <input
                                type="text"
                                v-model="selectedNode.config.default_intent"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] font-mono shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="default"
                            />
                        </div>
                    </div>

                    <!-- Trigger Node Inspector (Keyword Filter) -->
                    <div v-else-if="selectedNode.type.startsWith('trigger_')" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Keyword Filter (Optional)</label>
                            <input
                                type="text"
                                v-model="selectedNode.config.keyword_filter"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="e.g. START, PROMO, MENU, HELP"
                            />
                            <p class="text-[10px] text-stone-500 mt-1">Only fire when customer sends this keyword. Leave blank for all messages.</p>
                        </div>
                        <div v-if="selectedNode.config.keyword_filter">
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Match Mode</label>
                            <select
                                v-model="selectedNode.config.keyword_match_mode"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                            >
                                <option value="contains">Contains Keyword</option>
                                <option value="equals">Exact Match</option>
                                <option value="starts_with">Starts With</option>
                            </select>
                        </div>
                    </div>

                    <!-- Catalog Search Inspector -->
                    <div v-else-if="selectedNode.type.includes('search') || selectedNode.type === 'action_search_catalog'" class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Catalog Query</label>
                            <input
                                type="text"
                                v-model="selectedNode.config.query"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="Leave blank to show all available products"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#241e19] mb-1">Max Items</label>
                            <input
                                type="number"
                                min="1"
                                max="20"
                                v-model.number="selectedNode.config.limit"
                                class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                                placeholder="5"
                            />
                            <p class="text-[10px] text-stone-500 mt-1">Results are stored in &#123;&#123;catalog.summary&#125;&#125; for downstream message nodes.</p>
                        </div>
                    </div>

                    <!-- Fallback Expression Inspector -->
                    <div v-else-if="selectedNode.type.includes('condition')">
                        <label class="block text-xs font-medium text-[#241e19] mb-1">Condition Expression</label>
                        <input
                            type="text"
                            v-model="selectedNode.config.expression"
                            class="w-full text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20 font-mono"
                            placeholder="intent == 'book_inspection'"
                        />
                    </div>

                    <!-- Connect to node -->
                    <div class="pt-3 border-t border-[#e8e2d9]">
                        <label class="block text-xs font-medium text-[#241e19] mb-1">Link to Next Node</label>
                        <div class="flex gap-2">
                            <select
                                v-model="connectTargetId"
                                class="flex-1 text-xs rounded-md border-[#e8e2d9] bg-white text-[#241e19] shadow-xs focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20"
                            >
                                <option value="" disabled>Select target</option>
                                <option v-for="n in form.nodes.filter(x => x.id !== selectedNode.id)" :key="n.id" :value="n.id">
                                    {{ n.label }}
                                </option>
                            </select>
                            <button
                                @click="addEdgeConnection"
                                :disabled="!connectTargetId"
                                type="button"
                                class="btn-primary py-1 px-3 disabled:opacity-40"
                            >
                                Link
                            </button>
                        </div>
                    </div>

                    <!-- Outgoing edges -->
                    <div class="pt-2" v-if="form.edges.filter(e => e.source === selectedNode.id).length > 0">
                        <label class="block text-xs font-medium text-[#241e19] mb-1.5">Outgoing Links</label>
                        <div class="space-y-1.5">
                            <div
                                v-for="edge in form.edges.filter(e => e.source === selectedNode.id)"
                                :key="edge.id"
                                class="p-2 rounded-md border border-[#e8e2d9] bg-[#faf8f5] text-xs flex items-center justify-between"
                            >
                                <span class="truncate text-[#241e19]">→ {{ getNodeById(edge.target)?.label }}</span>
                                <div class="flex items-center gap-1.5">
                                    <input
                                        type="text"
                                        v-model="edge.label"
                                        placeholder="Label"
                                        class="text-[10px] py-0.5 px-1.5 w-14 rounded border-[#e8e2d9] bg-white"
                                    />
                                    <button @click="removeEdge(edge.id)" class="text-red-600 hover:text-red-800 font-bold px-1">✕</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-8 h-8 rounded-full bg-[#f5efe6] text-[#7b5537] flex items-center justify-center mb-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/>
                        </svg>
                    </div>
                    <p class="text-xs text-stone-500">Select any node on the canvas to edit its properties.</p>
                </div>
            </div>
        </div>

        <!-- Super Admin Publish Blueprint Modal -->
        <div
            v-if="showTemplateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-xl border border-[#e8e2d9] shadow-xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#e8e2d9]">
                    <div>
                        <h3 class="text-sm font-bold text-[#211812]">Publish as Platform Blueprint</h3>
                        <p class="text-xs text-stone-500 mt-0.5">Make this workflow available to all stores as a starter template.</p>
                    </div>
                    <button @click="showTemplateModal = false" class="text-stone-400 hover:text-stone-700 text-lg">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Blueprint Name</label>
                        <input
                            type="text"
                            v-model="templateForm.name"
                            class="w-full text-xs rounded-lg border-[#e8e2d9] p-2 focus:ring-1 focus:ring-[#7b5537]"
                            placeholder="e.g. Abandoned Cart Recovery"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Industry Focus</label>
                        <select
                            v-model="templateForm.industry"
                            class="w-full text-xs rounded-lg border-[#e8e2d9] p-2 focus:ring-1 focus:ring-[#7b5537]"
                        >
                            <option value="general">General / All Industries</option>
                            <option value="retail">Retail &amp; E-Commerce</option>
                            <option value="restaurant">Food &amp; Restaurant</option>
                            <option value="real_estate">Real Estate &amp; Housing</option>
                            <option value="services">Services &amp; Appointments</option>
                            <option value="healthcare">Healthcare &amp; Clinic</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Description</label>
                        <textarea
                            v-model="templateForm.description"
                            rows="3"
                            class="w-full text-xs rounded-lg border-[#e8e2d9] p-2 focus:ring-1 focus:ring-[#7b5537]"
                            placeholder="Explain what this automation does and why stores should use it..."
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#e8e2d9]">
                    <button
                        type="button"
                        @click="showTemplateModal = false"
                        class="px-3 py-1.5 text-xs text-stone-600 hover:text-stone-900 font-medium"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitSaveTemplate"
                        :disabled="templateForm.processing || !templateForm.name"
                        class="btn-primary"
                    >
                        {{ templateForm.processing ? 'Publishing...' : 'Publish Blueprint' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
