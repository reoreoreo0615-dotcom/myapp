<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { formatNumber } from '@/Utils/format';

const props = defineProps({
    modelValue: {
        type: Number,
        required: true,
    },
    // 重量なら 2.5 や 1.25、回数なら 1
    step: {
        type: Number,
        default: 1,
    },
    min: {
        type: Number,
        default: -Infinity,
    },
    max: {
        type: Number,
        default: Infinity,
    },
    unit: {
        type: String,
        default: '',
    },
    // SetRow など一覧の中で使う際に幅を詰めるための省スペース版。
    // 44x44px の最低ラインは維持する。
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const editing = ref(false);
const draft = ref('');
const editInput = ref(null);

const displayValue = computed(() => formatNumber(props.modelValue));

const buttonSizeClass = computed(() => (props.compact ? 'h-11 w-11' : 'h-12 w-12'));
const numberSizeClass = computed(() =>
    props.compact ? 'min-w-12 text-xl' : 'min-w-[4.5rem] text-2xl',
);

function clamp(value) {
    return Math.min(props.max, Math.max(props.min, value));
}

function round(value) {
    // decimal(5,2) 相当。浮動小数の誤差を避けるため小数第2位で丸める。
    return Math.round(value * 100) / 100;
}

function changeValue(direction) {
    if (editing.value) {
        return;
    }
    const next = clamp(round(props.modelValue + direction * props.step));
    if (next !== props.modelValue) {
        emit('update:modelValue', next);
    }
}

// 長押しで連続増減する。最初の1回は即座に反映(タップ1回=1ステップを崩さない)。
let holdTimeout = null;
let holdInterval = null;
let pointerActive = false;

function clearHoldTimers() {
    if (holdTimeout) {
        clearTimeout(holdTimeout);
        holdTimeout = null;
    }
    if (holdInterval) {
        clearInterval(holdInterval);
        holdInterval = null;
    }
}

function onPointerDown(direction) {
    pointerActive = true;
    changeValue(direction);
    holdTimeout = setTimeout(() => {
        holdInterval = setInterval(() => changeValue(direction), 100);
    }, 450);
}

function onPointerUp() {
    clearHoldTimers();
    // pointerup の直後に発火する合成 click イベントを無視するための猶予。
    setTimeout(() => {
        pointerActive = false;
    }, 300);
}

function onClick(direction) {
    // キーボード操作(Enter/Space)など、pointerdown を経由しない click のみ処理する。
    if (pointerActive) {
        return;
    }
    changeValue(direction);
}

onBeforeUnmount(() => {
    clearHoldTimers();
});

async function startEdit() {
    draft.value = String(props.modelValue);
    editing.value = true;
    await nextTick();
    editInput.value?.focus();
    editInput.value?.select();
}

function commitEdit() {
    if (!editing.value) {
        return;
    }
    const parsed = Number.parseFloat(draft.value);
    editing.value = false;
    if (Number.isNaN(parsed)) {
        return;
    }
    const next = clamp(round(parsed));
    if (next !== props.modelValue) {
        emit('update:modelValue', next);
    }
}
</script>

<template>
    <div
        class="inline-flex items-stretch divide-x divide-line overflow-hidden rounded-[--radius-control] border border-line"
        role="group"
        :aria-label="unit ? `${unit} を調整` : '数値を調整'"
    >
        <button
            type="button"
            :class="buttonSizeClass"
            class="flex shrink-0 items-center justify-center font-display text-2xl leading-none text-ink transition-colors active:bg-accent active:text-ground disabled:cursor-not-allowed disabled:opacity-30"
            :disabled="modelValue <= min"
            aria-label="減らす"
            @pointerdown="onPointerDown(-1)"
            @pointerup="onPointerUp"
            @pointerleave="onPointerUp"
            @pointercancel="onPointerUp"
            @click="onClick(-1)"
        >
            &minus;
        </button>

        <div :class="numberSizeClass" class="flex shrink-0 items-center justify-center px-2">
            <input
                v-if="editing"
                ref="editInput"
                v-model="draft"
                type="text"
                inputmode="decimal"
                class="w-full border-0 bg-transparent p-0 text-center font-display tabular-nums text-ink focus:outline-none focus:ring-0"
                @blur="commitEdit"
                @keydown.enter="commitEdit"
            />
            <button
                v-else
                type="button"
                class="flex items-center gap-1 font-display tabular-nums text-ink"
                @click="startEdit"
            >
                <span>{{ displayValue }}</span>
                <span v-if="unit" class="text-sm text-ink-2">{{ unit }}</span>
            </button>
        </div>

        <button
            type="button"
            :class="buttonSizeClass"
            class="flex shrink-0 items-center justify-center font-display text-2xl leading-none text-ink transition-colors active:bg-accent active:text-ground disabled:cursor-not-allowed disabled:opacity-30"
            :disabled="modelValue >= max"
            aria-label="増やす"
            @pointerdown="onPointerDown(1)"
            @pointerup="onPointerUp"
            @pointerleave="onPointerUp"
            @pointercancel="onPointerUp"
            @click="onClick(1)"
        >
            +
        </button>
    </div>
</template>
