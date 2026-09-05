<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const isAdmin = computed(() => page.props.auth.user.is_admin);
const userName = computed(() => page.props.auth.user.name);

// 記録(ワークアウト記録)と履歴の画面は別Issue(#14 / #10・#11)でこれから作る。
// ルートがまだ存在しないため、ここでは非活性のプレースホルダーとして置く。
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
            href: null,
            active: false,
            icon: 'record',
        },
        {
            key: 'history',
            label: '履歴',
            href: null,
            active: false,
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
    <div class="flex min-h-screen flex-col bg-ground pb-24">
        <!-- 上部バーは最小限。画面名 + 画面固有の情報 + ログアウト -->
        <header
            class="flex items-center justify-between gap-4 border-b border-line px-4 py-4"
        >
            <div class="min-w-0 flex-1">
                <slot name="header" />
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <span class="label-micro text-ink-3 truncate max-w-24">{{ userName }}</span>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    type="button"
                    class="label-micro flex h-11 items-center border border-line px-3 text-ink-2 transition-colors hover:border-ink-3 hover:text-ink"
                >
                    ログアウト
                </Link>
            </div>
        </header>

        <main class="flex-1 px-4 py-6">
            <slot />
        </main>

        <!-- 下部固定ナビ(スマホの親指が届く位置) -->
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface"
            style="padding-bottom: env(safe-area-inset-bottom)"
        >
            <ul class="flex">
                <li v-for="item in navItems" :key="item.key" class="flex-1">
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        class="flex min-h-[56px] flex-col items-center justify-center gap-1 border-t-2 px-1 py-2"
                        :class="
                            item.active
                                ? 'border-accent text-accent'
                                : 'border-transparent text-ink-2'
                        "
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
                        <span class="label-micro text-[10px]">{{
                            item.label
                        }}</span>
                    </Link>

                    <span
                        v-else
                        class="flex min-h-[56px] flex-col items-center justify-center gap-1 border-t-2 border-transparent px-1 py-2 text-ink-3 opacity-40"
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
                        <span class="label-micro text-[10px]">{{
                            item.label
                        }}</span>
                    </span>
                </li>
            </ul>
        </nav>
    </div>
</template>
