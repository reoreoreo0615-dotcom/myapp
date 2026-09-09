<script setup>
import { restNotifierStatuses } from '@/Utils/restTimerAlert';
import { computed } from 'vue';

// Issue #28: 休憩タイマー完了時にどの通知手段が使えるかを画面に出す。
//
// 「iOS Safari ではバイブが鳴らない」ことに気づけないまま使われるのを防ぐ
// のが目的なので、対応状況は常時(タイマーが動いていないときも)表示する。
// `restNotifierStatuses()` は CompositeNotifier に委譲しているだけで、
// このコンポーネントは Sound / Vibration / Visual という個々の型を知らない
// (label と supported の配列を受け取って表示するだけ)。
const statuses = computed(() => restNotifierStatuses());

const hasUnsupported = computed(() => statuses.value.some((status) => !status.supported));
</script>

<template>
    <p class="label-micro flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-ink-3">
        <span>休憩タイマー通知:</span>
        <span v-for="status in statuses" :key="status.label" class="inline-flex items-center gap-1">
            {{ status.label }}
            <span :class="status.supported ? 'text-ok' : 'text-warn'">{{
                status.supported ? '○' : '×'
            }}</span>
        </span>
        <span v-if="hasUnsupported" class="basis-full text-ink-3">
            × の手段はこの端末では使えません。音量を上げておくと気づきやすくなります。
        </span>
    </p>
</template>
