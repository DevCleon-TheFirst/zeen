<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    initialPlan: {
        type: String,
        default: 'starter',
    },
});

const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const requestedPlan = urlParams?.get('plan') || props.initialPlan;
const validPlans = ['starter', 'pro', 'enterprise'];
const selectedPlan = validPlans.includes(requestedPlan) ? requestedPlan : 'starter';

const form = useForm({
    name: '',
    business_name: '',
    industry: 'retail',
    email: '',
    password: '',
    password_confirmation: '',
    plan: selectedPlan,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Create Your Business Account" />

        <div class="mb-6 text-center">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Get Started</h2>
            <p class="mt-1 text-sm text-gray-500">Connect your channels & automate customer operations with AI</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="business_name" value="Business / Company Name" />
                <TextInput
                    id="business_name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.business_name"
                    placeholder="e.g. Apex Real Estate, Grand Hotel"
                    required
                    autofocus
                />
                <InputError class="mt-1" :message="form.errors.business_name" />
            </div>

            <div>
                <InputLabel for="industry" value="Industry" />
                <select
                    id="industry"
                    v-model="form.industry"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    required
                >
                    <option value="real_estate">Real Estate & Property Management</option>
                    <option value="hospitality">Hotels, Shortlets & Hospitality</option>
                    <option value="healthcare">Healthcare, Clinics & Dental</option>
                    <option value="retail">Retail, E-commerce & Products</option>
                    <option value="education">Education & Online Courses</option>
                    <option value="services">Professional & Consulting Services</option>
                    <option value="other">Other Business</option>
                </select>
                <InputError class="mt-1" :message="form.errors.industry" />
            </div>

            <div>
                <InputLabel for="name" value="Your Full Name" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    placeholder="e.g. Alex Johnson"
                    required
                    autocomplete="name"
                />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Work Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    placeholder="alex@company.com"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirm Password" />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1" :message="form.errors.password_confirmation" />
            </div>

            <!-- Subscription Plan Selection (No Free Plan) -->
            <div class="pt-1">
                <label class="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1.5">
                    Select Your Subscription Plan
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <!-- Starter Card -->
                    <button
                        type="button"
                        @click="form.plan = 'starter'"
                        class="p-3 text-left rounded-xl border text-xs transition-all cursor-pointer relative"
                        :class="form.plan === 'starter' ? 'bg-[#faf8f5] border-[#7b5537] ring-1 ring-[#7b5537] shadow-xs' : 'bg-white border-[#e8e2d9] hover:border-stone-400'"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-[#211812]">Starter Store</span>
                            <span v-if="form.plan === 'starter'" class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        </div>
                        <p class="text-sm font-bold text-[#211812]">₦20,000 <span class="text-[10px] font-normal text-stone-500">/mo</span></p>
                        <p class="text-[10px] text-stone-600 mt-1 font-medium">2,500 Messages / mo</p>
                    </button>

                    <!-- Pro Card (Featured) -->
                    <button
                        type="button"
                        @click="form.plan = 'pro'"
                        class="p-3 text-left rounded-xl border text-xs transition-all cursor-pointer relative"
                        :class="form.plan === 'pro' ? 'bg-[#291e17] text-white border-[#f59e0b] ring-1 ring-[#f59e0b] shadow-md' : 'bg-white border-[#e8e2d9] hover:border-stone-400'"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold" :class="form.plan === 'pro' ? 'text-white' : 'text-[#211812]'">Omnichannel Pro</span>
                            <span class="text-[9px] font-bold px-1.5 py-0.2 rounded" :class="form.plan === 'pro' ? 'bg-[#f59e0b] text-[#1a110b]' : 'bg-amber-100 text-amber-800'">Popular</span>
                        </div>
                        <p class="text-sm font-bold" :class="form.plan === 'pro' ? 'text-white' : 'text-[#211812]'">₦40,000 <span class="text-[10px] font-normal" :class="form.plan === 'pro' ? 'text-stone-300' : 'text-stone-500'">/mo</span></p>
                        <p class="text-[10px] mt-1 font-medium" :class="form.plan === 'pro' ? 'text-amber-300' : 'text-stone-600'">8,000 Messages / mo</p>
                    </button>

                    <!-- Enterprise Card -->
                    <button
                        type="button"
                        @click="form.plan = 'enterprise'"
                        class="p-3 text-left rounded-xl border text-xs transition-all cursor-pointer relative"
                        :class="form.plan === 'enterprise' ? 'bg-[#faf8f5] border-[#7b5537] ring-1 ring-[#7b5537] shadow-xs' : 'bg-white border-[#e8e2d9] hover:border-stone-400'"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-[#211812]">Enterprise Scale</span>
                            <span v-if="form.plan === 'enterprise'" class="w-2 h-2 rounded-full bg-purple-500"></span>
                        </div>
                        <p class="text-sm font-bold text-[#211812]">₦80,000 <span class="text-[10px] font-normal text-stone-500">/mo</span></p>
                        <p class="text-[10px] text-stone-600 mt-1 font-medium">25,000 Messages / mo</p>
                    </button>
                </div>
                <InputError class="mt-1" :message="form.errors.plan" />
            </div>

            <div class="pt-2 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Already have an account?
                </Link>

                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Create Account
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
