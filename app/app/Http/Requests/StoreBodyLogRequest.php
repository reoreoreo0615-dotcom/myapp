<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBodyLogRequest extends FormRequest
{
    /**
     * Any authenticated user may record their own body weight (ownership is
     * set by the controller, not chosen by the request), so no ownership
     * check is needed here. The 1-entry-per-day rule is enforced by the
     * controller via updateOrCreate (same-day re-entry overwrites instead of
     * erroring), not by a uniqueness validation rule.
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
            'measured_on' => ['required', 'date', 'before_or_equal:today'],
            'weight_kg' => ['required', 'numeric', 'min:1', 'max:999.99'],
            'body_fat_percentage' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'memo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
