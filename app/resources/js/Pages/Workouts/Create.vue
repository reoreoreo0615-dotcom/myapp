<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Rule from '@/Components/Rule.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    routines: {
        type: Array,
        required: true,
    },
});

// 二重送信防止(連打でワークアウトが2件作られないようにする)。
const starting = ref(false);

const start = (routineId = null) => {
    if (starting.value) {
        return;
    }
    starting.value = true;

    router.post(
        route('workouts.store'),
        { routine_id: routineId },
        {
            onFinish: () => {
                starting.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="記録を開始" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-medium text-ink">記録を開始</h2>
        </template>

        <p class="text-sm text-ink-2">
            メニューを選ぶと、そのメニューの種目が記録画面に並びます。
        </p>

        <Rule class="mt-6" />

        <div v-if="routines.length === 0" class="py-8 text-center text-sm text-ink-2">
            まだメニューがありません。メニューなしでもワークアウトを開始できます。
        </div>

        <div v-else class="divide-y divide-line">
            <button
                v-for="routine in routines"
                :key="routine.id"
                type="button"
                class="flex w-full items-center justify-between gap-3 py-4 text-left disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="starting"
                @click="start(routine.id)"
            >
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-medium text-ink">{{ routine.name }}</span>
                    <span class="label-micro mt-1 block text-[10px] text-ink-3">
                        {{ routine.exercises_count }}種目
                    </span>
                </span>
                <span class="label-micro shrink-0 text-accent">開始 &rarr;</span>
            </button>
        </div>

        <Rule class="mt-2" />

        <button
            type="button"
            class="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 border border-line font-mono text-xs uppercase tracking-widest text-ink transition-colors hover:border-ink-2 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="starting"
            @click="start(null)"
        >
            メニューなしで開始
        </button>
    </AuthenticatedLayout>
</template>
