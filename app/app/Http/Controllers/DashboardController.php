<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardSummaryService $summaryService,
    ) {}

    /**
     * ログイン後のホーム画面(Issue #4)。
     *
     * 4タイル分の集計データの組み立ては {@see DashboardSummaryService} の責務。
     * 集計はすべて自分の user_id で絞り込まれたクエリからのみ取得するため、
     * 他ユーザーの記録が混ざることはない。
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'summary' => $this->summaryService->build($request->user()->id),
        ]);
    }
}
