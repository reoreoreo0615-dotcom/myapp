<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderRoutineExercisesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('routine'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routine = $this->route('routine');

        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => [
                'integer',
                Rule::exists('routine_exercises', 'id')->where(
                    fn ($query) => $query->where('routine_id', $routine->id)
                ),
            ],
        ];
    }

    /**
     * `order` は「並び替え後の並び順」そのものを表す配列なので、一部だけ渡された
     * 一括更新はメニューの種目構成を暗黙に壊す。個々の id の存在チェックだけでなく、
     * ルーティンが持つ routine_exercises の id 集合と完全に一致することを確認する。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('order') || $validator->errors()->has('order.*')) {
                return;
            }

            $routine = $this->route('routine');

            $expected = $routine->routineExercises()->pluck('id')->sort()->values()->all();
            $given = collect($this->input('order', []))
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($expected !== $given) {
                $validator->errors()->add('order', '並び替え対象の種目がメニューの内容と一致しません。');
            }
        });
    }
}
