<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PlateauNotice from '@/Components/PlateauNotice.vue';
import RatioBar from '@/Components/RatioBar.vue';
import StatValue from '@/Components/StatValue.vue';
import { formatNumber } from '@/Utils/format';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // { hasRecords, weeklyVolume: { thisWeek, lastWeek, changePercent }, streakWeeks,
    //   monthlyRecordUpdates, latestPersonalBest: { exerciseName, isBodyweight, weight, reps, date } | null,
    //   activeWorkoutId: number|null, routinesCount: number,
    //   bodyWeight: { current, measuredOn, changeFromPrevious } | null,
    //   plateauExercises: [{ exerciseId, exerciseName, isBodyweight, status, sessionsWithoutUpdate,
    //                        baseline: {weight,reps}, suggestions: [...] }],
    //   muscleBalance: { sufficientData, counts: {push,pull,legs,core}, pushPullTotal,
    //                     isImbalanced, dominant: 'push'|'pull'|null, ratio } }
    summary: {
        type: Object,
        required: true,
    },
});

// PlateauNotice.vue は History / 記録画面と共通の(snake_case の)形を受け取るため、
// このページの camelCase な summary.plateauExercises の要素をその場で変換する。
function toPlateauNoticeShape(item) {
    return {
        status: item.status,
        sessions_without_update: item.sessionsWithoutUpdate,
        baseline: item.baseline,
        suggestions: item.suggestions,
    };
}

// Issue #19: 「メニューを作ってから記録する」という画面間のつながりが
// 分からない、というフィードバックへの対処。優先順位は3択:
// 進行中のトレーニングがあれば最優先で再開させ、無ければメニューの
// 有無で「まず作る」か「もう始める」かを出し分ける。
const nextAction = computed(() => {
    if (props.summary.activeWorkoutId) {
        return {
            label: 'トレーニングを再開',
            href: route('workouts.show', props.summary.activeWorkoutId),
        };
    }
    if (props.summary.routinesCount === 0) {
        return {
            label: 'まずメニューを作る',
            href: route('routines.create'),
        };
    }
    return {
        label: 'トレーニングを始める',
        href: route('workouts.create'),
    };
});

// 総挙上重量は "12,480" のように桁区切りで表示する(formatNumber は
// 桁区切りをしないため、ダッシュボード専用にここで整形する)。
function formatVolume(value) {
    return new Intl.NumberFormat('ja-JP', { maximumFractionDigits: 0 }).format(Math.round(value));
}

// 'YYYY-MM-DD' -> '9/3'
function formatMonthDay(isoDate) {
    const [, month, day] = isoDate.split('-');
    return `${Number(month)}/${Number(day)}`;
}

const changePercent = computed(() => props.summary.weeklyVolume.changePercent);

const changeLabel = computed(() => {
    if (changePercent.value === null) {
        // 今週まだ記録が無い場合、比率ではなく事実だけを伝える(週の途中で
        // 「-100%」と出すと、単に未トレーニングなだけなのに後退したと読める)
        if (props.summary.weeklyVolume.thisWeek === 0) {
            return '今週はまだ記録なし';
        }
        return '先週の記録なし';
    }
    const sign = changePercent.value > 0 ? '+' : '';
    return `先週比 ${sign}${changePercent.value}%`;
});

const changeColorClass = computed(() => {
    if (changePercent.value === null) {
        return 'text-ink-3';
    }
    if (changePercent.value > 0) {
        return 'text-ok';
    }
    if (changePercent.value < 0) {
        return 'text-warn';
    }
    return 'text-ink-3';
});

const latestPersonalBest = computed(() => props.summary.latestPersonalBest);

// Issue #21: 体重タイル。増減そのものに良し悪しは無い(増量期か減量期かで
// 意味が逆になる)ため、ok/warn は使わず中立色で「事実」だけを示す。
const bodyWeightChange = computed(() => props.summary.bodyWeight?.changeFromPrevious ?? null);

const bodyWeightChangeLabel = computed(() => {
    if (bodyWeightChange.value === null) {
        return '前回の記録なし';
    }
    const sign = bodyWeightChange.value > 0 ? '+' : '';
    return `前回から ${sign}${formatNumber(bodyWeightChange.value)}kg`;
});
</script>

