<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// bootstrap/app.php の Exceptions::respond() から、デバッグ無効時に
// 404 / 403 / 500 (および 503) をこのページ1枚に集約してレンダリングする。
// ステータスごとの文言をここで出し分ける(状態はサーバー側から渡された数値のみ)。
const props = defineProps({
    status: {
        type: Number,
        required: true,
    },
});

const content = computed(() => {
    switch (props.status) {
        case 403:
            return {
                title: 'アクセス権がありません',
                description: 'このページを表示する権限がありません。',
            };
        case 404:
            return {
                title: 'ページが見つかりません',
                description:
                    '指定されたページは存在しないか、移動または削除された可能性があります。',
            };
        case 419:
            return {
                title: 'セッションの有効期限が切れました',
                description: 'ページを再読み込みしてから、もう一度お試しください。',
            };
        case 503:
            return {
                title: 'メンテナンス中です',
                description: 'しばらく経ってから再度アクセスしてください。',
            };
        default:
            return {
                title: 'エラーが発生しました',
                description: '予期しない問題が発生しました。しばらく経ってから再度お試しください。',
            };
    }
});
</script>

<template>
    <Head :title="`${status}`" />

    <div class="flex min-h-screen flex-col bg-ground">
        <header class="flex items-center border-b border-line px-4">
            <span class="inline-flex min-h-11 items-center gap-2 px-2 py-3">
                <ApplicationLogo class="h-6 w-6 fill-current text-ink" />
                <span class="label-micro text-xs text-ink-2">Overload</span>
            </span>
        </header>

        <main
            class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center px-6 py-10 sm:max-w-md"
        >
            <p class="label-micro text-[11px] text-ink-3">ERROR</p>
            <p class="mt-2 font-display text-6xl tabular-nums text-ink">{{ status }}</p>

            <p class="mt-6 text-lg text-ink">{{ content.title }}</p>
            <p class="mt-2 text-sm text-ink-2">{{ content.description }}</p>

            <Link
                :href="route('dashboard')"
                class="mt-10 flex h-12 w-full items-center justify-center gap-2 bg-accent px-6 font-mono text-xs uppercase tracking-widest text-ground transition-opacity hover:opacity-90"
            >
                ダッシュボードへ戻る
            </Link>
        </main>
    </div>
</template>
