<script setup>
import { computed } from 'vue';
import NumberStepper from '@/Components/NumberStepper.vue';
import { formatNumber } from '@/Utils/format';

const props = defineProps({
    setNumber: {
        type: Number,
        required: true,
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
    weightStep: {
        type: Number,
        default: 2.5,
    },
    repsStep: {
        type: Number,
        default: 1,
    },
    completed: {
        type: Boolean,
        default: false,
    },
    // 送信中は記録ボタンを無効化し、二重送信を防ぐ。
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:weight', 'update:reps', 'record']);

const weightText = computed(() => formatNumber(props.weight));
</script>

<template>
    <div class="border-b border-line py-3 last:border-b-0">
        <div class="flex items-center gap-2">
            <span class="w-5 shrink-0 font-mono text-xs tabular-nums text-ink-3">
                {{ setNumber }}
            </span>

            <template v-if="completed">
                <div
                    class="flex flex-1 items-baseline gap-1 font-display text-lg tabular-nums text-ink-2"
                >
                    <span>{{ weightText }}{{ unit }}</span>
                    <span class="text-ink-3">&times;</span>
                    <span>{{ reps }}</span>
                </div>
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center text-ok"
                    aria-label="完了"
                >
                    &check;
                </span>
            </template>

            <template v-else>
                <NumberStepper
                    compact
                    class="flex-1"
                    :model-value="weight"
                    :step="weightStep"
                    :min="0"
                    :unit="unit"
                    @update:model-value="emit('update:weight', $event)"
                />
                <NumberStepper
                    compact
                    class="flex-1"
                    :model-value="reps"
                    :step="repsStep"
                    :min="0"
                    @update:model-value="emit('update:reps', $event)"
                />
            </template>
        </div>

        <button
            v-if="!completed"
            type="button"
            class="mt-2 h-12 w-full bg-accent font-mono text-xs uppercase tracking-widest text-ground transition-opacity disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="processing"
            @click="emit('record')"
        >
            {{ processing ? '記録中…' : '記録' }}
        </button>
    </div>
</template>
