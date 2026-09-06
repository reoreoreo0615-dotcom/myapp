<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import ExerciseHistoryChart from '@/Components/ExerciseHistoryChart.vue';
import Modal from '@/Components/Modal.vue';
import NumberStepper from '@/Components/NumberStepper.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Rule from '@/Components/Rule.vue';
import { formatNumber } from '@/Utils/format';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    // '3m' | '6m' | 'all'
    period: {
        type: String,
        required: true,
    },
    // [{ id, measured_on, weight_kg, body_fat_percentage, memo }] measured_on 降順
    logs: {
        type: Array,
        required: true,
    },
    // 直近の記録({ id, measured_on, weight_kg, body_fat_percentage, memo })。無ければ null。
    latest: {
        type: Object,
        default: null,
    },
});

const page = usePage();

function todayString() {
    // ローカルタイムゾーンでの「今日」。toISOString() は UTC になるため使わない。
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const periodOptions = [
    { value: '3m', label: '3ヶ月' },
    { value: '6m', label: '6ヶ月' },
    { value: 'all', label: '全期間' },
];

function selectPeriod(value) {
    if (value === props.period) {
        return;
    }
    router.get(
        route('body-logs.index'),
        { period: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

// ------------------------------------------------------------------
// 記録フォーム(新規 / 編集を1つのフォームで兼ねる)
// ------------------------------------------------------------------

const editingLogId = ref(null);
const trackBodyFat = ref(false);
const bodyFatValue = ref(20);

const form = useForm({
    measured_on: todayString(),
    weight_kg: props.latest ? props.latest.weight_kg : 60,
    body_fat_percentage: null,
    memo: '',
});

const isEditing = computed(() => editingLogId.value !== null);

function startCreate() {
    editingLogId.value = null;
    form.clearErrors();
    form.measured_on = todayString();
    form.weight_kg = props.latest ? props.latest.weight_kg : 60;
    trackBodyFat.value = false;
    bodyFatValue.value = props.latest?.body_fat_percentage ?? 20;
    form.memo = '';
}

function startEdit(log) {
    editingLogId.value = log.id;
    form.clearErrors();
    form.measured_on = log.measured_on;
    form.weight_kg = log.weight_kg;
    trackBodyFat.value = log.body_fat_percentage !== null;
    bodyFatValue.value = log.body_fat_percentage ?? 20;
    form.memo = log.memo ?? '';
}

function submit() {
    form.body_fat_percentage = trackBodyFat.value ? bodyFatValue.value : null;

    if (isEditing.value) {
        form.patch(route('body-logs.update', editingLogId.value), {
            preserveScroll: true,
            onSuccess: () => startCreate(),
        });
    } else {
        form.post(route('body-logs.store'), {
            preserveScroll: true,
            onSuccess: () => startCreate(),
        });
    }
}

// ------------------------------------------------------------------
// 削除確認
// ------------------------------------------------------------------

const confirmingDeleteLog = ref(null);
const isDeleting = ref(false);

function confirmDelete(log) {
    confirmingDeleteLog.value = log;
}

function closeDeleteModal() {
    confirmingDeleteLog.value = null;
}

function deleteLog() {
    if (!confirmingDeleteLog.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(route('body-logs.destroy', confirmingDeleteLog.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            if (editingLogId.value === confirmingDeleteLog.value?.id) {
                startCreate();
            }
            closeDeleteModal();
        },
    });
}

// ------------------------------------------------------------------
// 推移グラフ(既存の ExerciseHistoryChart.vue を再利用。Issue #21)
// ------------------------------------------------------------------

const chartPoints = computed(() =>
    // グラフは日付昇順を前提にしている(一覧は降順のため反転する)。
    [...props.logs]
        .reverse()
        .map((log) => ({ date: log.measured_on, value: log.weight_kg, weight: 0, reps: 0 })),
);

// props.logs が(削除・追加で)入れ替わったら、開いていた編集フォームが
// 古いデータを指したままにならないよう新規記録モードに戻す。
watch(
    () => props.logs,
    () => {
        if (isEditing.value && !props.logs.some((log) => log.id === editingLogId.value)) {
            startCreate();
        }
    },
);
</script>

<template>
    <Head title="体重" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-medium text-ink">体重</h2>
        </template>

        <!-- 履歴画面と同じタブ(Issue #21)。互いに行き来できるようにする。 -->
        <div class="flex border-b border-line" role="tablist">
            <Link
                :href="route('history.index')"
                class="label-micro flex h-11 flex-1 items-center justify-center border-b-2 border-transparent text-center text-ink-2 transition-colors hover:text-ink"
                role="tab"
                aria-selected="false"
            >
                種目別
            </Link>
            <span
                class="label-micro flex h-11 flex-1 items-center justify-center border-b-2 border-accent text-center text-accent"
                role="tab"
                aria-selected="true"
            >
                体重
            </span>
        </div>

        <div
            v-if="page.props.flash?.success"
            class="mt-4 border border-ok px-4 py-3 text-sm text-ok"
        >
            {{ page.props.flash.success }}
        </div>

        <!-- 記録フォーム -->
        <form class="mt-6" @submit.prevent="submit">
            <p class="label-micro text-[11px] text-ink-3">
                {{ isEditing ? '記録を編集' : '体重を記録' }}
            </p>

            <div class="mt-3">
                <label class="label-micro block text-[10px] text-ink-3" for="body-log-date"
                    >日付</label
                >
                <input
                    id="body-log-date"
                    v-model="form.measured_on"
                    type="date"
                    :max="todayString()"
                    class="mt-1.5 h-11 w-full border border-line bg-surface px-3 text-sm text-ink"
                />
                <p v-if="form.errors.measured_on" class="mt-1 text-xs text-warn">
                    {{ form.errors.measured_on }}
                </p>
            </div>

            <div class="mt-4">
                <p class="label-micro text-[10px] text-ink-3">体重</p>
                <NumberStepper
                    v-model="form.weight_kg"
                    class="mt-1.5"
                    :step="0.1"
                    :min="1"
                    :max="999.9"
                    unit="kg"
                />
                <p v-if="form.errors.weight_kg" class="mt-1 text-xs text-warn">
                    {{ form.errors.weight_kg }}
                </p>
            </div>

            <div class="mt-4">
                <label class="flex h-11 items-center gap-2 text-sm text-ink-2">
                    <input v-model="trackBodyFat" type="checkbox" class="h-5 w-5 border-line" />
                    体脂肪率も記録する
                </label>
                <NumberStepper
                    v-if="trackBodyFat"
                    v-model="bodyFatValue"
                    class="mt-1.5"
                    :step="0.1"
                    :min="0"
                    :max="99.9"
                    unit="%"
                />
                <p v-if="form.errors.body_fat_percentage" class="mt-1 text-xs text-warn">
                    {{ form.errors.body_fat_percentage }}
                </p>
            </div>

            <div class="mt-4">
                <label class="label-micro block text-[10px] text-ink-3" for="body-log-memo"
                    >メモ(任意)</label
                >
                <input
                    id="body-log-memo"
                    v-model="form.memo"
                    type="text"
                    maxlength="255"
                    class="mt-1.5 h-11 w-full border border-line bg-surface px-3 text-sm text-ink"
                    placeholder="例: 朝食前"
                />
            </div>

            <div class="mt-5 flex gap-3">
                <PrimaryButton type="submit" class="h-12 flex-1" :disabled="form.processing">
                    {{ isEditing ? '更新する' : '記録する' }}
                </PrimaryButton>
                <SecondaryButton v-if="isEditing" type="button" @click="startCreate">
                    キャンセル
                </SecondaryButton>
            </div>
        </form>

        <Rule class="mt-8" />

        <!-- 期間フィルタ(履歴画面と同じ選択肢) -->
        <div class="flex gap-2">
            <button
                v-for="option in periodOptions"
                :key="option.value"
                type="button"
                class="label-micro h-10 flex-1 border text-[11px] transition-colors"
                :class="
                    option.value === period
                        ? 'border-accent text-accent'
                        : 'border-line text-ink-2 hover:border-ink-3'
                "
                @click="selectPeriod(option.value)"
            >
                {{ option.label }}
            </button>
        </div>

        <!-- 推移グラフ。0件・1件でも壊れない(ExerciseHistoryChart.vue の既存の空表示に乗る)。 -->
        <div class="mt-6">
            <p class="label-micro text-[10px] text-ink-3">体重の推移 (kg)</p>
            <ExerciseHistoryChart class="mt-2" :points="chartPoints" metric="weight" label="体重" />
        </div>

        <Rule class="mt-6" />

        <!-- 一覧・編集・削除 -->
        <div class="mt-4">
            <p class="label-micro text-[10px] text-ink-3">記録一覧</p>
            <div v-if="logs.length === 0" class="mt-3 text-sm text-ink-2">
                この期間の記録がありません。
            </div>
            <div v-else class="mt-2 divide-y divide-line">
                <div v-for="log in logs" :key="log.id" class="py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="label-micro text-[10px] text-ink-3 tabular-nums">{{
                                log.measured_on
                            }}</span>
                            <p class="mt-1 tabular-nums text-ink">
                                <span class="text-lg">{{ formatNumber(log.weight_kg) }}</span
                                ><span class="text-ink-2">kg</span>
                                <span
                                    v-if="log.body_fat_percentage !== null"
                                    class="ml-3 text-sm text-ink-2"
                                >
                                    体脂肪 {{ formatNumber(log.body_fat_percentage) }}%
                                </span>
                            </p>
                            <p v-if="log.memo" class="mt-1 truncate text-xs text-ink-3">
                                {{ log.memo }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button
                                type="button"
                                class="label-micro flex h-11 items-center border border-line px-3 text-[10px] text-ink-2 transition-colors hover:border-ink-2 hover:text-ink"
                                @click="startEdit(log)"
                            >
                                編集
                            </button>
                            <button
                                type="button"
                                class="label-micro flex h-11 items-center border border-line px-3 text-[10px] text-warn transition-colors hover:border-warn"
                                @click="confirmDelete(log)"
                            >
                                削除
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="confirmingDeleteLog !== null" @close="closeDeleteModal">
            <div class="p-6">
                <h2 class="text-base font-medium text-ink">この記録を削除しますか?</h2>
                <p class="mt-2 text-sm text-ink-2">
                    <span class="font-medium text-ink">{{ confirmingDeleteLog?.measured_on }}</span>
                    の体重記録を削除します。この操作は取り消せません。
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDeleteModal">キャンセル</SecondaryButton>
                    <DangerButton :disabled="isDeleting" @click="deleteLog">削除する</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
