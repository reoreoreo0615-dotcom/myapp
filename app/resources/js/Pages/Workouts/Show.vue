<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Rule from '@/Components/Rule.vue';
import SetRow from '@/Components/SetRow.vue';
import TargetDisplay from '@/Components/TargetDisplay.vue';
import { formatNumber } from '@/Utils/format';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

const page = usePage();

const props = defineProps({
    workout: {
        type: Object,
        required: true,
    },
    // 終了済み(finished_at 確定済み)のワークアウトは閲覧専用になる。
    isFinished: {
        type: Boolean,
        required: true,
    },
    canAddExercises: {
        type: Boolean,
        required: true,
    },
    exercises: {
        type: Array,
        required: true,
    },
    // exercise_id をキーにした { prev: [{weight,reps}], target: {weight,reps,type}|null }
    progression: {
        type: Object,
        required: true,
    },
    // exercise_id をキーにした [{id,set_number,weight,reps,is_warmup}]
    recordedSets: {
        type: Object,
        required: true,
    },
});

const muscleGroupLabels = {
    chest: '胸',
    back: '背中',
    shoulders: '肩',
    legs: '脚',
    arms: '腕',
    core: '体幹',
};

const exerciseById = computed(() => {
    const map = {};
    for (const exercise of props.exercises) {
        map[exercise.id] = exercise;
    }
    return map;
});

/* --- 表示中の種目(メニューありなら固定、メニューなしなら記録済み種目 + 追加分) --- */

const visibleExerciseIds = ref(
    new Set(
        props.canAddExercises
            ? Object.keys(props.recordedSets).map(Number)
            : props.exercises.map((exercise) => exercise.id),
    ),
);

const visibleExercises = computed(() =>
    props.exercises.filter((exercise) => visibleExerciseIds.value.has(exercise.id)),
);

const remainingExercises = computed(() =>
    props.exercises.filter((exercise) => !visibleExerciseIds.value.has(exercise.id)),
);

const showPicker = ref(false);

function addExercise(exercise) {
    visibleExerciseIds.value.add(exercise.id);
    visibleExerciseIds.value = new Set(visibleExerciseIds.value);
    ensureDraft(exercise.id);
    showPicker.value = false;
}

/* --- 次に記録するセットの入力値(種目ごと)。目標値をプリセットする --- */

const draft = reactive({});

function targetOrDefault(exerciseId) {
    const target = props.progression[exerciseId]?.target ?? null;
    if (target) {
        return { weight: target.weight, reps: target.reps };
    }
    const exercise = exerciseById.value[exerciseId];
    return { weight: 0, reps: exercise?.target_rep_min ?? 8 };
}

function ensureDraft(exerciseId) {
    if (!draft[exerciseId]) {
        const base = targetOrDefault(exerciseId);
        draft[exerciseId] = { weight: base.weight, reps: base.reps, isWarmup: false };
    }
    return draft[exerciseId];
}

for (const exercise of visibleExercises.value) {
    ensureDraft(exercise.id);
}

function nextSetNumber(exerciseId) {
    const sets = props.recordedSets[exerciseId] ?? [];
    if (sets.length === 0) {
        return 1;
    }
    return Math.max(...sets.map((set) => set.set_number)) + 1;
}

/* --- セットの記録(タップ1回で1セット)。二重送信は種目ごとに抑止する --- */

const processing = reactive({});

function recordSet(exercise) {
    if (processing[exercise.id]) {
        return;
    }
    processing[exercise.id] = true;

    const values = ensureDraft(exercise.id);
    const clientRequestId =
        typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `${Date.now()}-${Math.random()}`;

    router.post(
        route('workouts.sets.store', props.workout.id),
        {
            exercise_id: exercise.id,
            weight: values.weight,
            reps: values.reps,
            is_warmup: values.isWarmup,
            client_request_id: clientRequestId,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                processing[exercise.id] = false;
                // 目標値は据え置き(セッション中は同じ目標を提示し続ける)。
                // ウォームアップの選択だけは1セットごとにリセットする。
                const base = targetOrDefault(exercise.id);
                draft[exercise.id] = { weight: base.weight, reps: base.reps, isWarmup: false };
            },
        },
    );
}

