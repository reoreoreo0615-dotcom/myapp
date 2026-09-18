<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const page = usePage();
const isAdmin = computed(() => page.props.auth.user.is_admin);
const userName = computed(() => page.props.auth.user.name);

const navItems = computed(() => {
    const items = [
        {
            key: 'dashboard',
            label: 'ダッシュボード',
            href: route('dashboard'),
            active: route().current('dashboard'),
            icon: 'dashboard',
        },
        {
            key: 'record',
            label: '記録',
            href: route('workouts.create'),
            active: route().current('workouts.*'),
            icon: 'record',
        },
        {
            key: 'history',
            label: '履歴',
            href: route('history.index'),
            active: route().current('history.*'),
            icon: 'history',
        },
        {
            key: 'menu',
            label: 'メニュー',
            href: route('routines.index'),
            active: route().current('routines.*'),
            icon: 'menu',
        },
    ];

    if (isAdmin.value) {
        items.push({
            key: 'admin',
            label: '管理',
            href: route('admin.users.index'),
            active: route().current('admin.*'),
            icon: 'admin',
        });
    }

    return items;
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-ground pb-24 md:pb-0">
        <!--
            ヘッダーは全幅で1段。左からアプリ名、ナビ(md以上のみ)、
            右端にユーザー名とログアウト。
            画面名(header スロット)は main の先頭に置く。ヘッダーに残すと
            デスクトップで2段になって縦を食うため。スロットは1箇所でしか
            描画しない(記録画面のタイマーがスロット内にあり、二重に描くと
            タイマーが2つ動いてしまう)。
        -->
        <header class="sticky top-0 z-30 border-b border-line bg-ground/80 backdrop-blur-xl">
            <div class="mx-auto flex w-full max-w-5xl items-center gap-4 px-4 py-3">
                <Link :href="route('dashboard')" class="flex shrink-0 items-center gap-2">
                    <ApplicationLogo class="h-6 w-6 fill-current text-accent" />
                    <span class="text-base font-semibold tracking-tight text-ink">Overload</span>
                </Link>

                <!-- ナビはデスクトップのみ。md未満は下部固定バーを使う -->
                <ul class="hidden items-center gap-1 md:flex">
                    <li v-for="item in navItems" :key="item.key">
                        <Link
                            :href="item.href"
                            class="flex h-9 items-center rounded-full px-3.5 text-sm font-medium transition-colors"
                            :class="
                                item.active ? 'bg-surface text-ink' : 'text-ink-2 hover:text-ink'
                            "
                            :aria-current="item.active ? 'page' : undefined"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>

                <div class="ml-auto flex shrink-0 items-center gap-3">
                    <!-- ユーザー名は登録情報の編集への導線を兼ねる -->
                    <Link
                        :href="route('profile.edit')"
                        class="flex h-9 max-w-32 items-center truncate px-1 text-sm transition-colors"
                        :class="
                            route().current('profile.edit')
                                ? 'text-ink'
                                : 'text-ink-3 hover:text-ink'
                        "
                    >
                        {{ userName }}
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        type="button"
                        class="flex h-9 items-center rounded-full bg-surface px-4 text-sm font-medium text-ink-2 transition-colors hover:text-ink active:bg-line"
                    >
                        ログアウト
                    </Link>
                </div>
            </div>
        </header>

        <!--
            広い画面で本文が端まで伸びると、1行が長すぎて読みにくく、
            ボタンも間延びする。コンテンツ幅に上限を設けて中央に寄せる。
        -->
        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-6">
            <div v-if="$slots.header" class="mb-6 flex items-center justify-between gap-4">
                <slot name="header" />
            </div>
            <slot />
        </main>

        <!-- 下部固定ナビ(スマホの親指が届く位置)。md以上ではヘッダーのナビに切り替える -->
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-ground/90 backdrop-blur-xl md:hidden"
            style="padding-bottom: env(safe-area-inset-bottom)"
        >
            <ul class="flex">
                <li v-for="item in navItems" :key="item.key" class="flex-1">
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        class="flex min-h-[56px] flex-col items-center justify-center gap-1 px-1 py-2"
                        :class="item.active ? 'text-accent' : 'text-ink-3'"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            class="h-5 w-5"
                            aria-hidden="true"
                        >
                            <template v-if="item.icon === 'dashboard'">
                                <rect x="3.5" y="3.5" width="7" height="7" />
                                <rect x="13.5" y="3.5" width="7" height="7" />
                                <rect x="3.5" y="13.5" width="7" height="7" />
                                <rect x="13.5" y="13.5" width="7" height="7" />
                            </template>
                            <template v-else-if="item.icon === 'record'">
                                <circle cx="12" cy="12" r="8" />
                                <circle cx="12" cy="12" r="2.5" />
                            </template>
                            <template v-else-if="item.icon === 'history'">
                                <circle cx="12" cy="12" r="8" />
                                <path d="M12 7.5V12l3 2" />
                            </template>
                            <template v-else-if="item.icon === 'menu'">
                                <path d="M4 6h16M4 12h16M4 18h16" />
                            </template>
                            <template v-else-if="item.icon === 'admin'">
                                <path
                                    d="M12 3.5 5 6v5.5c0 4.2 3 7.4 7 9 4-1.6 7-4.8 7-9V6l-7-2.5Z"
                                />
                            </template>
                        </svg>
                        <span class="text-[10px] font-medium">{{ item.label }}</span>
                    </Link>

                    <span
                        v-else
                        class="flex min-h-[56px] flex-col items-center justify-center gap-1 px-1 py-2 text-ink-3 opacity-40"
                        aria-disabled="true"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            class="h-5 w-5"
                            aria-hidden="true"
                        >
                            <template v-if="item.icon === 'record'">
                                <circle cx="12" cy="12" r="8" />
                                <circle cx="12" cy="12" r="2.5" />
                            </template>
                            <template v-else-if="item.icon === 'history'">
                                <circle cx="12" cy="12" r="8" />
                                <path d="M12 7.5V12l3 2" />
                            </template>
                        </svg>
                        <span class="text-[10px] font-medium">{{ item.label }}</span>
                    </span>
                </li>
            </ul>
        </nav>
    </div>
</template>
