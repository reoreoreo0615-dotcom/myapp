<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminNav from '@/Components/AdminNav.vue';
import Checkbox from '@/Components/Checkbox.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DangerOutlineButton from '@/Components/DangerOutlineButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    exercises: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    muscleGroups: {
        type: Array,
        required: true,
    },
    movementTypes: {
        type: Array,
        required: true,
    },
    equipmentOptions: {
        type: Array,
        required: true,
    },
    progressionStrategies: {
        type: Array,
        required: true,
    },
});

const page = usePage();

const muscleGroupLabels = {
    chest: '胸',
    back: '背中',
    shoulders: '肩',
    legs: '脚',
    arms: '腕',
    core: '体幹',
};

const equipmentLabels = {
    barbell: 'バーベル',
    dumbbell: 'ダンベル',
    machine: 'マシン',
    cable: 'ケーブル',
    bodyweight: '自重',
};

const movementTypeLabels = {
    push: 'プッシュ',
    pull: 'プル',
    legs: 'レッグ',
    core: 'コア',
};

const progressionStrategyLabels = {
    double: 'ダブルプログレッション',
    linear: 'リニアプログレッション',
    five_by_five: '5×5',
};

/* --- フィルタ --- */

const filterMuscleGroup = ref(props.filters.muscle_group ?? '');
const filterEquipment = ref(props.filters.equipment ?? '');

