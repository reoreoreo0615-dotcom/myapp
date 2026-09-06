<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import DangerButton from '@/Components/DangerButton.vue';
import FirstTimeTip from '@/Components/FirstTimeTip.vue';
import IntervalTimer from '@/Components/IntervalTimer.vue';
import Modal from '@/Components/Modal.vue';
import PlateauNotice from '@/Components/PlateauNotice.vue';
import Rule from '@/Components/Rule.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SetRow from '@/Components/SetRow.vue';
import TargetDisplay from '@/Components/TargetDisplay.vue';
import { formatNumber } from '@/Utils/format';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
    // Issue #23①: 終了済みでも「記録を修正する」で入った明示的な編集モード中は true。
    isEditing: {
        type: Boolean,
        required: true,
    },
    // セットの追加・編集・削除ができるか(未終了、または終了済み+編集モード中)。
    canEditSets: {
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
    // exercise_id をキーにした [{id,set_number,weight,reps,rpe,is_warmup}]
    recordedSets: {
        type: Object,
        required: true,
    },
    // Issue #24①: 停滞している種目のみ exercise_id をキーに含まれる
    // { status, sessions_without_update, baseline: {weight,reps}, suggestions: [...] }。
    // 記録操作を邪魔しないよう、あくまで補足情報として下に添えるだけにする。
    plateau: {
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
        draft[exerciseId] = { weight: base.weight, reps: base.reps, isWarmup: false, rpe: null };
    }
    return draft[exerciseId];
}

for (const exercise of visibleExercises.value) {
    ensureDraft(exercise.id);
}

/* --- インターバルタイマー(Issue #20) ---
 * 「セットを記録した瞬間」に自動で休憩タイマーを始める。既定の休憩秒数は
 * 種目の性質(種目マスタの target_rep_max)から決める:
 * コンパウンド(<=10)は180秒、アイソレーション(>10)は90秒。
 * ワークアウト単位の単一タイマーとして扱う(直近に記録したセットの休憩を
 * 計るのが目的なので、別種目のセットを記録したらその種目の既定値で
 * 上書きしてよい)。
 */

function defaultRestSeconds(exercise) {
    return (exercise.target_rep_max ?? 12) <= 10 ? 180 : 90;
}

const timerRef = ref(null);
const timerBarVisible = ref(false);

function nextSetNumber(exerciseId) {
    const sets = props.recordedSets[exerciseId] ?? [];
    if (sets.length === 0) {
        return 1;
    }
    return Math.max(...sets.map((set) => set.set_number)) + 1;
}

/* --- セットの記録(タップ1回で1セット)。二重送信は種目ごとに抑止する --- */

const processing = reactive({});

// Issue #19: 記録直後に「何が起きたか」を一言返す。数秒で消える控えめな
// フィードバックにとどめ、常時表示のノイズにはしない。
const recordFeedback = reactive({});
const feedbackTimers = {};

function showRecordFeedback(exerciseId, message) {
    recordFeedback[exerciseId] = message;
    clearTimeout(feedbackTimers[exerciseId]);
    feedbackTimers[exerciseId] = setTimeout(() => {
        recordFeedback[exerciseId] = null;
    }, 2500);
}

function recordSet(exercise) {
    if (processing[exercise.id]) {
        return;
    }
    processing[exercise.id] = true;

    const values = ensureDraft(exercise.id);
    const setNumber = nextSetNumber(exercise.id);
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
            rpe: values.rpe,
            is_warmup: values.isWarmup,
            client_request_id: clientRequestId,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showRecordFeedback(exercise.id, `${setNumber}セット目を記録しました`);
                timerRef.value?.start(defaultRestSeconds(exercise));
            },
            onFinish: () => {
                processing[exercise.id] = false;
                // 目標値は据え置き(セッション中は同じ目標を提示し続ける)。
                // ウォームアップ・RPEの入力だけは1セットごとにリセットする。
                const base = targetOrDefault(exercise.id);
                draft[exercise.id] = {
                    weight: base.weight,
                    reps: base.reps,
                    isWarmup: false,
                    rpe: null,
                };
            },
        },
    );
}

/* --- 既存セットの編集・削除 --- */

const editingSetId = ref(null);
const editDraft = reactive({ weight: 0, reps: 0, rpe: null });
const editProcessing = ref(false);

function startEdit(set) {
    editingSetId.value = set.id;
    editDraft.weight = set.weight;
    editDraft.reps = set.reps;
    editDraft.rpe = set.rpe ?? null;
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
            rpe: editDraft.rpe,
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
    if (!confirm('このセットを削除しますか?直後であれば元に戻せます。')) {
        return;
    }
    router.delete(route('workouts.sets.destroy', [props.workout.id, set.id]), {
        preserveScroll: true,
        preserveState: true,
    });
}

