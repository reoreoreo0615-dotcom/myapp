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
        class="inline-flex h-12 items-center justify-center gap-2 border border-line px-6 font-mono text-xs uppercase tracking-widest text-ink transition-colors hover:border-ink-2 active:bg-surface active:border-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-ground disabled:cursor-not-allowed disabled:opacity-40"
    >
        <slot />
    </button>
</template>
