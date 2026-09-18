<script setup>
import { Link } from '@inertiajs/vue3';

const items = [
    {
        key: 'users',
        label: 'ユーザー',
        href: route('admin.users.index'),
        active: route().current('admin.users.*'),
    },
    {
        key: 'exercises',
        label: '種目マスタ',
        href: route('admin.exercises.index'),
        active: route().current('admin.exercises.*'),
    },
];
</script>

<template>
    <nav class="mb-6 inline-flex gap-1 rounded-full bg-surface p-1">
        <Link
            v-for="item in items"
            :key="item.key"
            :href="item.href"
            class="flex h-9 items-center rounded-full px-5 text-sm font-medium transition-colors"
            :class="item.active ? 'bg-card text-ink shadow-sm' : 'text-ink-2 hover:text-ink'"
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
