<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    appointments: Object,
    filters: Object,
});

const filterStatus = (status) => {
    router.get(route('appointments.index'), {
        status: status === 'all' ? null : status,
    }, { preserveState: true });
};

const updateStatus = (apt, newStatus) => {
    router.patch(route('appointments.update', apt.id), { status: newStatus }, { preserveScroll: true });
};

const deleteAppointment = (apt) => {
    if (confirm(`Remove appointment "${apt.title}"?`)) {
        router.delete(route('appointments.destroy', apt.id), { preserveScroll: true });
    }
};

const tabs = [
    { value: 'all',       label: 'All Bookings' },
    { value: 'pending',   label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Appointments & Bookings" />

        <template #header>
            <div class="flex items-center gap-2 text-xs font-semibold text-[#291e17]">
                Appointments & Bookings
            </div>
        </template>

        <div class="p-6 md:p-8 space-y-6 bg-[#faf8f5] min-h-screen text-[#291e17]">
            <!-- Page Header -->
            <div class="pb-4 border-b border-[#e8e2d9]">
                <h1 class="text-lg font-semibold tracking-tight text-[#291e17]">
                    Appointments & Bookings
                </h1>
                <p class="text-xs text-[#7b5537] mt-0.5">
                    View and manage client viewings, consultations, and bookings scheduled by the AI or your team.
                </p>
            </div>

            <!-- Status Filter Tabs -->
            <div class="flex items-center gap-1 p-1 rounded-md bg-white border border-[#e8e2d9] w-fit overflow-x-auto">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    @click="filterStatus(tab.value)"
                    class="px-3 py-1.5 rounded text-xs font-medium whitespace-nowrap transition-colors"
                    :class="filters.status === tab.value
                        ? 'bg-[#4a3324] text-[#faf8f5]'
                        : 'text-[#7b5537] hover:text-[#291e17] hover:bg-[#faf8f5]'"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Empty State -->
            <div v-if="appointments.data.length === 0" class="card p-12 text-center border-dashed border-[#d9c39f]">
                <div class="w-10 h-10 rounded-md bg-[#f2ece4] border border-[#e8e2d9] text-[#7b5537] flex items-center justify-center mx-auto mb-3">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-sm text-[#291e17] mb-1">No appointments found</h3>
                <p class="text-xs text-[#7b5537] max-w-sm mx-auto">
                    When customers book viewings or consultations via WhatsApp, Telegram, or Messenger, the AI schedules them here automatically.
                </p>
            </div>

            <!-- Appointments Table -->
            <div v-else class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-[#f8f5f0] border-b border-[#e8e2d9]">
                                <th scope="col" class="py-3 pl-5 pr-3 text-left text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Appointment</th>
                                <th scope="col" class="px-3 py-3 text-left text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Customer</th>
                                <th scope="col" class="px-3 py-3 text-left text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Scheduled</th>
                                <th scope="col" class="px-3 py-3 text-left text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Duration</th>
                                <th scope="col" class="px-3 py-3 text-left text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Status</th>
                                <th scope="col" class="py-3 pl-3 pr-5 text-right text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e8e2d9]">
                            <tr
                                v-for="apt in appointments.data"
                                :key="apt.id"
                                class="hover:bg-[#faf8f5] transition-colors"
                            >
                                <td class="whitespace-nowrap py-3.5 pl-5 pr-3">
                                    <div class="text-xs font-semibold text-[#291e17]">{{ apt.title }}</div>
                                    <div class="text-[11px] text-[#7b5537] mt-0.5">{{ apt.location || 'Location TBC' }}</div>
                                </td>

                                <td class="whitespace-nowrap px-3 py-3.5">
                                    <div class="text-xs font-medium text-[#291e17]">{{ apt.customer.name }}</div>
                                    <div class="text-[11px] font-mono text-[#7b5537]">{{ apt.customer.phone || '—' }}</div>
                                </td>

                                <td class="whitespace-nowrap px-3 py-3.5 text-xs text-[#291e17] tabular-nums">
                                    {{ apt.scheduled_at }}
                                </td>

                                <td class="whitespace-nowrap px-3 py-3.5 text-xs text-[#7b5537] tabular-nums">
                                    {{ apt.duration_minutes }} min
                                </td>

                                <td class="whitespace-nowrap px-3 py-3.5">
                                    <select
                                        :value="apt.status"
                                        @change="updateStatus(apt, $event.target.value)"
                                        class="rounded-md border border-[#e8e2d9] bg-white py-1 px-2 text-xs font-medium text-[#291e17] focus:border-[#4a3324] focus:outline-none cursor-pointer"
                                    >
                                        <option value="pending">Pending</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                        <option value="no_show">No Show</option>
                                    </select>
                                </td>

                                <td class="whitespace-nowrap py-3.5 pl-3 pr-5 text-right text-xs font-medium">
                                    <div class="flex items-center justify-end gap-3">
                                        <Link
                                            v-if="apt.conversation_id"
                                            :href="route('inbox.index', { selected: apt.conversation_id })"
                                            class="text-xs font-medium text-[#4a3324] hover:text-[#291e17] transition-colors"
                                        >
                                            View Chat &rarr;
                                        </Link>
                                        <button
                                            @click="deleteAppointment(apt)"
                                            type="button"
                                            class="text-xs font-medium text-[#991b1b] hover:text-[#7f1d1d] transition-colors"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
