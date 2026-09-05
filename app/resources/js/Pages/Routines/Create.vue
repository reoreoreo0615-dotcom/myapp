<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    description: '',
});

const submit = () => {
    form.post(route('routines.store'));
};
</script>

<template>
    <Head title="メニューを作成" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-medium text-ink">メニューを作成</h2>
        </template>

        <form class="space-y-6" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="メニュー名" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="例: 胸の日"
                    maxlength="60"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="description" value="メモ(任意)" />
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    class="mt-1 block w-full rounded-none border-line bg-surface px-3 py-2.5 text-ink shadow-none placeholder:text-ink-3 focus:border-accent focus:ring-1 focus:ring-accent"
                    placeholder="このメニューについてのメモ"
                ></textarea>
                <InputError class="mt-2" :message="form.errors.description" />
            </div>

            <div class="flex gap-3">
                <PrimaryButton :disabled="form.processing">作成</PrimaryButton>
                <Link
                    :href="route('routines.index')"
                    class="inline-flex h-12 items-center justify-center gap-2 border border-line px-6 font-mono text-xs uppercase tracking-widest text-ink transition-colors hover:border-ink-2"
                >
                    キャンセル
                </Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
