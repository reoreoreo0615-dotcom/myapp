<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ExerciseHistoryChart from '@/Components/ExerciseHistoryChart.vue';
import Rule from '@/Components/Rule.vue';
import StatValue from '@/Components/StatValue.vue';
import { formatNumber } from '@/Utils/format';
import { Head, router } from '@inertiajs/vue3';
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
    // null、または { exercise, chart: {metric, metric_label, points}, sets, personalBest }
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
</script>

<template>
    <Head title="種目別履歴" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-medium text-ink">履歴</h2>
        </template>

        <div>
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
                {{ isBodyweightMetric ? '自重種目・トップセットのレップ数で推移を表示' : '推定1RM(Epley式)の推移' }}
            </p>

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
            <p v-else class="mt-4 text-sm text-ink-2">この期間の記録がまだありません。</p>

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
                        <span class="label-micro w-10 shrink-0 text-right text-[9px] text-ink-3">{{ set.is_warmup ? 'W' : '' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
