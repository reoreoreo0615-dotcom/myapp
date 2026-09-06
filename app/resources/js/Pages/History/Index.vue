<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ExerciseHistoryChart from '@/Components/ExerciseHistoryChart.vue';
import PlateauNotice from '@/Components/PlateauNotice.vue';
import Rule from '@/Components/Rule.vue';
import StatValue from '@/Components/StatValue.vue';
import { formatNumber } from '@/Utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // [{ id, name, muscle_group, is_bodyweight }]
    exercises: {
        type: Array,
        required: true,
    },
    // '3m' | '6m' | 'all'
    period: {
        type: String,
        required: true,
    },
    selectedExerciseId: {
        type: [Number, String, null],
        default: null,
    },
    // null、または { exercise, chart: {metric, metric_label, points}, sets, personalBest,
    //   latestBodyweightRatio: {date, ratio, body_weight_kg, estimated_one_rep_max_with_bodyweight} | null,
    //   plateau: { status, sessions_without_update, baseline: {weight,reps}, suggestions: [...] } | null }
    history: {
        type: Object,
        default: null,
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

const periodOptions = [
    { value: '3m', label: '3ヶ月' },
    { value: '6m', label: '6ヶ月' },
    { value: 'all', label: '全期間' },
];

function navigate(exerciseId, period) {
    router.get(
        route('history.index'),
        {
            exercise_id: exerciseId ?? undefined,
            period,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onExerciseChange(event) {
    const value = event.target.value;
    navigate(value === '' ? null : Number(value), props.period);
}

function selectPeriod(value) {
    if (value === props.period) {
        return;
    }
    navigate(props.selectedExerciseId, value);
}

const isBodyweightMetric = computed(() => props.history?.chart?.metric === 'reps');

// 'YYYY-MM-DD' -> '9/3'(Dashboard.vue と同じ整形)
function formatMonthDay(isoDate) {
    const [, month, day] = isoDate.split('-');
    return `${Number(month)}/${Number(day)}`;
}
</script>

<template>
    <Head title="種目別履歴" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-medium text-ink">履歴</h2>
        </template>

        <!--
            体重記録(Issue #21)への導線。下部固定ナビにこれ以上項目を増やすと
            375px 幅で窮屈になるため追加せず、関連性の高いこの履歴画面に
            「タブ」として置く(体重画面側にも同じタブを置いて行き来できる)。
        -->
        <div class="flex border-b border-line" role="tablist">
            <span
                class="label-micro flex h-11 flex-1 items-center justify-center border-b-2 border-accent text-center text-accent"
                role="tab"
                aria-selected="true"
            >
                種目別
            </span>
            <Link
                :href="route('body-logs.index')"
                class="label-micro flex h-11 flex-1 items-center justify-center border-b-2 border-transparent text-center text-ink-2 transition-colors hover:text-ink"
                role="tab"
                aria-selected="false"
            >
                体重
            </Link>
        </div>

        <div class="mt-4">
            <label class="label-micro block text-[11px] text-ink-3" for="history-exercise">種目</label>
            <select
                id="history-exercise"
                class="mt-1.5 h-11 w-full border border-line bg-surface px-3 text-sm text-ink"
                :value="selectedExerciseId ?? ''"
                @change="onExerciseChange"
            >
                <option value="">種目を選択してください</option>
                <option v-for="exercise in exercises" :key="exercise.id" :value="exercise.id">
                    {{ exercise.name }}({{ muscleGroupLabels[exercise.muscle_group] ?? exercise.muscle_group }})
                </option>
            </select>
        </div>

        <div v-if="selectedExerciseId" class="mt-4 flex gap-2">
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

        <div v-if="!history" class="mt-10 text-center text-sm text-ink-2">
            種目を選択すると、記録の推移が表示されます。
        </div>

        <div v-else class="mt-6">
            <h3 class="truncate font-medium text-ink">{{ history.exercise.name }}</h3>
            <p class="label-micro mt-1 text-[10px] text-ink-3">
                {{
                    isBodyweightMetric
                        ? '自重種目・トップセットのレップ数で推移を表示'
                        : '推定1RM(1回だけ挙げられる重さの目安)の推移'
                }}
            </p>

            <PlateauNotice v-if="history.plateau" :plateau="history.plateau" />

            <Rule class="mt-4" />

            <div v-if="history.personalBest" class="mt-4 flex flex-wrap gap-x-6 gap-y-4">
                <StatValue
                    v-if="history.personalBest.metric === '1rm'"
                    label="自己ベスト・重量"
                    :value="history.personalBest.max_weight"
                    unit="kg"
                />
                <StatValue
                    v-if="history.personalBest.metric === '1rm'"
                    label="自己ベスト・推定1RM"
                    :value="history.personalBest.max_estimated_1rm"
                    unit="kg"
                    accent
                />
                <StatValue
                    v-if="history.personalBest.metric === 'reps'"
                    label="自己ベスト・レップ数"
                    :value="history.personalBest.max_reps"
                    unit="回"
                    accent
                />
                <StatValue
                    v-if="history.personalBest.metric === 'reps' && history.personalBest.max_weight > 0"
                    label="自己ベスト・加重"
                    :value="history.personalBest.max_weight"
                    unit="kg"
                />
            </div>
            <div v-else class="mt-4 text-sm text-ink-2">
                <p>この期間の記録がまだありません。</p>
                <Link :href="route('workouts.create')" class="mt-2 inline-block text-accent underline underline-offset-2">
                    まず記録する &rarr;
                </Link>
            </div>

            <Rule class="mt-4" />

            <!-- 体重比(Issue #21): 筋力の数字は体重を抜きにしては解釈できない、という
                 このアプリの前提を反映する表示。体重記録が無い期間は出さない。 -->
            <div v-if="history.latestBodyweightRatio" class="mt-4">
                <div class="flex flex-wrap gap-x-6 gap-y-4">
                    <StatValue
                        label="体重比"
                        :value="history.latestBodyweightRatio.ratio"
                        unit="倍"
                        accent
                    />
                    <StatValue
                        v-if="isBodyweightMetric"
                        label="体重+加重の概算1RM"
                        :value="history.latestBodyweightRatio.estimated_one_rep_max_with_bodyweight"
                        unit="kg"
                    />
                </div>
                <p class="label-micro mt-2 text-[10px] text-ink-3">
                    {{ formatMonthDay(history.latestBodyweightRatio.date) }}時点の体重
                    {{ formatNumber(history.latestBodyweightRatio.body_weight_kg) }}kg を使用。
                    <template v-if="isBodyweightMetric">
                        体重の全部が乗る前提の概算です(懸垂・ディップスなど一部の種目にのみ正確)。
                    </template>
                </p>
            </div>
            <div v-else class="mt-4 text-sm text-ink-2">
                <p>体重を記録すると、体重比(相対筋力)が表示されます。</p>
                <Link :href="route('body-logs.index')" class="mt-2 inline-block text-accent underline underline-offset-2">
                    体重を記録する &rarr;
                </Link>
            </div>

            <Rule class="mt-4" />

            <div class="mt-4">
                <p class="label-micro text-[10px] text-ink-3">{{ history.chart.metric_label }}</p>
                <ExerciseHistoryChart
                    class="mt-2"
                    :points="history.chart.points"
                    :metric="history.chart.metric"
                />
            </div>

            <Rule class="mt-6" />

            <div class="mt-4">
                <p class="label-micro text-[10px] text-ink-3">全セット一覧</p>
                <div v-if="history.sets.length === 0" class="mt-3 text-sm text-ink-2">記録がありません。</div>
                <div v-else class="mt-2 divide-y divide-line">
                    <div
                        v-for="set in history.sets"
                        :key="set.id"
                        class="flex items-center justify-between gap-3 py-2.5 text-sm"
                        :class="set.is_warmup ? 'opacity-50' : ''"
                    >
                        <span class="label-micro w-20 shrink-0 text-[10px] text-ink-3 tabular-nums">{{ set.date }}</span>
                        <span class="flex-1 text-right tabular-nums text-ink">{{ formatNumber(set.weight) }}<span class="text-ink-2">kg</span></span>
                        <span class="flex-1 text-right tabular-nums text-ink">{{ set.reps }}<span class="text-ink-2">回</span></span>
                        <span class="w-14 shrink-0 text-right tabular-nums text-ink-2">{{ set.rpe !== null ? formatNumber(set.rpe) : '—' }}</span>
                        <span class="label-micro w-12 shrink-0 text-right text-[9px] text-ink-3">{{ set.is_warmup ? 'アップ' : '' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
