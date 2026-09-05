<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { playRestTimerAlert, unlockRestTimerAudio } from '@/Utils/restTimerAlert';

// Issue #20: セット間の休憩を計るインターバルタイマー。
//
// 設計メモ:
// - 経過時間は setInterval のカウントを足し込まない。開始時刻(Date.now())を
//   保持し、毎回「現在時刻との差分」で計算する。こうしないと、画面を離れて
//   戻ってきたときに(タブが非アクティブの間 setInterval が間引かれるため)
//   経過時間がズレる。
// - 状態は種目単位ではなくワークアウト単位。直近に記録したセットの休憩を
//   計るのが目的なので、次のセットを別の種目で記録したら、その種目の
//   既定休憩時間で上書きしてよい。
// - localStorage に永続化し、ページ遷移や再読み込みをまたいでも
//   (同じワークアウトである限り)経過時間を正しく復元する。
const props = defineProps({
    workoutId: {
        type: [Number, String],
        required: true,
    },
});

const emit = defineEmits(['visible-change']);

defineExpose({ start, clear });

const STEP_SECONDS = 30;
const MIN_SECONDS = 30;
const MAX_SECONDS = 600;

const storageKey = computed(() => `overload:rest-timer:${props.workoutId}`);

// null = まだ一度もタイマーが使われていない(バー自体を表示しない)。
const targetSeconds = ref(null);
const startedAt = ref(null);
const running = ref(false);
const nowMs = ref(Date.now());

let tickHandle = null;
let notified = false;

function readStorage() {
    try {
        const raw = window.localStorage.getItem(storageKey.value);
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw);
        if (typeof parsed.targetSeconds !== 'number') {
            return null;
        }
        return parsed;
    } catch {
        return null;
    }
}

function writeStorage() {
    try {
        if (targetSeconds.value === null) {
            window.localStorage.removeItem(storageKey.value);
            return;
        }
        window.localStorage.setItem(
            storageKey.value,
            JSON.stringify({
                targetSeconds: targetSeconds.value,
                startedAt: startedAt.value,
                running: running.value,
            }),
        );
    } catch {
        // 保存できなくても、このセッション内でタイマー自体は機能させる。
    }
}

function startTicking() {
    if (tickHandle) {
        return;
    }
    tickHandle = setInterval(() => {
        nowMs.value = Date.now();
    }, 500);
}

function stopTicking() {
    if (tickHandle) {
        clearInterval(tickHandle);
        tickHandle = null;
    }
}

function clamp(value) {
    return Math.min(MAX_SECONDS, Math.max(MIN_SECONDS, value));
}

const elapsedSeconds = computed(() => {
    if (!running.value || startedAt.value === null) {
        return 0;
    }
    return Math.max(0, Math.floor((nowMs.value - startedAt.value) / 1000));
});

const remainingSeconds = computed(() => (targetSeconds.value ?? 0) - elapsedSeconds.value);

const isOverTarget = computed(() => running.value && remainingSeconds.value < 0);

const displaySeconds = computed(() => {
    if (!running.value) {
        return targetSeconds.value ?? 0;
    }
    return isOverTarget.value ? elapsedSeconds.value - targetSeconds.value : remainingSeconds.value;
});

