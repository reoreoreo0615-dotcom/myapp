<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV ストリーム出力の共通処理(Issue #26①)。
 *
 * データ取得(DBアクセス)には関知しない。呼び出し元が渡す `iterable` を
 * 1行ずつ `php://output` に書き出すだけなので、渡す側が
 * `LazyCollection`/`Generator` であれば全件をメモリに保持せずに済む。
 */
class CsvExportService
{
    /**
     * UTF-8 BOM 付き CSV を `StreamedResponse` として返す。
     *
     * BOM を先頭に書き込むのは、Excel でそのまま開いたときに日本語が
     * 文字化けするのを防ぐため(Excel は BOM が無い UTF-8 を Shift_JIS 等と
     * 誤認識することがある)。
     *
     * @param  array<int, string>  $headerRow
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    public function stream(string $filename, array $headerRow, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headerRow, $rows): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headerRow);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
