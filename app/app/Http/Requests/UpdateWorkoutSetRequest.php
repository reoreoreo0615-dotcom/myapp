<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkoutSetRequest extends FormRequest
{
    /**
     * Editing a set on a workout is gated by the same "update" ability
     * that governs the workout itself. The exercise a set belongs to is
     * not editable (only weight / reps / is_warmup can be corrected).
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
        return [
            'weight' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'reps' => ['required', 'integer', 'min:1', 'max:999'],
            'is_warmup' => ['nullable', 'boolean'],
        ];
    }
}