/* --- 誤削除からの復元(Issue #23③) ---
 * バックエンドは削除の直後だけ flash.undo に復元用URLを積む。
 * 「直後のみ」の担保は Inertia の flash がリクエスト単位で消えることに委ねる
 * (専用のゴミ箱画面は用意しない)。
 */
function undoDelete() {
    if (!page.props.flash?.undo) {
        return;
    }
    router.patch(page.props.flash.undo, {}, { preserveScroll: true, preserveState: true });
}

/* --- 終了済みワークアウトの編集モード(Issue #23①) ---
 * progression_snapshot は一切再計算しない。バックエンドも同様に
 * editing_started_at の付け外しだけを行い、finished_at やスナップショットには触れない。
 */
const startingEdit = ref(false);
const endingEdit = ref(false);

function startEditing() {
    if (startingEdit.value) {
        return;
    }
    startingEdit.value = true;
    router.patch(
        route('workouts.start-editing', props.workout.id),
        {},
        { preserveScroll: true, onFinish: () => (startingEdit.value = false) },
    );
}

function endEditing() {
    if (endingEdit.value) {
        return;
    }
    endingEdit.value = true;
    router.patch(
        route('workouts.end-editing', props.workout.id),
        {},
        { preserveScroll: true, onFinish: () => (endingEdit.value = false) },
    );
}

/* --- ワークアウト自体の削除(Issue #23③) --- */

const confirmingDeleteWorkout = ref(false);
const deletingWorkout = ref(false);

function deleteWorkout() {
    deletingWorkout.value = true;
    router.delete(route('workouts.destroy', props.workout.id), {
        onFinish: () => {
            deletingWorkout.value = false;
            confirmingDeleteWorkout.value = false;
        },
    });
}

/* --- 前回との関係で「今日の目標」を説明する1行 ---
 * 数字だけを並べても、それがアプリの計算結果だという核心のコンセプトが
 * 伝わらない、というフィードバックへの対処。バックエンドが今日の目標を
 * 導く際に使ったトップセット(最大重量、同重量ならより多いレップ数)と
 * 同じ選び方をここでも再現し、目標の数字と食い違わないようにする。
 */

function topPrevSet(exerciseId) {
    const sets = props.progression[exerciseId]?.prev ?? [];
    if (sets.length === 0) {
        return null;
    }
    return [...sets].sort((a, b) => {
        if (b.weight !== a.weight) {
            return b.weight - a.weight;
        }
        return b.reps - a.reps;
    })[0];
}

