<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="ダッシュボード" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-medium text-ink">ダッシュボード</h2>
        </template>

        <div class="border-t border-line pt-6 text-sm text-ink-2">
            ログインしました。(このダッシュボードは仮実装です。詳細な画面は Issue #4 で実装予定です)
        </div>
    </AuthenticatedLayout>
</template>
