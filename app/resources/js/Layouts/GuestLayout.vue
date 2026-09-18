<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-ground">
        <header class="flex items-center border-b border-line px-4">
            <Link :href="route('home')" class="inline-flex min-h-11 items-center gap-2 px-2 py-3">
                <ApplicationLogo class="h-6 w-6 fill-current text-ink" />
                <span class="text-base font-semibold tracking-tight text-ink">Overload</span>
            </Link>
        </header>

        <main class="mx-auto w-full max-w-sm px-6 py-12 sm:max-w-md">
            <div class="card p-6 sm:p-8">
                <slot />
            </div>
        </main>
    </div>
</template>
