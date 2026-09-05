<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-warn">
            {{ message }}
        </p>
    </div>
</template>
