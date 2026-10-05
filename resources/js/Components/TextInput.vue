<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md border-[#e8e2d9] bg-white text-sm text-[#241e19] shadow-xs placeholder-[#9c8e80] focus:border-[#7b5537] focus:ring-2 focus:ring-[#7b5537]/20 transition-colors duration-150"
        v-model="model"
        ref="input"
    />
</template>
