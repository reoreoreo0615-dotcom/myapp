<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutineExerciseRequest extends FormRequest
{
    /**
     * Adding an exercise to a routine is an edit of that routine, so it is
     * gated by the same "update" ability as editing the routine's name.
     */
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
            'exercise_id' => [
                'required',
                'integer',
                // 選べるのは既定種目(user_id = null)か自分の独自種目のみ。
                // 他人の独自種目は選択肢に出ないし、選ぼうとしても弾く。
                Rule::exists('exercises', 'id')->where(
                    fn ($query) => $query->where(function ($q) {
                        $q->whereNull('user_id')->orWhere('user_id', $this->user()->id);
                    })
                ),
                // 同じ種目を同じメニューに二重追加できないようにする
                // (routine_exercises の unique(routine_id, exercise_id) と対になる検証)。
                Rule::unique('routine_exercises', 'exercise_id')
                    ->where(fn ($query) => $query->where('routine_id', $routine->id)),
            ],
            'target_sets' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exercise_id.unique' => 'この種目はすでにメニューに追加されています。',
        ];
    }
}
