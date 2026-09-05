<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoutineExerciseRequest extends FormRequest
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
        return [
            'target_sets' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}
