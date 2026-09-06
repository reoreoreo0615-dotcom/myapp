<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataExportForm from './Partials/DataExportForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="プロフィール" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-medium text-ink">プロフィール</h2>
        </template>

        <div class="divide-y divide-line border-t border-line">
            <section class="py-6 first:pt-0">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </section>

            <section class="py-6">
                <UpdatePasswordForm />
            </section>

            <section class="py-6">
                <DataExportForm />
            </section>

            <section class="py-6">
                <DeleteUserForm />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
