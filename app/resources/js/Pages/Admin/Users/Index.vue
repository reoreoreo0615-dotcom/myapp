<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    users: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);
const adminCount = computed(
    () => props.users.filter((user) => user.is_admin).length,
);

const confirmingDeleteUser = ref(null);
const isDeleting = ref(false);

const confirmDelete = (user) => {
    confirmingDeleteUser.value = user;
};

const closeDeleteModal = () => {
    confirmingDeleteUser.value = null;
};

const deleteUser = () => {
    if (!confirmingDeleteUser.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(route('admin.users.destroy', confirmingDeleteUser.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            closeDeleteModal();
        },
    });
};

const toggleAdmin = (user) => {
    router.patch(
        route('admin.users.update-admin', user.id),
        { is_admin: !user.is_admin },
        { preserveScroll: true },
    );
};

const canRevokeAdmin = (user) => !(user.is_admin && adminCount.value <= 1);

const formatDate = (value) => {
    return new Date(value).toLocaleDateString('ja-JP');
};
</script>

<template>
    <Head title="ユーザー管理" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                ユーザー管理
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.error"
                    class="rounded-md bg-red-50 p-4 text-sm text-red-700"
                >
                    {{ page.props.flash.error }}
                </div>

                <div
                    v-for="user in users"
                    :key="user.id"
                    class="overflow-hidden rounded-lg bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 truncate font-semibold text-gray-900">
                                <span class="truncate">{{ user.name }}</span>
                                <span
                                    v-if="user.is_admin"
                                    class="inline-flex shrink-0 items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700"
                                >
                                    管理者
                                </span>
                                <span
                                    v-if="user.id === currentUserId"
                                    class="inline-flex shrink-0 items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                                >
                                    あなた
                                </span>
                            </p>
                            <p class="truncate text-sm text-gray-500">
                                {{ user.email }}
                            </p>
                        </div>
                        <span class="shrink-0 text-xs text-gray-400">
                            ID: {{ user.id }}
                        </span>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-2 gap-y-1 text-sm text-gray-600">
                        <div>
                            <dt class="text-xs text-gray-400">登録日</dt>
                            <dd>{{ formatDate(user.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">ワークアウト記録件数</dt>
                            <dd>{{ user.workouts_count }}件</dd>
                        </div>
                    </dl>

                    <div v-if="user.id !== currentUserId" class="mt-4 flex flex-wrap gap-2">
                        <SecondaryButton
                            :disabled="user.is_admin && !canRevokeAdmin(user)"
                            :class="{ 'opacity-25': user.is_admin && !canRevokeAdmin(user) }"
                            :title="
                                user.is_admin && !canRevokeAdmin(user)
                                    ? '最後の管理者からは権限を剥奪できません'
                                    : ''
                            "
                            @click="toggleAdmin(user)"
                        >
                            {{ user.is_admin ? '管理者権限を剥奪' : '管理者にする' }}
                        </SecondaryButton>
                        <DangerButton @click="confirmDelete(user)">
                            削除
                        </DangerButton>
                    </div>
                    <p v-else class="mt-4 text-xs text-gray-400">
                        自分自身の削除・権限変更はできません
                    </p>
                </div>
            </div>
        </div>

        <Modal :show="confirmingDeleteUser !== null" @close="closeDeleteModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    本当にこのユーザーを削除しますか?
                </h2>

                <p class="mt-2 text-sm text-gray-600">
                    <span class="font-medium">{{ confirmingDeleteUser?.name }}</span>
                    ({{ confirmingDeleteUser?.email }}) を削除すると、
                    このユーザーのワークアウト記録・ルーティン・独自種目が
                    すべて完全に削除されます。この操作は取り消せません。
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDeleteModal">
                        キャンセル
                    </SecondaryButton>
                    <DangerButton
                        :class="{ 'opacity-25': isDeleting }"
                        :disabled="isDeleting"
                        @click="deleteUser"
                    >
                        削除する
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
