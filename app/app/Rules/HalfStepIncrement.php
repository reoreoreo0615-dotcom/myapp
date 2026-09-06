<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 値が 0.5 刻みであることを検証する(Issue #26②: RPE の入力を 6.0〜10.0 の
 * 0.5 刻みに限定するため)。`between:6,10` と組み合わせて使う想定。
 */
class HalfStepIncrement implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $doubled = (float) $value * 2;

        if (abs($doubled - round($doubled)) > 0.0001) {
            $fail('The :attribute must be in increments of 0.5.');
        }
    }
}
