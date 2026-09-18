<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-accent px-6 text-sm font-medium text-white transition-opacity hover:opacity-85 active:opacity-70 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-ground disabled:cursor-not-allowed disabled:opacity-40"
    >
        <slot />
    </button>
</template>
