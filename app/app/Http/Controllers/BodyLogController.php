<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBodyLogRequest;
use App\Http\Requests\UpdateBodyLogRequest;
use App\Models\BodyLog;
use App\Repositories\BodyLogRepository;
use App\Support\HistoryPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class BodyLogController extends Controller
{
    public function __construct(
        private readonly BodyLogRepository $bodyLogRepository,
    ) {}

    /**
     * 体重の記録・一覧画面(Issue #21)。
     *
     * データはすべて自分の user_id で絞り込まれたクエリからしか取得しないため、
     * 他ユーザーの記録が混ざることはない。
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $period = HistoryPeriod::normalize($request->string('period')->toString());
        $sinceDate = HistoryPeriod::sinceDate($period);

        $logs = $this->bodyLogRepository->listForUser($userId, $sinceDate);

        return Inertia::render('BodyWeight/Index', [
            'period' => $period,
            'logs' => $logs,
            // 記録フォームの初期値。前回値をプリセットしておくと、体重の変化が
            // 小さい日々の入力でステッパーの操作量が最小で済む。
            'latest' => $logs[0] ?? null,
        ]);
    }

    /**
     * 体重を記録する。同日の再入力はエラーにせず上書きする
     * ((user_id, measured_on) の組で updateOrCreate)。
     */
    public function store(StoreBodyLogRequest $request): RedirectResponse
    {
        $data = $request->validated();

        BodyLog::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'measured_on' => $data['measured_on'],
            ],
            [
                'weight_kg' => $data['weight_kg'],
                'body_fat_percentage' => $data['body_fat_percentage'] ?? null,
                'memo' => $data['memo'] ?? null,
            ],
        );

        return Redirect::route('body-logs.index')->with('success', '体重を記録しました。');
    }

    /**
     * 既存の記録を修正する。
     */
    public function update(UpdateBodyLogRequest $request, BodyLog $bodyLog): RedirectResponse
    {
        $bodyLog->update($request->validated());

        return Redirect::route('body-logs.index')->with('success', '記録を更新しました。');
    }

    /**
     * 記録を削除する。
     */
    public function destroy(Request $request, BodyLog $bodyLog): RedirectResponse
    {
        $this->authorize('delete', $bodyLog);

        $bodyLog->delete();

        return Redirect::route('body-logs.index')->with('success', '記録を削除しました。');
    }
}
