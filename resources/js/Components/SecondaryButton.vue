<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center rounded-md border border-[#e8e2d9] bg-white px-4 py-2 text-xs font-medium text-[#4a3324] shadow-xs transition-colors duration-150 hover:bg-[#faf7f2] focus:outline-none focus:ring-2 focus:ring-[#7b5537]/30 disabled:opacity-40"
    >
        <slot />
    </button>
</template>