const applyFilters = () => {
    router.get(
        route('admin.exercises.index'),
        {
            muscle_group: filterMuscleGroup.value || undefined,
            equipment: filterEquipment.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

// equipment でフィルタすると部位内の一部だけが表示され、並び替えに使う
// 「部位内の全件一致」チェックが必ず失敗するため、その間は並び替えを禁止する。
const reorderDisabled = computed(() => filterEquipment.value !== '');

/* --- 部位ごとにグループ化(表示順はサーバーの muscle_group, sort_order 順のまま) --- */

const groups = computed(() => {
    const map = new Map();
    for (const exercise of props.exercises) {
        if (!map.has(exercise.muscle_group)) {
            map.set(exercise.muscle_group, []);
        }
        map.get(exercise.muscle_group).push(exercise);
    }
    return Array.from(map.entries()).map(([muscleGroup, items]) => ({
        muscleGroup,
        items,
    }));
});

/* --- 並び替え(上下ボタン。sort_order は部位内で一括更新する) --- */

const reordering = ref(false);

const move = (group, index, direction) => {
    if (reordering.value || reorderDisabled.value) {
        return;
    }
    const target = index + direction;
    if (target < 0 || target >= group.items.length) {
        return;
    }

    const items = [...group.items];
    [items[index], items[target]] = [items[target], items[index]];

    reordering.value = true;
    router.patch(
        route('admin.exercises.reorder'),
        {
            muscle_group: group.muscleGroup,
            order: items.map((item) => item.id),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                reordering.value = false;
            },
        },
    );
};

/* --- 追加・編集フォーム --- */

const showFormModal = ref(false);
const editingExercise = ref(null);

const blankFormData = () => ({
    name: '',
    muscle_group: props.muscleGroups[0] ?? '',
    movement_type: props.movementTypes[0] ?? '',
    equipment: props.equipmentOptions[0] ?? '',
    is_bodyweight: false,
    weight_increment: 2.5,
    target_rep_min: 8,
    target_rep_max: 12,
    progression_strategy: props.progressionStrategies[0] ?? 'double',
    sort_order: 0,
});

const form = useForm(blankFormData());

const openCreateModal = () => {
    editingExercise.value = null;
    form.defaults(blankFormData());
    form.reset();
    showFormModal.value = true;
};

const openEditModal = (exercise) => {
    editingExercise.value = exercise;
    const data = {
        name: exercise.name,
        muscle_group: exercise.muscle_group,
        movement_type: exercise.movement_type,
        equipment: exercise.equipment,
        is_bodyweight: exercise.is_bodyweight,
        weight_increment: exercise.weight_increment,
        target_rep_min: exercise.target_rep_min,
        target_rep_max: exercise.target_rep_max,
        progression_strategy: exercise.progression_strategy,
        sort_order: exercise.sort_order,
    };
    form.defaults(data);
    form.reset();
    showFormModal.value = true;
};

const closeFormModal = () => {
    showFormModal.value = false;
    editingExercise.value = null;
    form.clearErrors();
};

const submitForm = () => {
    if (editingExercise.value) {
        form.patch(route('admin.exercises.update', editingExercise.value.id), {
            preserveScroll: true,
            onSuccess: () => closeFormModal(),
        });
    } else {
        form.post(route('admin.exercises.store'), {
            preserveScroll: true,
            onSuccess: () => closeFormModal(),
        });
    }
};

/* --- 削除 --- */

const confirmingDeleteExercise = ref(null);
const isDeleting = ref(false);

const canDelete = (exercise) => exercise.workout_sets_count === 0;

const confirmDelete = (exercise) => {
    if (!canDelete(exercise)) {
        return;
    }
    confirmingDeleteExercise.value = exercise;
};

const closeDeleteModal = () => {
    confirmingDeleteExercise.value = null;
};

const deleteExercise = () => {
    if (!confirmingDeleteExercise.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(route('admin.exercises.destroy', confirmingDeleteExercise.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            closeDeleteModal();
        },
    });
};
</script>

<template>
    <Head title="種目マスタ管理" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-2xl font-semibold tracking-tight text-ink">種目マスタ管理</h2>
        </template>

        <AdminNav />

        <div
            v-if="page.props.flash?.success"
            class="mb-4 border border-ok px-4 py-3 text-sm text-ok"
        >
            {{ page.props.flash.success }}
        </div>
        <div
            v-if="page.props.flash?.error"
            class="mb-4 border border-warn px-4 py-3 text-sm text-warn"
        >
            {{ page.props.flash.error }}
        </div>

        <!-- フィルタ -->
        <div class="mb-6 flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <InputLabel value="部位で絞り込み" />
                <select
                    v-model="filterMuscleGroup"
                    class="mt-1 w-full rounded-[--radius-control] border-line bg-surface px-3 py-2.5 text-ink focus:border-accent focus:ring-1 focus:ring-accent"
                    @change="applyFilters"
                >
                    <option value="">すべて</option>
                    <option v-for="mg in muscleGroups" :key="mg" :value="mg">
                        {{ muscleGroupLabels[mg] ?? mg }}
                    </option>
                </select>
            </div>
            <div class="min-w-0 flex-1">
                <InputLabel value="器具で絞り込み" />
                <select
                    v-model="filterEquipment"
                    class="mt-1 w-full rounded-[--radius-control] border-line bg-surface px-3 py-2.5 text-ink focus:border-accent focus:ring-1 focus:ring-accent"
                    @change="applyFilters"
                >
                    <option value="">すべて</option>
                    <option v-for="eq in equipmentOptions" :key="eq" :value="eq">
                        {{ equipmentLabels[eq] ?? eq }}
                    </option>
                </select>
            </div>
            <PrimaryButton class="shrink-0" @click="openCreateModal"> 新規追加 </PrimaryButton>
        </div>

        <p v-if="reorderDisabled" class="mb-4 text-xs text-ink-3">
            器具で絞り込み中は並び替えできません。部位のみの絞り込みに切り替えてください。
        </p>

        <p v-if="exercises.length === 0" class="text-sm text-ink-2">
            条件に一致する種目がありません。
        </p>

        <div v-for="group in groups" :key="group.muscleGroup" class="mb-8">
            <h3 class="label-micro mb-2 border-b border-line pb-2 text-ink-2">
                {{ muscleGroupLabels[group.muscleGroup] ?? group.muscleGroup }}
            </h3>

            <div class="card divide-y divide-line px-5">
                <div
                    v-for="(exercise, index) in group.items"
                    :key="exercise.id"
                    class="flex items-start gap-3 py-4 first:pt-0"
                >
                    <div class="flex shrink-0 flex-col gap-1">
                        <button
                            type="button"
                            class="flex h-11 w-11 items-center justify-center rounded-[--radius-control] bg-surface text-ink disabled:cursor-not-allowed disabled:opacity-30"
                            aria-label="上へ移動"
                            :disabled="index === 0 || reordering || reorderDisabled"
                            @click="move(group, index, -1)"
                        >
                            &uarr;
                        </button>
                        <button
                            type="button"
                            class="flex h-11 w-11 items-center justify-center rounded-[--radius-control] bg-surface text-ink disabled:cursor-not-allowed disabled:opacity-30"
                            aria-label="下へ移動"
                            :disabled="
                                index === group.items.length - 1 || reordering || reorderDisabled
                            "
                            @click="move(group, index, 1)"
                        >
                            &darr;
                        </button>
                    </div>

                    <div class="relative min-w-0 flex-1 sm:pr-48">
                        <!-- 広い画面では、行の右端に操作ボタンを置いて縦の間延びを防ぐ -->
                        <p class="flex flex-wrap items-center gap-2 font-medium text-ink">
                            <span class="truncate">{{ exercise.name }}</span>
                            <span
                                v-if="exercise.is_bodyweight"
                                class="shrink-0 rounded-full bg-surface px-2 py-0.5 text-[11px] text-ink-3"
                            >
                                自重
                            </span>
                        </p>

                        <dl
                            class="mt-2 grid grid-cols-2 gap-x-2 gap-y-1 text-sm text-ink-2 sm:grid-cols-5"
                        >
                            <div>
                                <dt class="label-micro text-[10px] text-ink-3">器具</dt>
                                <dd>
                                    {{ equipmentLabels[exercise.equipment] ?? exercise.equipment }}
                                </dd>
                            </div>
                            <div>
                                <dt class="label-micro text-[10px] text-ink-3">種別</dt>
                                <dd>
                                    {{
                                        movementTypeLabels[exercise.movement_type] ??
                                        exercise.movement_type
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="label-micro text-[10px] text-ink-3">刻み幅</dt>
                                <dd class="tabular-nums">{{ exercise.weight_increment }}kg</dd>
                            </div>
                            <div>
                                <dt class="label-micro text-[10px] text-ink-3">目標レップ</dt>
                                <dd class="tabular-nums">
                                    {{ exercise.target_rep_min }}–{{ exercise.target_rep_max }}
                                </dd>
                            </div>
                            <div>
                                <dt class="label-micro text-[10px] text-ink-3">漸進法</dt>
                                <dd>
                                    {{
                                        progressionStrategyLabels[exercise.progression_strategy] ??
                                        exercise.progression_strategy
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <p class="mt-2 text-sm text-ink-2">
                            記録件数:
                            <span class="tabular-nums">{{ exercise.workout_sets_count }}</span
                            >件
                        </p>

                        <div
                            class="mt-3 flex flex-wrap gap-2 sm:absolute sm:right-0 sm:top-0 sm:mt-0"
                        >
                            <SecondaryButton class="h-11 px-5" @click="openEditModal(exercise)">
                                編集
                            </SecondaryButton>
                            <DangerOutlineButton
                                :disabled="!canDelete(exercise)"
                                :title="
                                    canDelete(exercise)
                                        ? ''
                                        : `${exercise.workout_sets_count}件の記録で使用中のため削除できません`
                                "
                                @click="confirmDelete(exercise)"
                            >
                                削除
                            </DangerOutlineButton>
                        </div>
                        <p v-if="!canDelete(exercise)" class="mt-1 text-xs text-ink-3">
                            {{ exercise.workout_sets_count }}件の記録で使用中のため削除できません
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 追加・編集モーダル -->
        <Modal :show="showFormModal" @close="closeFormModal">
            <form class="p-6" @submit.prevent="submitForm">
                <h2 class="text-base font-medium text-ink">
                    {{ editingExercise ? '種目を編集' : '種目を追加' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="name" value="種目名" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel for="muscle_group" value="部位" />
                        <select
                            id="muscle_group"
                            v-model="form.muscle_group"
                            class="mt-1 w-full rounded-[--radius-control] border-line bg-surface px-3 py-2.5 text-ink focus:border-accent focus:ring-1 focus:ring-accent"
                        >
                            <option v-for="mg in muscleGroups" :key="mg" :value="mg">
                                {{ muscleGroupLabels[mg] ?? mg }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.muscle_group" />
                    </div>
                    <div>
                        <InputLabel for="movement_type" value="動作種別" />
                        <select
                            id="movement_type"
                            v-model="form.movement_type"
                            class="mt-1 w-full rounded-[--radius-control] border-line bg-surface px-3 py-2.5 text-ink focus:border-accent focus:ring-1 focus:ring-accent"
                        >
                            <option v-for="mt in movementTypes" :key="mt" :value="mt">
                                {{ movementTypeLabels[mt] ?? mt }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.movement_type" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="equipment" value="器具" />
                    <select
                        id="equipment"
                        v-model="form.equipment"
                        class="mt-1 w-full rounded-[--radius-control] border-line bg-surface px-3 py-2.5 text-ink focus:border-accent focus:ring-1 focus:ring-accent"
                    >
                        <option v-for="eq in equipmentOptions" :key="eq" :value="eq">
                            {{ equipmentLabels[eq] ?? eq }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.equipment" />
                </div>

                <label class="mt-4 flex min-h-[44px] items-center gap-2">
                    <Checkbox v-model:checked="form.is_bodyweight" />
                    <span class="text-sm text-ink">自重種目</span>
                </label>
                <InputError class="mt-1" :message="form.errors.is_bodyweight" />

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel for="weight_increment" value="重量の刻み幅(kg)" />
                        <TextInput
                            id="weight_increment"
                            v-model="form.weight_increment"
                            type="number"
                            step="0.25"
                            min="0.01"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.weight_increment" />
                    </div>
                    <div>
                        <InputLabel for="sort_order" value="表示順" />
                        <TextInput
                            id="sort_order"
                            v-model="form.sort_order"
                            type="number"
                            step="1"
                            min="0"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.sort_order" />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel for="target_rep_min" value="目標レップ数(下限)" />
                        <TextInput
                            id="target_rep_min"
                            v-model="form.target_rep_min"
                            type="number"
                            step="1"
                            min="1"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.target_rep_min" />
                    </div>
                    <div>
                        <InputLabel for="target_rep_max" value="目標レップ数(上限)" />
                        <TextInput
                            id="target_rep_max"
                            v-model="form.target_rep_max"
                            type="number"
                            step="1"
                            min="1"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.target_rep_max" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="progression_strategy" value="漸進法" />
                    <select
                        id="progression_strategy"
                        v-model="form.progression_strategy"
                        class="mt-1 w-full rounded-[--radius-control] border-line bg-surface px-3 py-2.5 text-ink focus:border-accent focus:ring-1 focus:ring-accent"
                    >
                        <option v-for="ps in progressionStrategies" :key="ps" :value="ps">
                            {{ progressionStrategyLabels[ps] ?? ps }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.progression_strategy" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeFormModal">
                        キャンセル
                    </SecondaryButton>
                    <PrimaryButton type="submit" :disabled="form.processing">
                        {{ editingExercise ? '更新する' : '追加する' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- 削除確認モーダル -->
        <Modal :show="confirmingDeleteExercise !== null" @close="closeDeleteModal">
            <div class="p-6">
                <h2 class="text-base font-medium text-ink">本当にこの種目を削除しますか?</h2>

                <p class="mt-2 text-sm text-ink-2">
                    <span class="font-medium text-ink">{{ confirmingDeleteExercise?.name }}</span>
                    を削除します。この操作は取り消せません。
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDeleteModal"> キャンセル </SecondaryButton>
                    <DangerButton :disabled="isDeleting" @click="deleteExercise">
                        削除する
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
