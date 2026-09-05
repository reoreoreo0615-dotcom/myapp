<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoutineRequest extends FormRequest
{
    /**
     * Only the owner of the routine may update it. Delegating the policy
     * check to the FormRequest means an unauthorized attempt is rejected
     * with a 403 before validation even runs.
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
        return [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