/* --- 既存セットの編集・削除 --- */

const editingSetId = ref(null);
const editDraft = reactive({ weight: 0, reps: 0 });
const editProcessing = ref(false);

function startEdit(set) {
    editingSetId.value = set.id;
    editDraft.weight = set.weight;
    editDraft.reps = set.reps;
}

function cancelEdit() {
    editingSetId.value = null;
}

function saveEdit(set) {
    editProcessing.value = true;
    router.patch(
        route('workouts.sets.update', [props.workout.id, set.id]),
        {
            weight: editDraft.weight,
            reps: editDraft.reps,
            is_warmup: set.is_warmup,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                editProcessing.value = false;
                editingSetId.value = null;
            },
        },
    );
}

function deleteSet(set) {
    if (!confirm('このセットを削除しますか?')) {
        return;
    }
    router.delete(route('workouts.sets.destroy', [props.workout.id, set.id]), {
        preserveScroll: true,
        preserveState: true,
    });
}

/* --- 前回セットの表示 --- */

function prevSetsText(exerciseId) {
    const sets = props.progression[exerciseId]?.prev ?? [];
    if (sets.length === 0) {
        return '前回の記録はありません';
    }
    return sets.map((set) => `${formatNumber(set.weight)}×${set.reps}`).join(' / ');
}

/* --- 経過時間タイマー(終了済みなら finished_at で止まる) --- */

const now = ref(Date.now());
let timerHandle = null;

onMounted(() => {
    if (props.isFinished) {
        return;
    }
    timerHandle = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (timerHandle) {
        clearInterval(timerHandle);
    }
});

