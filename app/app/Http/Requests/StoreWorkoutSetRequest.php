<?php

namespace App\Http\Requests;

use App\Rules\HalfStepIncrement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutSetRequest extends FormRequest
{
    /**
     * Recording a set on a workout is gated by the same "update" ability
     * that governs the workout itself.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('workout'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'exercise_id' => [
                'required',
                'integer',
                // 選べるのは既定種目(user_id = null)か自分の独自種目のみ。
                Rule::exists('exercises', 'id')->where(
                    fn ($query) => $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))
                ),
            ],
            'weight' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'reps' => ['required', 'integer', 'min:1', 'max:999'],
            'is_warmup' => ['nullable', 'boolean'],
            // RPE(Issue #26②)は任意入力。「タップ1回で記録」を崩さないため必須にしない。
            // 既定は未入力(null)。入力する場合は 6.0〜10.0 を 0.5 刻みのみ許可する。
            'rpe' => ['nullable', 'numeric', 'between:6,10', new HalfStepIncrement],
            // 二重送信防止のための冪等キー。フロントが入力行ごとに1つ発行する。
            'client_request_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
