<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportPeriodRequest;
use App\Repositories\BodyLogRepository;
use App\Repositories\WorkoutSetRepository;
use App\Services\CsvExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * データのCSVエクスポート(Issue #26①)。
 *
 * ローカル運用でバックアップ手段が無いことへの対処。全件を配列に持たず
 * ストリーム出力し、必ず自分の user_id で絞り込んだデータのみを出力する。
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly WorkoutSetRepository $workoutSetRepository,
        private readonly BodyLogRepository $bodyLogRepository,
        private readonly CsvExportService $csvExportService,
    ) {}

    /**
     * ワークアウト記録(セット単位)の CSV。
     * 列: 日付 / 種目 / セット番号 / 重量 / 回数 / RPE / ウォームアップ / メモ
     */
    public function workoutSets(ExportPeriodRequest $request): StreamedResponse
    {
        $userId = $request->user()->id;
        $fromDate = $request->fromDate();
        $toDate = $request->toDate();

        $rows = $this->workoutSetRepository->exportRows($userId, $fromDate, $toDate)
            ->map(fn ($row): array => [
                $row->performed_on,
                $row->exercise_name,
                $row->set_number,
                $row->weight,
                $row->reps,
                $row->rpe ?? '',
                $row->is_warmup ? '1' : '0',
                $row->memo ?? '',
            ]);

        return $this->csvExportService->stream(
            'workouts_'.now()->format('Ymd_His').'.csv',
            ['日付', '種目', 'セット番号', '重量', '回数', 'RPE', 'ウォームアップ', 'メモ'],
            $rows,
        );
    }

    /**
     * 体重記録の CSV。
     * 列: 日付 / 体重 / 体脂肪率 / メモ
     */
    public function bodyLogs(ExportPeriodRequest $request): StreamedResponse
    {
        $userId = $request->user()->id;
        $fromDate = $request->fromDate();
        $toDate = $request->toDate();

        $rows = $this->bodyLogRepository->exportRows($userId, $fromDate, $toDate)
            ->map(fn ($log): array => [
                $log->measured_on->format('Y-m-d'),
                $log->weight_kg,
                $log->body_fat_percentage ?? '',
                $log->memo ?? '',
            ]);

        return $this->csvExportService->stream(
            'body_weight_'.now()->format('Ymd_His').'.csv',
            ['日付', '体重', '体脂肪率', 'メモ'],
            $rows,
        );
    }
}
