<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="パスワードをお忘れの方" />

        <div class="mb-4 text-sm text-ink-2">
            パスワードをお忘れですか?ご登録のメールアドレスを入力してください。
            パスワード再設定用のリンクをメールでお送りします。
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-ok">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="メールアドレス" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-6">
                <PrimaryButton class="w-full" :disabled="form.processing">
                    パスワード再設定メールを送信
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
