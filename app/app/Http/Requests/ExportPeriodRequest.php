<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CSVエクスポート(Issue #26①)の期間フィルタ。`from`/`to` はどちらも任意で、
 * 指定しなければ下限/上限なし(全期間)として扱う。
 */
class ExportPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ログイン済みユーザーなら誰でも「自分の」データをエクスポートできる。
        // 自分のデータしか出さないという担保は、コントローラ側で必ず
        // 自分の user_id で絞り込んだクエリを使うことで行う。
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function fromDate(): ?string
    {
        $value = $this->validated('from');

        return $value !== null ? (string) $value : null;
    }

    public function toDate(): ?string
    {
        $value = $this->validated('to');

        return $value !== null ? (string) $value : null;
    }
}
