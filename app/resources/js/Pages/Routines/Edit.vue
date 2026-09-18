<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import NumberStepper from '@/Components/NumberStepper.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Rule from '@/Components/Rule.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    routine: {
        type: Object,
        required: true,
    },
    exercises: {
        type: Array,
        required: true,
    },
    availableExercises: {
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

/* --- メニュー名・メモ --- */

const detailsForm = useForm({
    name: props.routine.name,
    description: props.routine.description ?? '',
});

const saveDetails = () => {
    detailsForm.patch(route('routines.update', props.routine.id), {
        preserveScroll: true,
    });
};

/* --- 種目の並び替え(上下ボタン。sort_order は一括更新する) --- */

const reordering = ref(false);

const move = (index, direction) => {
    if (reordering.value) {
        return;
    }
    const target = index + direction;
    if (target < 0 || target >= props.exercises.length) {
        return;
    }

    const items = [...props.exercises];
    [items[index], items[target]] = [items[target], items[index]];

    reordering.value = true;
    router.patch(
        route('routines.exercises.reorder', props.routine.id),
        { order: items.map((item) => item.id) },
        {
            preserveScroll: true,
            onFinish: () => {
                reordering.value = false;
            },
        },
    );
};

/* --- 目標セット数 --- */

const updateTargetSets = (routineExerciseId, value) => {
    router.patch(
        route('routines.exercises.update', [props.routine.id, routineExerciseId]),
        { target_sets: value },
        { preserveScroll: true, preserveState: true },
    );
};

/* --- 種目の削除 --- */

const removingId = ref(null);

const removeExercise = (routineExerciseId) => {
    removingId.value = routineExerciseId;
    router.delete(route('routines.exercises.destroy', [props.routine.id, routineExerciseId]), {
        preserveScroll: true,
        onFinish: () => {
            removingId.value = null;
        },
    });
};

/* --- 種目の追加 --- */

const addedExerciseIds = computed(
    () => new Set(props.exercises.map((exercise) => exercise.exercise_id)),
);

const selectableExercises = computed(() =>
    props.availableExercises.filter((exercise) => !addedExerciseIds.value.has(exercise.id)),
);

const groupedSelectableExercises = computed(() => {
    const groups = {};
    for (const exercise of selectableExercises.value) {
        (groups[exercise.muscle_group] ??= []).push(exercise);
    }
    return groups;
});

const addForm = useForm({
    exercise_id: '',
});

const addExercise = () => {
    if (!addForm.exercise_id) {
        return;
    }
    addForm.post(route('routines.exercises.store', props.routine.id), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset('exercise_id');
        },
    });
};

/* --- 自分だけの種目を新規作成 --- */

const showCustomExerciseForm = ref(false);

const customExerciseForm = useForm({
    name: '',
    muscle_group: 'chest',
    equipment: 'barbell',
});

