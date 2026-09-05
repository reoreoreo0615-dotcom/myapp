<?php

namespace App\Http\Requests;

use App\Enums\Equipment;
use App\Enums\MuscleGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreExerciseRequest extends FormRequest
{
    /**
     * Any authenticated user may add their own custom exercise
     * (`user_id` is forced to the current user by the controller).
     */
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
            'name' => [
                'required',
                'string',
                'max:80',
                // 同名重複の扱い: 自分の既存の独自種目とは重複を禁止する
                // (誤って同じ種目を何度も作ってしまう事故を防ぐ)。
                // 一方で既定種目や他人の独自種目とは名前が重なっても許可する
                // (他ユーザーのデータ存在を漏らさないため、かつ他人の種目名と
                // 衝突するかどうかをこのユーザーが気にする理由はない)。
                Rule::unique('exercises')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'muscle_group' => ['required', new Enum(MuscleGroup::class)],
            'equipment' => ['required', new Enum(Equipment::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'この名前の種目はすでに登録されています。',
        ];
    }
}
