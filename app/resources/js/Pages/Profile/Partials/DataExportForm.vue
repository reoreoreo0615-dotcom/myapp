<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, reactive } from 'vue';

// 期間はどちらも任意(空なら下限/上限なしで全期間を対象にする)。
const period = reactive({
    from: '',
    to: '',
});

function exportHref(routeName) {
    const params = {};
    if (period.from) {
        params.from = period.from;
    }
    if (period.to) {
        params.to = period.to;
    }
    return route(routeName, params);
}

const workoutsHref = computed(() => exportHref('export.workouts'));
const bodyLogsHref = computed(() => exportHref('export.body-logs'));
</script>

<template>
    <section>
        <header>
            <h2 class="text-base font-medium text-ink">データのエクスポート</h2>

            <p class="mt-1 text-sm text-ink-2">
                ワークアウト記録・体重記録を CSV(UTF-8 BOM 付き、Excel対応)でダウンロードします。
                ローカルにバックアップとして保存しておくことをおすすめします。
            </p>
        </header>

        <div class="mt-6 flex flex-wrap gap-4">
            <div class="min-w-40 flex-1">
                <InputLabel for="export-from" value="開始日(任意)" />
                <TextInput
                    id="export-from"
                    v-model="period.from"
                    type="date"
                    class="mt-1 block w-full sm:max-w-md"
                />
            </div>
            <div class="min-w-40 flex-1">
                <InputLabel for="export-to" value="終了日(任意)" />
                <TextInput
                    id="export-to"
                    v-model="period.to"
                    type="date"
                    class="mt-1 block w-full sm:max-w-md"
                />
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-4">
            <a
                :href="workoutsHref"
                class="inline-flex rounded-full h-11 items-center justify-center gap-2 bg-surface px-6 text-sm font-medium text-ink transition-colors hover:bg-line active:bg-line"
            >
                ワークアウト記録をCSVで出力
            </a>
            <a
                :href="bodyLogsHref"
                class="inline-flex rounded-full h-11 items-center justify-center gap-2 bg-surface px-6 text-sm font-medium text-ink transition-colors hover:bg-line active:bg-line"
            >
                体重記録をCSVで出力
            </a>
        </div>
    </section>
</template>
