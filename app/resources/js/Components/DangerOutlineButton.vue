<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<!--
    一覧に並ぶ「削除」用のボタン。
    塗りつぶしの DangerButton は主要導線より目立ってしまうため、
    一覧では枠線と文字色で危険を示し、実行前の確認は Modal に任せる。
-->
<template>
    <button
        :type="type"
        class="inline-flex h-11 items-center justify-center gap-2 border border-warn/60 px-5 font-mono text-xs uppercase tracking-widest text-warn transition-colors hover:border-warn hover:bg-warn/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-warn focus-visible:ring-offset-2 focus-visible:ring-offset-ground disabled:cursor-not-allowed disabled:opacity-40"
    >
        <slot />
    </button>
</template>
