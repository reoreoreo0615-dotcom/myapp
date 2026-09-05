<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Rule from '@/Components/Rule.vue';
import StatValue from '@/Components/StatValue.vue';
import { formatNumber } from '@/Utils/format';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // { hasRecords, weeklyVolume: { thisWeek, lastWeek, changePercent }, streakWeeks,
    //   monthlyRecordUpdates, latestPersonalBest: { exerciseName, isBodyweight, weight, reps, date } | null }
    summary: {
        type: Object,
        required: true,
    },
});

// 総ボリュームは "12,480" のように桁区切りで表示する(formatNumber は
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

        <div v-if="!summary.hasRecords" class="mt-12 text-center">
            <p class="text-sm text-ink-2">まだ記録がありません。</p>
            <p class="mt-1 text-sm text-ink-2">最初のワークアウトを記録しましょう。</p>
            <Link
                :href="route('workouts.create')"
                class="mt-8 inline-flex h-12 items-center justify-center border border-accent px-6 text-sm font-medium text-accent transition-colors hover:bg-accent hover:text-ground"
            >
                まず1回記録しよう
            </Link>
        </div>

        <div v-else class="grid grid-cols-2 gap-x-6 gap-y-8">
            <div class="min-w-0">
                <StatValue label="今週の総ボリューム" :value="formatVolume(summary.weeklyVolume.thisWeek)" unit="kg" />
                <p v-if="changeLabel" class="label-micro mt-1.5 text-[10px]" :class="changeColorClass">
                    {{ changeLabel }}
                </p>
            </div>

            <div class="min-w-0">
                <StatValue label="連続トレーニング" :value="summary.streakWeeks" unit="週" />
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
