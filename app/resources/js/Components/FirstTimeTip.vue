<script setup>
import { ref } from 'vue';

// Issue #19: 初回のみ表示するミニガイド。1回閉じたら二度と出さない。
//
// 保存先は localStorage を選んだ(サーバー側の変更を避けるため):
// - 「閉じたか」はこの端末・このブラウザだけの状態でよく、他デバイスと
//   同期する必要がない(むしろジムでは同じ端末を使い続けるのが前提)。
// - DB 列やユーザー設定に持たせるとマイグレーション・API 往復が要る割に
//   得られる価値が小さい(端末をまたいで見せたい/消したい要件が無い)。
// - プライベートブラウズ等で localStorage が使えない場合は、単に毎回
//   表示されるだけに留め(catch で握りつぶす)、機能自体は壊さない。
const props = defineProps({
    // 端末内で一意なキー。ガイドの内容ごとに変える。
    storageKey: {
        type: String,
        required: true,
    },
});

function readDismissed() {
    try {
        return window.localStorage.getItem(props.storageKey) === '1';
    } catch {
        return false;
    }
}

const dismissed = ref(readDismissed());

function dismiss() {
    dismissed.value = true;
    try {
        window.localStorage.setItem(props.storageKey, '1');
    } catch {
        // 保存できなくても閉じる操作自体は成立させる。
    }
}
</script>

<template>
    <div
        v-if="!dismissed"
        class="flex items-start justify-between gap-3 border border-line bg-surface px-4 py-3 text-sm text-ink-2"
    >
        <div class="min-w-0 flex-1"><slot /></div>
        <button
            type="button"
            class="label-micro flex h-11 min-w-11 shrink-0 items-center justify-center px-2 text-[10px] text-ink-3 hover:text-ink"
            aria-label="このガイドを閉じる"
            @click="dismiss"
        >
            閉じる
        </button>
    </div>
</template>
