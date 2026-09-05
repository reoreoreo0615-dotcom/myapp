<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Rule from '@/Components/Rule.vue';
import StatValue from '@/Components/StatValue.vue';
import { formatNumber } from '@/Utils/format';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // { hasRecords, weeklyVolume: { thisWeek, lastWeek, changePercent }, streakWeeks,
    //   monthlyRecordUpdates, latestPersonalBest: { exerciseName, isBodyweight, weight, reps, date } | null,
    //   activeWorkoutId: number|null, routinesCount: number }
    summary: {
        type: Object,
        required: true,
    },
});

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
</script>

<template>
    <Head title="ダッシュボード" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-medium text-ink">ダッシュボード</h2>
        </template>

        <div>
            <p class="label-micro text-[11px] text-ink-3">次にやること</p>
            <Link
                :href="nextAction.href"
                class="mt-2 flex h-12 w-full items-center justify-center gap-2 bg-accent px-6 font-mono text-xs uppercase tracking-widest text-ground transition-opacity hover:opacity-90"
            >
                {{ nextAction.label }}
            </Link>
        </div>

        <Rule class="mt-8" />

        <div v-if="!summary.hasRecords" class="mt-8 text-center">
            <p class="text-sm text-ink-2">まだ記録がありません。</p>
        </div>

        <div v-else class="mt-8 grid grid-cols-2 gap-x-6 gap-y-8">
            <div class="min-w-0">
                <StatValue label="今週の総挙上重量" :value="formatVolume(summary.weeklyVolume.thisWeek)" unit="kg" />
                <p v-if="changeLabel" class="label-micro mt-1.5 text-[10px]" :class="changeColorClass">
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
                        {{ latestPersonalBest.reps }}<span class="ml-1 text-base text-ink-2">回</span>
                    </template>
                    <template v-else>
                        {{ formatNumber(latestPersonalBest.weight) }}<span class="text-base text-ink-2">kg</span>
                        <span class="text-xl text-ink-2"> × </span>{{ latestPersonalBest.reps }}
                    </template>
                </p>
                <p class="label-micro mt-1 truncate text-[10px] text-ink-3">
                    {{ latestPersonalBest.exerciseName }}・{{ formatMonthDay(latestPersonalBest.date) }}
                </p>
            </div>
        </div>

        <Rule v-if="summary.hasRecords" class="mt-8" />
    </AuthenticatedLayout>
</template>
