<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutRequest extends FormRequest
{
    /**
     * Any authenticated user may start their own workout (ownership is set
     * by the controller, not chosen by the request), so no ownership check
     * is needed here. The routine, if any, must belong to the user.
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
            'routine_id' => [
                'nullable',
                'integer',
                Rule::exists('routines', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            // Issue #23②: ジムで入力し忘れた日を後から記録できるように、
            // 開始日を選べるようにする。既定(未指定)は今日。未来日は不可。
            'performed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
