<script setup>
import { computed, ref } from 'vue';
import { formatNumber } from '@/Utils/format';

/*
 * 停滞(プラトー)の通知(Issue #24①)。
 *
 * 「停滞しています」と断定せず、具体的な次の一手(デロード / レップレンジ変更)を
 * 添えて提示する。押し付けないため、閉じるボタンで即座に無視できる
 * (画面をリロードすれば再表示される。サーバー側には何も保存しない)。
 * 記録操作(NumberStepper / 記録ボタン)の手数は増やさない。
 */
const props = defineProps({
    // { status: 'stagnant'|'declining', sessions_without_update, baseline: {weight,reps}, suggestions: [...] }
    plateau: {
        type: Object,
        required: true,
    },
});

const dismissed = ref(false);

const statusLabel = computed(() =>
    props.plateau.status === 'declining' ? '数値が下降ぎみです' : '停滞しているかもしれません',
);

function suggestionText(suggestion) {
    switch (suggestion.type) {
        case 'deload_weight':
            return `重量を${formatNumber(suggestion.deload_weight)}kgに落として${suggestion.sessions}セッションほど行い、その後${formatNumber(suggestion.current_weight)}kgに戻してみる(デロード)`;
        case 'deload_reps':
            return `レップ数を${suggestion.deload_reps}回に落として${suggestion.sessions}セッションほど行い、その後${suggestion.current_reps}回に戻してみる(デロード)`;
        case 'rep_range':
            return `目標レップ範囲を${suggestion.current_rep_min}〜${suggestion.current_rep_max}回から${suggestion.suggested_rep_min}〜${suggestion.suggested_rep_max}回に下げてみる`;
        default:
            return '';
    }
}
</script>

<template>
    <div v-if="!dismissed" class="mt-3 border-l-2 border-warn py-1 pl-3">
        <div class="flex items-start justify-between gap-3">
            <p class="label-micro text-[10px] text-caution">
                {{ statusLabel }}・直近{{ plateau.sessions_without_update }}回、更新なし
            </p>
            <button
                type="button"
                class="label-micro shrink-0 text-[10px] text-ink-3 underline"
                @click="dismissed = true"
            >
                閉じる
            </button>
        </div>
        <ul class="mt-1.5 space-y-1 text-xs text-ink-2">
            <li v-for="(suggestion, index) in plateau.suggestions" :key="index">
                ・{{ suggestionText(suggestion) }}
            </li>
        </ul>
    </div>
</template>
