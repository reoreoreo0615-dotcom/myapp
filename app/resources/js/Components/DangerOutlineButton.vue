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
    塗りつぶしの DangerButton は主要導線より目立つため、一覧では
    淡い面と赤い文字で示し、取り返しのつかない実行は確認モーダルに任せる。
-->
<template>
    <button
        :type="type"
        class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-surface px-6 text-sm font-medium text-warn transition-colors hover:bg-line active:bg-line focus:outline-none focus-visible:ring-2 focus-visible:ring-warn focus-visible:ring-offset-2 focus-visible:ring-offset-ground disabled:cursor-not-allowed disabled:opacity-40"
    >
        <slot />
    </button>
</template>
