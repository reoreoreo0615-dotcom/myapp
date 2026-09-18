<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout>
        <Head title="メールアドレスの確認" />

        <div class="mb-4 text-sm text-ink-2">
            ご登録ありがとうございます!利用を開始する前に、先ほど送信したメール内のリンクを
            クリックしてメールアドレスを確認してください。メールが届いていない場合は、
            再度お送りします。
        </div>

        <div v-if="verificationLinkSent" class="mb-4 text-sm font-medium text-ok">
            ご登録いただいたメールアドレスに、新しい確認用リンクを送信しました。
        </div>

        <form @submit.prevent="submit">
            <div class="mt-6 flex flex-col gap-4">
                <PrimaryButton class="w-full" :disabled="form.processing">
                    確認メールを再送する
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-center text-sm text-ink-2 underline underline-offset-2 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                    >ログアウト</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
