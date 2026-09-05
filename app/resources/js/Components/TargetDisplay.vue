<script setup>
import { computed } from 'vue';
import { formatNumber } from '@/Utils/format';

const props = defineProps({
    label: {
        type: String,
        default: 'TARGET',
    },
    weight: {
        type: Number,
        required: true,
    },
    reps: {
        type: Number,
        required: true,
    },
    unit: {
        type: String,
        default: 'kg',
    },
    // 前回から改善した指標。'weight' のときだけ重量をアクセント色にする。
    // レップアップ('reps')は通常色のまま(毎回派手にすると「上がった」ことが伝わらない)。
    improvement: {
        type: String,
        default: null,
        validator: (value) => value === null || ['weight', 'reps'].includes(value),
    },
});

const weightText = computed(() => formatNumber(props.weight));
</script>

<template>
    <div class="leading-none">
        <p class="label-micro text-[11px] text-ink-3">{{ label }}</p>
        <p class="mt-1.5 flex items-baseline gap-1 font-display text-3xl tabular-nums">
            <span
                :class="improvement === 'weight' ? 'text-accent' : 'text-ink'"
            >
                {{ weightText }}{{ unit }}
            </span>
            <span class="text-xl text-ink-3">&times;</span>
            <span class="text-ink">{{ reps }}</span>
        </p>
    </div>
</template>
