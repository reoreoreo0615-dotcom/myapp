<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DangerOutlineButton from '@/Components/DangerOutlineButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    routines: {
        type: Array,
        required: true,
    },
});

const page = usePage();

const confirmingDeleteRoutine = ref(null);
const isDeleting = ref(false);

const confirmDelete = (routine) => {
    confirmingDeleteRoutine.value = routine;
};

const closeDeleteModal = () => {
    confirmingDeleteRoutine.value = null;
};

const deleteRoutine = () => {
    if (!confirmingDeleteRoutine.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(route('routines.destroy', confirmingDeleteRoutine.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            closeDeleteModal();
        },
    });
};

function undoDelete() {
    if (!page.props.flash?.undo) {
        return;
    }
    router.patch(page.props.flash.undo, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="メニュー" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-2xl font-semibold tracking-tight text-ink">メニュー</h2>
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

        <Link
            :href="route('routines.create')"
            class="inline-flex rounded-full h-11 w-full items-center justify-center gap-2 bg-accent px-6 text-sm font-medium text-white transition-opacity hover:opacity-85 sm:w-auto sm:min-w-64"
        >
            + 新しいメニューを作成
        </Link>

        <div v-if="routines.length === 0" class="card mt-6 p-8 text-center text-sm text-ink-2">
            メニューがまだありません。「胸の日」「Pull の日」のように、
            トレーニングの組み合わせを作成しましょう。
        </div>

        <div v-else class="card mt-6 divide-y divide-line px-5">
            <!-- 広い画面では、操作ボタンを行の右端に置いて1行に収める -->
            <div
                v-for="routine in routines"
                :key="routine.id"
                class="py-4 first:pt-4 sm:flex sm:items-center sm:justify-between sm:gap-6"
            >
                <Link :href="route('routines.edit', routine.id)" class="block min-w-0 flex-1">
                    <p class="truncate font-medium text-ink">{{ routine.name }}</p>
                    <p v-if="routine.description" class="mt-1 truncate text-sm text-ink-2">
                        {{ routine.description }}
                    </p>
                    <p class="label-micro mt-2 text-[10px] text-ink-3">
                        {{ routine.exercises_count }}種目
                    </p>
                </Link>

                <div class="mt-3 flex flex-wrap gap-2 sm:mt-0 sm:shrink-0">
                    <Link
                        :href="route('routines.edit', routine.id)"
                        class="inline-flex rounded-full h-11 items-center justify-center gap-2 bg-surface px-5 text-sm font-medium text-ink transition-colors hover:bg-line"
                    >
                        編集
                    </Link>
                    <DangerOutlineButton @click="confirmDelete(routine)">
                        削除
                    </DangerOutlineButton>
                </div>
            </div>
        </div>

        <Modal :show="confirmingDeleteRoutine !== null" @close="closeDeleteModal">
            <div class="p-6">
                <h2 class="text-base font-medium text-ink">本当にこのメニューを削除しますか?</h2>

                <p class="mt-2 text-sm text-ink-2">
                    <span class="font-medium text-ink">{{ confirmingDeleteRoutine?.name }}</span>
                    を削除します。削除した直後であれば「元に戻す」から復元できますが、
                    それ以降は復元できません
                    (過去にこのメニューで記録したワークアウトの履歴自体は残ります)。
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDeleteModal"> キャンセル </SecondaryButton>
                    <DangerButton :disabled="isDeleting" @click="deleteRoutine">
                        削除する
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
