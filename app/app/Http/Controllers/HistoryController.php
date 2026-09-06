<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Repositories\BodyLogRepository;
use App\Repositories\WorkoutSetRepository;
use App\Services\ExerciseHistoryService;
use App\Services\PlateauAnalysisService;
use App\Support\HistoryPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function __construct(
        private readonly WorkoutSetRepository $workoutSetRepository,
        private readonly BodyLogRepository $bodyLogRepository,
        private readonly ExerciseHistoryService $historyService,
        private readonly PlateauAnalysisService $plateauAnalysisService,
    ) {}

    /**
     * 種目別履歴画面(Issue #11)。
     *
     * `exercise_id` が指定されるまでは種目の選択肢一覧のみを返す。指定後は
     * その種目の推移グラフ・全セット一覧・自己ベストを SQL 側の集計
     * ({@see WorkoutSetRepository}) から組み立てる。
     *
     * 認可: 選べる種目は既定種目(user_id = null)か自分の独自種目のみ。
     * 他ユーザーの独自種目の id を直接指定した場合は 404(findOrFail)。
     * データ自体もすべて自分の user_id で絞り込まれたクエリからしか
     * 取得しないため、他ユーザーの記録が混ざることはない。
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $exercises = Exercise::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $userId))
            ->orderBy('muscle_group')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'muscle_group', 'is_bodyweight'])
            ->map(fn (Exercise $exercise): array => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'muscle_group' => $exercise->muscle_group->value,
                'is_bodyweight' => $exercise->is_bodyweight,
            ])
            ->values();

        $period = HistoryPeriod::normalize($request->string('period')->toString());

        $exerciseId = $request->integer('exercise_id') ?: null;
        $selected = null;

        if ($exerciseId !== null) {
            $exercise = Exercise::query()
                ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $userId))
                ->findOrFail($exerciseId);

            $sinceDate = HistoryPeriod::sinceDate($period);

            $topSets = $this->workoutSetRepository->historyTopSetsPerWorkout($userId, $exercise->id, $sinceDate);
            $allSets = $this->workoutSetRepository->historyAllSets($userId, $exercise->id, $sinceDate);
            $personalBestRaw = $this->workoutSetRepository->personalBest($userId, $exercise->id);

            // 体重比(Issue #21)の算出には、期間フィルタの外にある体重記録も要る
            // (フィルタで直近3ヶ月に絞っていても、「直近過去の体重」はそれより
            // 前の記録かもしれないため)。よって全期間・昇順で取得する。
            $bodyLogs = $this->bodyLogRepository->allMeasurementsAscending($userId);

            $chart = $this->historyService->buildChart($exercise->is_bodyweight, $topSets, $bodyLogs);

            // Issue #24①: 停滞判定は表示中の期間フィルタ('3m'/'6m')に関わらず
            // 全期間のセッション履歴で行う(期間を絞ると baseline=過去の自己ベスト
            // 自体が視界から消え、誤って「停滞なし」と判定しかねないため)。
            $plateauSessions = $this->workoutSetRepository->sessionTopSetsForExercises([$exercise->id], $userId);
            $plateau = $this->plateauAnalysisService->analyze([
                [
                    'id' => $exercise->id,
                    'name' => $exercise->name,
                    'is_bodyweight' => $exercise->is_bodyweight,
                    'weight_increment' => (float) $exercise->weight_increment,
                    'target_rep_min' => $exercise->target_rep_min,
                    'target_rep_max' => $exercise->target_rep_max,
                ],
            ], $plateauSessions)[0] ?? null;

            $selected = [
                'exercise' => [
                    'id' => $exercise->id,
                    'name' => $exercise->name,
                    'muscle_group' => $exercise->muscle_group->value,
                    'is_bodyweight' => $exercise->is_bodyweight,
                ],
                'chart' => $chart,
                'sets' => $allSets,
                'personalBest' => $this->historyService->buildPersonalBest($exercise->is_bodyweight, $personalBestRaw),
                'latestBodyweightRatio' => $this->historyService->latestBodyweightRatio($chart['points']),
                'plateau' => $plateau,
            ];
        }

        return Inertia::render('History/Index', [
            'exercises' => $exercises,
            'period' => $period,
            'selectedExerciseId' => $exerciseId,
            'history' => $selected,
        ]);
    }
}
