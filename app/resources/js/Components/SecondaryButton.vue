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
        class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-surface px-6 text-sm font-medium text-ink transition-colors hover:bg-line active:bg-line focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-ground disabled:cursor-not-allowed disabled:opacity-40"
    >
        <slot />
    </button>
</template>
