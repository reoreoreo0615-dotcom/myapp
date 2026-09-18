<script setup>
/*
 * 2値の比率を1本の横棒で示す、シンプルな比率バー(Issue #24②)。
 *
 * 部位バランス(push/pull)は「2つの値の比率」という単純な構造のデータで、
 * 推移も見せる必要が無いため、ExerciseHistoryChart.vue のような
 * 折れ線グラフ(SVG)ではなく、この専用の単純な部品として新規に作った。
 * 角丸の細い帯で表現する。色は意味を持つときだけ変える。
 */
const props = defineProps({
    // 左側(例: push)の値
    leftValue: {
        type: Number,
        required: true,
    },
    // 右側(例: pull)の値
    rightValue: {
        type: Number,
        required: true,
    },
    leftLabel: {
        type: String,
        required: true,
    },
    rightLabel: {
        type: String,
        required: true,
    },
    // 偏りの注意色(caution)にするかどうかは呼び出し側が決める。
    warn: {
        type: Boolean,
        default: false,
    },
});

const total = () => Math.max(props.leftValue + props.rightValue, 1);
</script>

<template>
    <div>
        <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-line">
            <div
                class="h-full rounded-full"
                :class="warn ? 'bg-caution' : 'bg-accent'"
                :style="{ width: `${(leftValue / total()) * 100}%` }"
            />
            <div class="h-full flex-1" />
        </div>
        <div class="mt-1.5 flex items-center justify-between text-xs text-ink-2">
            <span>{{ leftLabel }} {{ leftValue }}</span>
            <span>{{ rightLabel }} {{ rightValue }}</span>
        </div>
    </div>
</template>
