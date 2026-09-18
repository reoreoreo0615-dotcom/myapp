<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Rule from '@/Components/Rule.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    routines: {
        type: Array,
        required: true,
    },
});

const page = usePage();

function todayString() {
    // ローカルタイムゾーンでの「今日」。toISOString() は UTC になるため使わない。
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Issue #23②: ジムで入力し忘れた日を後から記録できるように、開始日を選べる
// ようにする。既定は今日。未来日はこの max 属性とサーバー側バリデーションの
// 両方で弾く。
const performedOn = ref(todayString());

// 二重送信防止(連打でワークアウトが2件作られないようにする)。
const starting = ref(false);

const start = (routineId = null) => {
    if (starting.value) {
        return;
    }
    starting.value = true;

    router.post(
        route('workouts.store'),
        { routine_id: routineId, performed_on: performedOn.value },
        {
            onFinish: () => {
                starting.value = false;
            },
        },
    );
};

function undoDelete() {
    if (!page.props.flash?.undo) {
        return;
    }
    router.patch(page.props.flash.undo, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="記録を開始" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-medium text-ink">記録を開始</h2>
        </template>

        <div
            v-if="page.props.flash?.success"
            class="mb-4 flex flex-wrap items-center justify-between gap-3 border border-ok px-4 py-3 text-sm text-ok"
        >
            <span>{{ page.props.flash.success }}</span>
            <button
                v-if="page.props.flash?.undo"
                type="button"
                class="label-micro shrink-0 text-[10px] underline"
                @click="undoDelete"
            >
                元に戻す
            </button>
        </div>
        <div
            v-if="page.props.flash?.info"
            class="mb-4 border border-line px-4 py-3 text-sm text-ink-2"
        >
            {{ page.props.flash.info }}
        </div>

        <div class="mb-6">
            <label class="label-micro block text-[10px] text-ink-3" for="workout-performed-on">
                トレーニングした日
            </label>
            <input
                id="workout-performed-on"
                v-model="performedOn"
                type="date"
                :max="todayString()"
                class="mt-1.5 h-11 w-full border border-line bg-surface px-3 text-sm text-ink sm:max-w-56"
            />
        </div>

        <p class="text-sm text-ink-2">メニューを選ぶと、そのメニューの種目が記録画面に並びます。</p>

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
            class="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 border border-line font-mono text-xs uppercase tracking-widest text-ink transition-colors hover:border-ink-2 disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto sm:min-w-64 sm:px-8"
            :disabled="starting"
            @click="start(null)"
        >
            メニューなしで開始
        </button>
    </AuthenticatedLayout>
</template>
