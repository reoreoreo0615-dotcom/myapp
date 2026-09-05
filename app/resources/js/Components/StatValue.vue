<script setup>
import { computed } from 'vue';
import { formatNumber } from '@/Utils/format';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [Number, String],
        required: true,
    },
    unit: {
        type: String,
        default: '',
    },
    // 意味のある変化(自己ベスト更新など)のときだけアクセント色にする。
    // 装飾目的での多用はしないこと。
    accent: {
        type: Boolean,
        default: false,
    },
});

const displayValue = computed(() =>
    typeof props.value === 'number' ? formatNumber(props.value) : props.value,
);
</script>

<template>
    <div class="leading-none">
        <p class="label-micro text-[11px] text-ink-3">{{ label }}</p>
        <p
            class="mt-1.5 font-display text-3xl tabular-nums"
            :class="accent ? 'text-accent' : 'text-ink'"
        >
            {{ displayValue }}<span v-if="unit" class="ml-1 text-base text-ink-2">{{ unit }}</span>
        </p>
    </div>
</template>
