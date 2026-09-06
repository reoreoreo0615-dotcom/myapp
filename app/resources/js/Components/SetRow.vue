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
    // 完了済みのセットを編集モードで表示するか(NumberStepper + 保存/キャンセル)。
    editing: {
        type: Boolean,
        default: false,
    },
    // 完了済み行に編集・削除ボタンを出すか。
    editable: {
        type: Boolean,
        default: false,
    },
    // ウォームアップとして記録されたセットであることを示す小さなラベルを出す。
    warmup: {
        type: Boolean,
        default: false,
    },
    // 送信中は記録/保存ボタンを無効化し、二重送信を防ぐ。
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:weight',
    'update:reps',
    'record',
    'edit-start',
    'edit-cancel',
    'edit-save',
    'delete',
]);

const weightText = computed(() => formatNumber(props.weight));

// 入力欄(未完了 or 編集中)を出すかどうか。
const showInputs = computed(() => !props.completed || props.editing);
</script>

<template>
    <div class="border-b border-line py-3 last:border-b-0">
        <div class="flex items-center gap-2">
            <span class="w-5 shrink-0 font-mono text-xs tabular-nums text-ink-3">
                {{ setNumber }}
            </span>

            <template v-if="completed && !editing">
                <div
                    class="flex flex-1 items-baseline gap-1 font-display text-lg tabular-nums text-ink-2"
                >
                    <span>{{ weightText }}{{ unit }}</span>
                    <span class="text-ink-3">&times;</span>
                    <span>{{ reps }}</span>
                    <span v-if="warmup" class="label-micro ml-1 text-[10px] text-ink-3">
                        アップ
                    </span>
                </div>
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center text-ok"
                    aria-label="完了"
                >
                    &check;
                </span>
                <template v-if="editable">
                    <button
                        type="button"
                        class="flex h-11 w-11 shrink-0 items-center justify-center text-ink-2 hover:text-ink"
                        aria-label="このセットを編集"
                        @click="emit('edit-start')"
                    >
                        &#9998;
                    </button>
                    <button
                        type="button"
                        class="flex h-11 w-11 shrink-0 items-center justify-center text-ink-3 hover:text-warn"
                        aria-label="このセットを削除"
                        @click="emit('delete')"
                    >
                        &#10005;
                    </button>
                </template>
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

        <div v-if="showInputs" class="mt-2 flex gap-2">
            <button
                v-if="editing"
                type="button"
                class="h-12 shrink-0 border border-line px-4 font-mono text-xs uppercase tracking-widest text-ink-2 transition-colors hover:border-ink-3 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="processing"
                @click="emit('edit-cancel')"
            >
                キャンセル
            </button>
            <button
                type="button"
                class="h-12 flex-1 bg-accent font-mono text-xs uppercase tracking-widest text-ground transition-opacity disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="processing"
                @click="editing ? emit('edit-save') : emit('record')"
            >
                {{
                    processing
                        ? editing
                            ? '保存中…'
                            : '記録中…'
                        : editing
                          ? '保存'
                          : 'このセットを記録'
                }}
            </button>
        </div>
    </div>
</template>
