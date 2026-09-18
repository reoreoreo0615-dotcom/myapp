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
        ref="input"
        v-model="model"
        class="w-full rounded-none border-line bg-surface px-3 py-2.5 text-ink shadow-none placeholder:text-ink-3 focus:border-accent focus:ring-1 focus:ring-accent"
    />
</template>
