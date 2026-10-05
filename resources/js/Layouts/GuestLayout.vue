<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-[#faf8f5] pt-8 sm:justify-center sm:pt-0 selection:bg-[#7b5537] selection:text-white"
    >
        <div class="flex flex-col items-center">
            <Link href="/" class="group flex flex-col items-center">
                <ApplicationLogo class="h-24 w-auto object-contain drop-shadow-xs transition-transform duration-200 group-hover:scale-105" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-7 py-6 shadow-sm border border-[#e8e2d9] sm:max-w-md sm:rounded-xl"
        >
            <slot />
        </div>
    </div>
</template>
