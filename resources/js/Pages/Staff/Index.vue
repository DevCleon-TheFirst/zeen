<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    staff: Array,
    roles: Array,
});

const showInviteModal = ref(false);

const inviteForm = useForm({
    name: '',
    email: '',
    role: 'agent',
    phone: '',
    password: '',
});

const submitInvite = () => {
    inviteForm.post(route('staff.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showInviteModal.value = false;
            inviteForm.reset();
        },
    });
};

const updateStaffMember = (user, role, isActive) => {
    router.patch(route('staff.update', user.id), {
        role: role,
        is_active: isActive,
    }, { preserveScroll: true });
};

const deleteStaff = (user) => {
    if (confirm(`Remove ${user.name} from the business?`)) {
        router.delete(route('staff.destroy', user.id), { preserveScroll: true });
    }
};

const roleColor = (role) => {
    if (role === 'admin' || role === 'manager') return 'background:#f2ece4;color:#4a3324;border:1px solid #e8e2d9;';
    return 'background:#ffffff;color:#7b5537;border:1px solid #e8e2d9;';
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Team & Staff" />

        <template #header>
            <div class="flex items-center gap-2 text-xs font-semibold text-[#291e17]">
                Team & Staff
            </div>
        </template>

        <div class="p-6 md:p-8 space-y-6 bg-[#faf8f5] min-h-screen text-[#291e17]">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-[#e8e2d9]">
                <div>
                    <h1 class="text-lg font-semibold tracking-tight text-[#291e17]">
                        Team & Staff Members
                    </h1>
                    <p class="text-xs text-[#7b5537] mt-0.5">
                        Manage agents who handle human escalations, and administrators with business dashboard access.
                    </p>
                </div>
                <button
                    @click="showInviteModal = true"
                    type="button"
                    class="btn-primary inline-flex items-center gap-2 self-start md:self-auto"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Staff Member
                </button>
            </div>

            <!-- Staff Table -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-[#f8f5f0] border-b border-[#e8e2d9]">
                                <th scope="col" class="py-3 pl-5 pr-3 text-left text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Staff Member</th>
                                <th scope="col" class="px-3 py-3 text-left text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Role</th>
                                <th scope="col" class="px-3 py-3 text-left text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Status</th>
                                <th scope="col" class="px-3 py-3 text-left text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Phone</th>
                                <th scope="col" class="px-3 py-3 text-left text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Joined</th>
                                <th scope="col" class="py-3 pl-3 pr-5 text-right text-[10px] font-mono uppercase tracking-wider text-[#7b5537]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e8e2d9]">
                            <tr
                                v-for="user in staff"
                                :key="user.id"
                                class="hover:bg-[#faf8f5] transition-colors"
                            >
                                <!-- Avatar + Name -->
                                <td class="whitespace-nowrap py-3.5 pl-5 pr-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 flex-shrink-0 rounded-full bg-[#f2ece4] border border-[#e8e2d9] flex items-center justify-center text-xs font-semibold text-[#4a3324]">
                                            {{ user.name[0].toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2 text-xs font-semibold text-[#291e17]">
                                                {{ user.name }}
                                                <span v-if="user.is_owner" class="px-1.5 py-0.5 rounded text-[9px] font-mono uppercase tracking-wider bg-[#f2ece4] text-[#4a3324] border border-[#e8e2d9]">
                                                    Owner
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-[#7b5537] mt-0.5">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Role selector -->
                                <td class="whitespace-nowrap px-3 py-3.5">
                                    <select
                                        v-if="!user.is_owner"
                                        :value="user.role"
                                        @change="updateStaffMember(user, $event.target.value, user.is_active)"
                                        class="rounded-md border border-[#e8e2d9] bg-white py-1 px-2 text-xs font-medium text-[#291e17] focus:border-[#4a3324] focus:outline-none cursor-pointer"
                                    >
                                        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                                    </select>
                                    <span v-else class="text-xs font-medium text-[#291e17]">Primary Owner</span>
                                </td>

                                <!-- Active toggle -->
                                <td class="whitespace-nowrap px-3 py-3.5">
                                    <button
                                        v-if="!user.is_owner"
                                        @click="updateStaffMember(user, user.role, !user.is_active)"
                                        type="button"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium transition-colors"
                                        :class="user.is_active
                                            ? 'bg-[#ecfdf5] text-[#065f46] border border-[#a7f3d0]'
                                            : 'bg-[#fef2f2] text-[#991b1b] border border-[#fecaca]'"
                                    >
                                        {{ user.is_active ? 'Active' : 'Deactivated' }}
                                    </button>
                                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-[#ecfdf5] text-[#065f46] border border-[#a7f3d0]">
                                        Active
                                    </span>
                                </td>

                                <!-- Phone -->
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs font-mono text-[#7b5537]">
                                    {{ user.phone || '—' }}
                                </td>

                                <!-- Joined -->
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs text-[#7b5537]">
                                    {{ user.created_at }}
                                </td>

                                <!-- Actions -->
                                <td class="whitespace-nowrap py-3.5 pl-3 pr-5 text-right text-xs font-medium">
                                    <button
                                        v-if="!user.is_owner && user.id !== $page.props.auth.user.id"
                                        @click="deleteStaff(user)"
                                        type="button"
                                        class="text-xs font-medium text-[#991b1b] hover:text-[#7f1d1d] transition-colors"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Invite Modal -->
            <div v-if="showInviteModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/40 backdrop-blur-[1px]">
                <div class="card max-w-md w-full p-6 shadow-xl">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-[#e8e2d9]">
                        <div>
                            <h3 class="font-semibold text-sm text-[#291e17]">Add New Staff Member</h3>
                            <p class="text-xs text-[#7b5537] mt-0.5">Create login credentials for a team member.</p>
                        </div>
                        <button @click="showInviteModal = false" class="w-6 h-6 rounded-md flex items-center justify-center text-sm text-[#7b5537] hover:bg-[#f2ece4]">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitInvite" class="space-y-4">
                        <div>
                            <InputLabel for="staff_name" value="Full Name" />
                            <TextInput id="staff_name" type="text" class="mt-1 block w-full text-xs" v-model="inviteForm.name" required placeholder="e.g. Amaka Okafor" />
                        </div>

                        <div>
                            <InputLabel for="staff_email" value="Email Address" />
                            <TextInput id="staff_email" type="email" class="mt-1 block w-full text-xs" v-model="inviteForm.email" required placeholder="amaka@company.com" />
                        </div>

                        <div>
                            <InputLabel for="staff_role" value="Assigned Role" />
                            <select
                                id="staff_role"
                                v-model="inviteForm.role"
                                class="mt-1 block w-full rounded-md text-xs py-2 px-3 border border-[#e8e2d9] bg-white text-[#291e17] focus:border-[#4a3324] focus:outline-none"
                                required
                            >
                                <option v-for="r in roles" :key="r.value" :value="r.value">
                                    {{ r.label }} — {{ r.description }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <InputLabel for="staff_phone" value="Phone Number (Optional)" />
                            <TextInput id="staff_phone" type="text" class="mt-1 block w-full text-xs font-mono" v-model="inviteForm.phone" placeholder="+234..." />
                        </div>

                        <div>
                            <InputLabel for="staff_pass" value="Initial Password" />
                            <TextInput id="staff_pass" type="password" class="mt-1 block w-full text-xs" v-model="inviteForm.password" required placeholder="Minimum 8 characters" />
                        </div>

                        <div class="pt-3 border-t border-[#e8e2d9] flex justify-end gap-2">
                            <button
                                type="button"
                                @click="showInviteModal = false"
                                class="btn-secondary"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="inviteForm.processing"
                                class="btn-primary disabled:opacity-50"
                            >
                                Create Staff Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
