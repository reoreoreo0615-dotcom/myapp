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
    <nav class="mb-6 flex gap-1 border-b border-line">
        <Link
            v-for="item in items"
            :key="item.key"
            :href="item.href"
            class="label-micro flex h-11 items-center border-b-2 px-3 text-[11px]"
            :class="
                item.active
                    ? 'border-accent text-accent'
                    : 'border-transparent text-ink-2 hover:text-ink'
            "
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
