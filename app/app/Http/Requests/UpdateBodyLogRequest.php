<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBodyLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bodyLog'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'measured_on' => [
                'required',
                'date',
                'before_or_equal:today',
                // 編集で日付を、既に別のレコードがある日にずらすことはできない。
                // 「同日の再入力は上書き」は新規記録(store)側の挙動であり、
                // 既存レコードの編集で暗黙に別レコードを消してしまわないための制約。
                Rule::unique('body_logs', 'measured_on')
                    ->where(fn ($query) => $query->where('user_id', $userId))
                    ->ignore($this->route('bodyLog')),
            ],
            'weight_kg' => ['required', 'numeric', 'min:1', 'max:999.99'],
            'body_fat_percentage' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'memo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