<template>
    <Head title="ダッシュボード" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-2xl font-semibold tracking-tight text-ink">ダッシュボード</h2>
        </template>

        <div>
            <p class="label-micro text-[11px] text-ink-3">次にやること</p>
            <Link
                :href="nextAction.href"
                class="mt-2 inline-flex rounded-full h-11 w-full items-center justify-center gap-2 bg-accent px-6 text-sm font-medium text-white transition-opacity hover:opacity-85 sm:w-auto sm:min-w-64"
            >
                {{ nextAction.label }}
            </Link>
        </div>

        <!--
            体重タイル(Issue #21)。下部固定ナビを増やさず、ここからの導線
            (と履歴画面のタブ)で体重記録画面に行けるようにしている。
            ワークアウト記録の有無とは無関係に、体重だけ先に記録している
            ユーザーもいるため hasRecords の外に置く。
        -->
        <div class="card mt-6 p-5">
            <div v-if="summary.bodyWeight" class="flex items-end justify-between gap-4">
                <div class="min-w-0">
                    <StatValue label="体重" :value="summary.bodyWeight.current" unit="kg" />
                    <p class="label-micro mt-1.5 text-[10px] text-ink-2">
                        {{ bodyWeightChangeLabel }}
                    </p>
                </div>
                <Link
                    :href="route('body-logs.index')"
                    class="flex h-9 shrink-0 items-center rounded-full bg-surface px-4 text-sm font-medium text-ink-2 transition-colors hover:text-ink"
                >
                    記録する
                </Link>
            </div>
            <Link
                v-else
                :href="route('body-logs.index')"
                class="flex items-center justify-between text-sm text-ink-2 transition-colors hover:text-ink"
            >
                <span>体重を記録する</span>
                <span aria-hidden="true">&rsaquo;</span>
            </Link>
        </div>

        <div v-if="!summary.hasRecords" class="mt-8 text-center">
            <p class="text-sm text-ink-2">まだ記録がありません。</p>
        </div>

        <div v-else class="card mt-6 grid grid-cols-2 gap-x-6 gap-y-8 p-5 lg:grid-cols-4">
            <div class="min-w-0">
                <StatValue
                    label="今週の総挙上重量"
                    :value="formatVolume(summary.weeklyVolume.thisWeek)"
                    unit="kg"
                />
                <p
                    v-if="changeLabel"
                    class="label-micro mt-1.5 text-[10px]"
                    :class="changeColorClass"
                >
                    {{ changeLabel }}
                </p>
            </div>

            <div class="min-w-0">
                <StatValue label="連続記録" :value="summary.streakWeeks" unit="週" />
            </div>

            <div class="min-w-0">
                <StatValue
                    label="今月の記録更新"
                    :value="summary.monthlyRecordUpdates"
                    unit="種目"
                    :accent="summary.monthlyRecordUpdates > 0"
                />
            </div>

            <div v-if="latestPersonalBest" class="min-w-0">
                <p class="label-micro text-[11px] text-ink-3">直近の自己ベスト</p>
                <p class="mt-1.5 truncate font-display text-3xl tabular-nums text-accent">
                    <template v-if="latestPersonalBest.isBodyweight">
                        {{ latestPersonalBest.reps
                        }}<span class="ml-1 text-base text-ink-2">回</span>
                    </template>
                    <template v-else>
                        {{ formatNumber(latestPersonalBest.weight)
                        }}<span class="text-base text-ink-2">kg</span>
                        <span class="text-xl text-ink-2"> × </span>{{ latestPersonalBest.reps }}
                    </template>
                </p>
                <p class="label-micro mt-1 truncate text-[10px] text-ink-3">
                    {{ latestPersonalBest.exerciseName }}・{{
                        formatMonthDay(latestPersonalBest.date)
                    }}
                </p>
            </div>
        </div>

        <!--
            部位バランス(Issue #24②)。記録が少ない期間(直近4週の push+pull
            セット数が少ない)は誤解を招くため、判定不能なときは何も出さない。
        -->
        <div v-if="summary.muscleBalance.sufficientData" class="card mt-6 p-5">
            <p class="label-micro text-[11px] text-ink-3">部位バランス(直近4週・セット数)</p>
            <RatioBar
                class="mt-3"
                :left-value="summary.muscleBalance.counts.push"
                :right-value="summary.muscleBalance.counts.pull"
                left-label="PUSH"
                right-label="PULL"
                :warn="summary.muscleBalance.isImbalanced"
            />
            <p v-if="summary.muscleBalance.isImbalanced" class="mt-2 text-xs text-caution">
                {{
                    summary.muscleBalance.dominant === 'push' ? '押す種目' : '引く種目'
                }}に偏っているかもしれません。
            </p>
        </div>

        <!-- 停滞している種目(Issue #24①)。無い場合は何も表示しない。 -->
        <div v-if="summary.plateauExercises.length > 0" class="card mt-6 p-5">
            <p class="label-micro text-[11px] text-ink-3">停滞している種目</p>
            <div class="mt-3 space-y-4">
                <div v-for="item in summary.plateauExercises" :key="item.exerciseId">
                    <Link
                        :href="route('history.index', { exercise_id: item.exerciseId })"
                        class="text-sm text-ink hover:text-accent"
                    >
                        {{ item.exerciseName }}
                    </Link>
                    <PlateauNotice :plateau="toPlateauNoticeShape(item)" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
