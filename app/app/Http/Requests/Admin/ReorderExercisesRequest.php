<?php

namespace App\Http\Requests\Admin;

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ReorderExercisesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'muscle_group' => ['required', new Enum(MuscleGroup::class)],
            'order' => ['required', 'array', 'min:1'],
            'order.*' => [
                'integer',
                Rule::exists('exercises', 'id')->whereNull('user_id'),
            ],
        ];
    }

    /**
     * `order` はその部位に属する既定種目の「並び替え後の並び順」そのものを表す
     * 配列なので、一部だけ渡された一括更新は他の並びを暗黙に壊す。個々の id の
     * 存在チェックだけでなく、指定された muscle_group が持つ既定種目の id 集合と
     * 完全に一致することを確認する(routines.exercises.reorder と同じ方針)。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (
                $validator->errors()->has('muscle_group')
                || $validator->errors()->has('order')
                || $validator->errors()->has('order.*')
            ) {
                return;
            }

            $expected = Exercise::query()
                ->whereNull('user_id')
                ->where('muscle_group', $this->input('muscle_group'))
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

            $given = collect($this->input('order', []))
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($expected !== $given) {
                $validator->errors()->add('order', '並び替え対象の種目が一覧の内容と一致しません。');
            }
        });
    }
}