const createCustomExercise = () => {
    customExerciseForm.post(route('exercises.store'), {
        preserveScroll: true,
        onSuccess: () => {
            customExerciseForm.reset('name');
            showCustomExerciseForm.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`メニュー編集: ${routine.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="truncate text-lg font-medium text-ink">{{ routine.name }}</h2>
        </template>

        <div
            v-if="page.props.flash?.success"
            class="mb-4 border border-ok px-4 py-3 text-sm text-ok"
        >
            {{ page.props.flash.success }}
        </div>

        <!-- メニュー名・メモ -->
        <section>
            <h3 class="label-micro text-[11px] text-ink-3">メニュー情報</h3>

            <form class="mt-3 space-y-4" @submit.prevent="saveDetails">
                <div>
                    <InputLabel for="name" value="メニュー名" />
                    <TextInput
                        id="name"
                        v-model="detailsForm.name"
                        type="text"
                        class="mt-1 block w-full sm:max-w-md"
                        maxlength="60"
                        required
                    />
                    <InputError class="mt-2" :message="detailsForm.errors.name" />
                </div>

                <div>
                    <InputLabel for="description" value="メモ(任意)" />
                    <textarea
                        id="description"
                        v-model="detailsForm.description"
                        rows="2"
                        class="mt-1 block w-full rounded-[--radius-control] border-line bg-surface px-3 py-2.5 text-ink shadow-none placeholder:text-ink-3 focus:border-accent focus:ring-1 focus:ring-accent sm:max-w-md"
                    ></textarea>
                    <InputError class="mt-2" :message="detailsForm.errors.description" />
                </div>

                <SecondaryButton type="submit" :disabled="detailsForm.processing">
                    保存
                </SecondaryButton>
            </form>
        </section>

        <Rule class="my-8" />

        <!-- 種目一覧 -->
        <section>
            <h3 class="label-micro text-[11px] text-ink-3">種目({{ exercises.length }})</h3>

            <div v-if="exercises.length === 0" class="py-6 text-sm text-ink-2">
                まだ種目がありません。下から追加してください。
            </div>

            <ol v-else class="mt-3 divide-y divide-line">
                <!-- 広い画面では、並べ替え・セット数・削除を1行にまとめる -->
                <li
                    v-for="(exercise, index) in exercises"
                    :key="exercise.id"
                    class="py-3 sm:flex sm:items-center sm:gap-4"
                >
                    <div class="flex items-center gap-3 sm:min-w-0 sm:flex-1">
                        <span
                            class="w-5 shrink-0 text-center font-mono text-xs tabular-nums text-ink-3"
                        >
                            {{ index + 1 }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-ink">{{ exercise.name }}</p>
                            <p class="label-micro text-[10px] text-ink-3">
                                {{
                                    muscleGroupLabels[exercise.muscle_group] ??
                                    exercise.muscle_group
                                }}
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-1 sm:order-last">
                            <button
                                type="button"
                                class="flex h-11 w-11 items-center justify-center rounded-[--radius-control] bg-surface text-ink disabled:cursor-not-allowed disabled:opacity-30"
                                aria-label="上へ移動"
                                :disabled="index === 0 || reordering"
                                @click="move(index, -1)"
                            >
                                &uarr;
                            </button>
                            <button
                                type="button"
                                class="flex h-11 w-11 items-center justify-center rounded-[--radius-control] bg-surface text-ink disabled:cursor-not-allowed disabled:opacity-30"
                                aria-label="下へ移動"
                                :disabled="index === exercises.length - 1 || reordering"
                                @click="move(index, 1)"
                            >
                                &darr;
                            </button>
                        </div>
                    </div>

                    <div
                        class="mt-3 flex items-center justify-between gap-3 sm:mt-0 sm:shrink-0 sm:justify-end sm:gap-4"
                    >
                        <div class="flex items-center gap-2">
                            <span class="label-micro text-[10px] text-ink-3">目標セット数</span>
                            <NumberStepper
                                compact
                                :model-value="exercise.target_sets"
                                :min="1"
                                :max="20"
                                @update:model-value="updateTargetSets(exercise.id, $event)"
                            />
                        </div>

                        <button
                            type="button"
                            class="h-11 shrink-0 rounded-full bg-surface px-5 text-sm font-medium text-warn disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="removingId === exercise.id"
                            @click="removeExercise(exercise.id)"
                        >
                            削除
                        </button>
                    </div>
                </li>
            </ol>
        </section>

        <Rule class="my-8" />

        <!-- 種目の追加 -->
        <section>
            <h3 class="label-micro text-[11px] text-ink-3">種目を追加</h3>

            <div class="mt-3 space-y-3">
                <select
                    v-model="addForm.exercise_id"
                    class="w-full rounded-[--radius-control] border-line bg-surface px-3 py-2.5 text-ink focus:border-accent focus:ring-1 focus:ring-accent sm:max-w-md"
                >
                    <option value="" disabled>種目を選択</option>
                    <optgroup
                        v-for="(list, group) in groupedSelectableExercises"
                        :key="group"
                        :label="muscleGroupLabels[group] ?? group"
                    >
                        <option v-for="exercise in list" :key="exercise.id" :value="exercise.id">
                            {{ exercise.name
                            }}<template v-if="exercise.is_custom">(自分の種目)</template>
                        </option>
                    </optgroup>
                </select>
                <InputError :message="addForm.errors.exercise_id" />

                <PrimaryButton
                    class="w-full sm:w-auto sm:min-w-64"
                    :disabled="!addForm.exercise_id || addForm.processing"
                    @click="addExercise"
                >
                    このメニューに追加
                </PrimaryButton>
            </div>

            <button
                type="button"
                class="label-micro mt-4 text-xs text-ink-2 underline underline-offset-2"
                @click="showCustomExerciseForm = !showCustomExerciseForm"
            >
                {{ showCustomExerciseForm ? '閉じる' : '+ 種目一覧にない種目を追加する' }}
            </button>

            <form
                v-if="showCustomExerciseForm"
                class="card mt-3 space-y-4 p-5"
                @submit.prevent="createCustomExercise"
            >
                <div>
                    <InputLabel for="exercise-name" value="種目名" />
                    <TextInput
                        id="exercise-name"
                        v-model="customExerciseForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        maxlength="80"
                        required
                    />
                    <InputError class="mt-2" :message="customExerciseForm.errors.name" />
                </div>

                <div>
                    <InputLabel for="exercise-muscle-group" value="部位" />
                    <select
                        id="exercise-muscle-group"
                        v-model="customExerciseForm.muscle_group"
                        class="mt-1 block w-full rounded-[--radius-control] border-line bg-surface px-3 py-2.5 text-ink focus:border-accent focus:ring-1 focus:ring-accent"
                    >
                        <option
                            v-for="(label, value) in muscleGroupLabels"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="customExerciseForm.errors.muscle_group" />
                </div>

                <div>
                    <InputLabel for="exercise-equipment" value="器具" />
                    <select
                        id="exercise-equipment"
                        v-model="customExerciseForm.equipment"
                        class="mt-1 block w-full rounded-[--radius-control] border-line bg-surface px-3 py-2.5 text-ink focus:border-accent focus:ring-1 focus:ring-accent"
                    >
                        <option
                            v-for="(label, value) in equipmentLabels"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="customExerciseForm.errors.equipment" />
                </div>

                <SecondaryButton type="submit" :disabled="customExerciseForm.processing">
                    種目を作成
                </SecondaryButton>
            </form>
        </section>
    </AuthenticatedLayout>
</template>
