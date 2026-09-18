<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="パスワードの確認" />

        <div class="mb-4 text-sm text-ink-2">
            ここはアプリのセキュリティ保護されたエリアです。続行する前にパスワードを確認してください。
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="password" value="パスワード" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-6">
                <PrimaryButton class="w-full" :disabled="form.processing"> 確認 </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