function progressionMessage(exerciseId) {
    const target = props.progression[exerciseId]?.target;
    const top = topPrevSet(exerciseId);
    if (!target || !top) {
        return '';
    }
    const prevText = `${formatNumber(top.weight)}kg×${top.reps}`;
    const targetText = `${formatNumber(target.weight)}kg×${target.reps}`;
    // 重量アップは「レップアップとの違いが分かるように」明示する。
    return target.type === 'weight'
        ? `前回 ${prevText} を達成 → 今日は ${targetText} に挑戦(重量が上がりました)`
        : `前回 ${prevText} を達成 → 今日は ${targetText} に挑戦`;
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
    Object.values(feedbackTimers).forEach((timer) => clearTimeout(timer));
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
            onSuccess: () => {
                timerRef.value?.clear();
            },
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
            class="mb-4 flex flex-wrap items-center justify-between gap-3 border border-ok px-4 py-3 text-sm text-ok"
        >
            <span>{{ page.props.flash.success }}</span>
            <button
                v-if="page.props.flash?.undo"
                type="button"
                class="label-micro shrink-0 text-[10px] underline"
                @click="undoDelete"
            >
                元に戻す
            </button>
        </div>
        <div
            v-if="page.props.flash?.info"
            class="mb-4 border border-line px-4 py-3 text-sm text-ink-2"
        >
            {{ page.props.flash.info }}
        </div>

        <div
            v-if="isEditing"
            class="label-micro mb-6 flex flex-wrap items-center justify-between gap-3 border border-line px-4 py-3 text-ink-2"
        >
            <span class="font-medium text-accent">修正中(セットの追加・編集・削除ができます)</span>
            <button
                type="button"
                class="text-accent disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="endingEdit"
                @click="endEditing"
            >
                {{ endingEdit ? '処理中…' : '修正を終える' }}
            </button>
        </div>
        <div
            v-else-if="isFinished"
            class="label-micro mb-6 flex flex-wrap items-center justify-between gap-3 border border-line px-4 py-3 text-ink-2"
        >
            <span>終了済み・閲覧専用</span>
            <span class="flex flex-wrap gap-4">
                <button
                    type="button"
                    class="text-accent disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="startingEdit"
                    @click="startEditing"
                >
                    記録を修正する &rarr;
                </button>
                <Link :href="route('history.index')" class="text-accent">履歴を見る &rarr;</Link>
                <Link :href="route('dashboard')" class="text-accent">ダッシュボード &rarr;</Link>
            </span>
        </div>

        <FirstTimeTip v-if="!isFinished" storage-key="overload:guide:workout-record" class="mb-6">
            目標の重量・回数は最初から入力されています。そのまま<strong class="text-ink"
                >「記録」</strong
            >を押せば1セット完了です。長押しで数値をまとめて増減できます。
        </FirstTimeTip>

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
                <p v-if="progression[exercise.id]?.target" class="mt-1 text-sm text-ink-2">
                    {{ progressionMessage(exercise.id) }}
                </p>

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

                <PlateauNotice v-if="plateau[exercise.id]" :plateau="plateau[exercise.id]" />

                <Rule class="mt-4" />

                <div>
                    <SetRow
                        v-for="set in recordedSets[exercise.id] ?? []"
                        :key="set.id"
                        :set-number="set.set_number"
                        :weight="editingSetId === set.id ? editDraft.weight : set.weight"
                        :reps="editingSetId === set.id ? editDraft.reps : set.reps"
                        :rpe="editingSetId === set.id ? editDraft.rpe : set.rpe"
                        :weight-step="exercise.weight_increment"
                        completed
                        :editable="canEditSets"
                        :warmup="set.is_warmup"
                        :editing="editingSetId === set.id"
                        :processing="editProcessing && editingSetId === set.id"
                        @update:weight="editDraft.weight = $event"
                        @update:reps="editDraft.reps = $event"
                        @update:rpe="editDraft.rpe = $event"
                        @edit-start="startEdit(set)"
                        @edit-cancel="cancelEdit"
                        @edit-save="saveEdit(set)"
                        @delete="deleteSet(set)"
                    />

                    <SetRow
                        v-if="canEditSets && draft[exercise.id]"
                        :set-number="nextSetNumber(exercise.id)"
                        :weight="draft[exercise.id].weight"
                        :reps="draft[exercise.id].reps"
                        :rpe="draft[exercise.id].rpe"
                        :weight-step="exercise.weight_increment"
                        :processing="!!processing[exercise.id]"
                        @update:weight="draft[exercise.id].weight = $event"
                        @update:reps="draft[exercise.id].reps = $event"
                        @update:rpe="draft[exercise.id].rpe = $event"
                        @record="recordSet(exercise)"
                    />
                </div>

                <p v-if="recordFeedback[exercise.id]" class="label-micro mt-2 text-[10px] text-ok">
                    {{ recordFeedback[exercise.id] }}
                </p>

                <label
                    v-if="canEditSets && draft[exercise.id]"
                    class="mt-2 flex h-11 items-center gap-2 text-sm text-ink-2"
                >
                    <Checkbox v-model:checked="draft[exercise.id].isWarmup" />
                    次のセットをウォームアップとして記録する(アップ)
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

        <Rule class="mt-8" />
        <div class="mt-4 flex justify-end">
            <button
                type="button"
                class="label-micro flex h-11 items-center border border-line px-3 text-[10px] text-warn transition-colors hover:border-warn"
                @click="confirmingDeleteWorkout = true"
            >
                このワークアウトを削除する
            </button>
        </div>

        <!-- 休憩タイマーの固定バーの分だけ、末尾コンテンツが隠れないよう空ける -->
        <div v-if="timerBarVisible" class="h-24" aria-hidden="true" />
    </AuthenticatedLayout>

    <IntervalTimer
        v-if="!isFinished"
        ref="timerRef"
        :workout-id="workout.id"
        @visible-change="timerBarVisible = $event"
    />

    <Modal :show="confirmingDeleteWorkout" @close="confirmingDeleteWorkout = false">
        <div class="p-6">
            <h2 class="text-base font-medium text-ink">このワークアウトを削除しますか?</h2>
            <p class="mt-2 text-sm text-ink-2">
                {{ workout.performed_on }} のワークアウトを削除します。削除した直後であれば
                「元に戻す」から復元できますが、それ以降は復元できません。
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="confirmingDeleteWorkout = false"
                    >キャンセル</SecondaryButton
                >
                <DangerButton :disabled="deletingWorkout" @click="deleteWorkout"
                    >削除する</DangerButton
                >
            </div>
        </div>
    </Modal>
</template>