function formatClock(totalSeconds) {
    const safe = Math.max(0, Math.floor(totalSeconds));
    const minutes = Math.floor(safe / 60);
    const seconds = safe % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

const displayText = computed(() => {
    const text = formatClock(displaySeconds.value);
    return isOverTarget.value ? `+${text}` : text;
});

const visible = computed(() => targetSeconds.value !== null);

watch(visible, (value) => emit('visible-change', value), { immediate: true });

watch([targetSeconds, startedAt, running], writeStorage);

// 目標到達の通知は、表示中に「跨いだ瞬間」だけ鳴らす。
// 画面を離れて戻ってきた時点で既に目標を超えていた場合は、突然鳴って
// 驚かせないよう抑制する(復元処理側で notified を先に true にしておく)。
watch(isOverTarget, (over, wasOver) => {
    if (over && !wasOver && !notified) {
        notified = true;
        playRestTimerAlert();
    }
    if (!over) {
        notified = false;
    }
});

function restartNow() {
    startedAt.value = Date.now();
    running.value = true;
    notified = false;
    startTicking();
}

function start(seconds) {
    targetSeconds.value = clamp(seconds);
    restartNow();
}

function stopTimer() {
    running.value = false;
    startedAt.value = null;
    notified = false;
    stopTicking();
}

function adjust(delta) {
    if (targetSeconds.value === null) {
        return;
    }
    targetSeconds.value = clamp(targetSeconds.value + delta);
}

function clear() {
    targetSeconds.value = null;
    startedAt.value = null;
    running.value = false;
    stopTicking();
}

let unlockController = null;

onMounted(() => {
    const stored = readStorage();
    if (stored) {
        targetSeconds.value = stored.targetSeconds;
        startedAt.value = typeof stored.startedAt === 'number' ? stored.startedAt : null;
        running.value = Boolean(stored.running) && startedAt.value !== null;
        if (running.value) {
            const elapsed = Math.max(0, Math.floor((Date.now() - startedAt.value) / 1000));
            // 離れていた間に既に目標を超えていた場合は、戻った瞬間に鳴らさない。
            notified = stored.targetSeconds - elapsed < 0;
            startTicking();
        }
    }

    // iOS Safari の自動再生制限への対応: ページ内で最初に指が触れた瞬間
    // (記録ボタンのタップも含む)に AudioContext をアンロックしておく。
    unlockController = new AbortController();
    document.addEventListener('pointerdown', unlockRestTimerAudio, {
        once: true,
        passive: true,
        signal: unlockController.signal,
    });
});

onBeforeUnmount(() => {
    stopTicking();
    unlockController?.abort();
});
</script>

<template>
    <div v-if="visible" class="rest-timer-bar border-t border-line bg-surface">
        <div class="mx-auto flex max-w-screen-md items-center gap-2 px-3 py-2 sm:gap-3 sm:px-4">
            <button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center border border-line font-display text-sm text-ink-2 transition-colors hover:border-ink-3 hover:text-ink disabled:cursor-not-allowed disabled:opacity-30"
                aria-label="休憩時間を30秒減らす"
                :disabled="targetSeconds <= MIN_SECONDS"
                @click="adjust(-STEP_SECONDS)"
            >
                &minus;30
            </button>

            <div class="min-w-0 flex-1 text-center">
                <p class="label-micro text-ink-3">
                    {{ isOverTarget ? '目標超過' : 'REST' }}
                </p>
                <p
                    class="font-display text-3xl leading-none tabular-nums"
                    :class="isOverTarget ? 'text-warn' : running ? 'text-ink' : 'text-ink-2'"
                >
                    {{ displayText }}
                </p>
            </div>

            <button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center border border-line font-display text-sm text-ink-2 transition-colors hover:border-ink-3 hover:text-ink disabled:cursor-not-allowed disabled:opacity-30"
                aria-label="休憩時間を30秒増やす"
                :disabled="targetSeconds >= MAX_SECONDS"
                @click="adjust(STEP_SECONDS)"
            >
                +30
            </button>

            <button
                v-if="running"
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center border border-line text-ink-2 transition-colors hover:border-ink-3 hover:text-ink"
                aria-label="タイマーをリセット"
                @click="restartNow"
            >
                &#8635;
            </button>

            <button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center border border-line text-ink-2 transition-colors hover:border-ink-3 hover:text-ink"
                :aria-label="running ? 'タイマーを停止' : 'タイマーを開始'"
                @click="running ? stopTimer() : restartNow()"
            >
                <span v-if="running">&#9632;</span>
                <span v-else>&#9654;</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
/*
 * 下部固定ナビ(AuthenticatedLayout, md未満のみ表示・高さ56px + セーフエリア)
 * の直上に重ねる。md以上ではナビが無いので画面最下部に固定する。
 */
.rest-timer-bar {
    position: fixed;
    right: 0;
    bottom: calc(56px + env(safe-area-inset-bottom));
    left: 0;
    z-index: 30;
}

@media (min-width: 768px) {
    .rest-timer-bar {
        bottom: 0;
    }
}
</style>