const elapsedText = computed(() => {
    if (!props.workout.started_at) {
        return '--:--';
    }
    const startMs = new Date(props.workout.started_at).getTime();
    const endMs =
        props.isFinished && props.workout.finished_at
            ? new Date(props.workout.finished_at).getTime()
            : now.value;
    const diffSec = Math.max(0, Math.floor((endMs - startMs) / 1000));
    const minutes = Math.floor(diffSec / 60);
    const seconds = diffSec % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

/* --- トレーニング終了 --- */

const finishing = ref(false);

function finishWorkout() {
    if (finishing.value) {
        return;
    }
    if (!confirm('トレーニングを終了しますか?終了後はセットの追加・編集ができなくなります。')) {
        return;
    }
    finishing.value = true;

    router.patch(
        route('workouts.finish', props.workout.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                finishing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="ワークアウト記録" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="min-w-0 truncate text-lg font-medium text-ink">
                    {{ workout.routine_name ?? 'フリーワークアウト' }}
                </h2>
                <div class="label-micro flex shrink-0 items-baseline gap-3 text-ink-2">
                    <span>{{ workout.performed_on }}</span>
                    <span class="font-display text-base tabular-nums text-ink">{{
                        elapsedText
                    }}</span>
                </div>
            </div>
        </template>

        <div
            v-if="page.props.flash?.success"
            class="mb-4 border border-ok px-4 py-3 text-sm text-ok"
        >
            {{ page.props.flash.success }}
        </div>
        <div
            v-if="page.props.flash?.info"
            class="mb-4 border border-line px-4 py-3 text-sm text-ink-2"
        >
            {{ page.props.flash.info }}
        </div>

        <div v-if="isFinished" class="label-micro mb-6 border border-line px-4 py-3 text-ink-2">
            終了済み・閲覧専用
        </div>

        <div v-if="visibleExercises.length === 0" class="py-8 text-center text-sm text-ink-2">
            記録する種目がありません。下から種目を追加してください。
        </div>

        <div v-else class="space-y-8">
            <section v-for="exercise in visibleExercises" :key="exercise.id">
                <div class="flex items-baseline justify-between gap-2">
                    <h3 class="min-w-0 truncate font-medium text-ink">{{ exercise.name }}</h3>
                    <span class="label-micro shrink-0 text-[10px] text-ink-3">
                        {{ muscleGroupLabels[exercise.muscle_group] ?? exercise.muscle_group }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-ink-2">前回: {{ prevSetsText(exercise.id) }}</p>

                <TargetDisplay
                    v-if="progression[exercise.id]?.target"
                    class="mt-3"
                    label="今日の目標"
                    :weight="progression[exercise.id].target.weight"
                    :reps="progression[exercise.id].target.reps"
                    :improvement="progression[exercise.id].target.type"
                />
                <p v-else class="label-micro mt-3 text-[11px] text-ink-3">
                    初めての種目です。最初のセットを記録してください。
                </p>

                <Rule class="mt-4" />

                <div>
                    <SetRow
                        v-for="set in recordedSets[exercise.id] ?? []"
                        :key="set.id"
                        :set-number="set.set_number"
                        :weight="editingSetId === set.id ? editDraft.weight : set.weight"
                        :reps="editingSetId === set.id ? editDraft.reps : set.reps"
                        :weight-step="exercise.weight_increment"
                        completed
                        :editable="!isFinished"
                        :warmup="set.is_warmup"
                        :editing="editingSetId === set.id"
                        :processing="editProcessing && editingSetId === set.id"
                        @update:weight="editDraft.weight = $event"
                        @update:reps="editDraft.reps = $event"
                        @edit-start="startEdit(set)"
                        @edit-cancel="cancelEdit"
                        @edit-save="saveEdit(set)"
                        @delete="deleteSet(set)"
                    />

                    <SetRow
                        v-if="!isFinished && draft[exercise.id]"
                        :set-number="nextSetNumber(exercise.id)"
                        :weight="draft[exercise.id].weight"
                        :reps="draft[exercise.id].reps"
                        :weight-step="exercise.weight_increment"
                        :processing="!!processing[exercise.id]"
                        @update:weight="draft[exercise.id].weight = $event"
                        @update:reps="draft[exercise.id].reps = $event"
                        @record="recordSet(exercise)"
                    />
                </div>

                <label
                    v-if="!isFinished && draft[exercise.id]"
                    class="mt-2 flex h-11 items-center gap-2 text-sm text-ink-2"
                >
                    <Checkbox v-model:checked="draft[exercise.id].isWarmup" />
                    次のセットをウォームアップとして記録する
                </label>
            </section>
        </div>

        <template v-if="!isFinished">
            <Rule class="mt-8" />
            <button
                type="button"
                class="inline-flex h-12 w-full items-center justify-center gap-2 border border-line font-mono text-xs uppercase tracking-widest text-ink transition-colors hover:border-ink-2 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="finishing"
                @click="finishWorkout"
            >
                {{ finishing ? '終了処理中…' : 'トレーニング終了' }}
            </button>
        </template>

        <template v-if="canAddExercises">
            <Rule class="mt-8" />

            <div v-if="showPicker" class="mt-4">
                <p class="label-micro mb-2 text-[11px] text-ink-3">種目を選択</p>
                <div class="max-h-64 divide-y divide-line overflow-y-auto border border-line">
                    <button
                        v-for="exercise in remainingExercises"
                        :key="exercise.id"
                        type="button"
                        class="flex min-h-[44px] w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm text-ink hover:bg-surface"
                        @click="addExercise(exercise)"
                    >
                        <span class="truncate">{{ exercise.name }}</span>
                        <span class="label-micro shrink-0 text-[10px] text-ink-3">
                            {{ muscleGroupLabels[exercise.muscle_group] ?? exercise.muscle_group }}
                        </span>
                    </button>
                    <p v-if="remainingExercises.length === 0" class="px-3 py-3 text-sm text-ink-2">
                        追加できる種目がありません。
                    </p>
                </div>
                <button
                    type="button"
                    class="mt-2 h-11 w-full border border-line font-mono text-xs uppercase tracking-widest text-ink-2"
                    @click="showPicker = false"
                >
                    閉じる
                </button>
            </div>
            <button
                v-else
                type="button"
                class="mt-4 inline-flex h-12 w-full items-center justify-center gap-2 border border-line font-mono text-xs uppercase tracking-widest text-ink transition-colors hover:border-ink-2"
                @click="showPicker = true"
            >
                + 種目を追加
            </button>
        </template>
    </AuthenticatedLayout>
</template>
