<?php

namespace App\Http\Requests\Admin;

use App\Enums\Equipment;
use App\Enums\MovementType;
use App\Enums\MuscleGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateExerciseRequest extends FormRequest
{
    /**
     * Admin-only access is already enforced by the 'admin' route middleware.
     * That the target is actually a default exercise (user_id = null) is
     * checked in the controller, since only there do we have a clean place
     * to return a 404 rather than a validation error.
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
                Rule::unique('exercises')
                    ->where(fn ($query) => $query->whereNull('user_id'))
                    ->ignore($this->route('exercise')),
            ],
            'muscle_group' => ['required', new Enum(MuscleGroup::class)],
            'movement_type' => ['required', new Enum(MovementType::class)],
            'equipment' => ['required', new Enum(Equipment::class)],
            'is_bodyweight' => ['required', 'boolean'],
            'weight_increment' => ['required', 'numeric', 'gt:0', 'max:99.99'],
            'target_rep_min' => ['required', 'integer', 'min:1', 'max:999'],
            'target_rep_max' => ['required', 'integer', 'min:1', 'max:999', 'gte:target_rep_min'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:32767'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'この名前の種目はすでに登録されています。',
            'weight_increment.gt' => '重量の刻み幅は0より大きい値を指定してください。',
            'target_rep_max.gte' => '目標レップ数の上限は下限以上にしてください。',
        ];
    }
}
