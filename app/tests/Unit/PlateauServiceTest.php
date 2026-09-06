<?php

namespace Tests\Unit;

use App\Services\PlateauService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PlateauService は Eloquent / DB に依存しない純粋なロジックなので、
 * モックを使わず値を直接渡してテストする。RefreshDatabase は使わない。
 */
class PlateauServiceTest extends TestCase
{
    private PlateauService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PlateauService;
    }

    private function session(float $weight, int $reps, float $metric): array
    {
        return ['weight' => $weight, 'reps' => $reps, 'metric' => $metric];
    }

    // ------------------------------------------------------------------
    // データ不足(判断3: N+1セッション未満は判定しない)
    // ------------------------------------------------------------------

    public static function insufficientSessionsProvider(): array
    {
        return [
            'セッションが1件のみ' => [[76.0]],
            'セッションがちょうど N(3)件(baseline を確定できない)' => [[70.0, 72.0, 74.0]],
        ];
    }

    #[DataProvider('insufficientSessionsProvider')]
    public function test_returns_null_when_not_enough_sessions(array $metrics): void
    {
        $sessions = array_map(fn (float $m) => $this->session(60.0, 8, $m), $metrics);

        $result = $this->service->detect($sessions, 2.5, 8, 12);

        $this->assertNull($result);
    }

    public function test_returns_null_with_zero_sessions(): void
    {
        $this->assertNull($this->service->detect([], 2.5, 8, 12));
    }

    // ------------------------------------------------------------------
    // 更新がある場合(停滞ではない)
    // ------------------------------------------------------------------

    public function test_returns_null_when_the_most_recent_session_sets_a_new_best(): void
    {
        // baseline は 70(直近3件を除く最大)。直近3件の最大が76なので更新あり。
        $sessions = [
            $this->session(60.0, 8, 60.0),
            $this->session(65.0, 8, 65.0),
            $this->session(70.0, 8, 70.0),
            $this->session(70.0, 8, 70.0),
            $this->session(70.0, 8, 70.0),
            $this->session(76.0, 8, 76.0),
        ];

        $this->assertNull($this->service->detect($sessions, 2.5, 8, 12));
    }

    public function test_returns_null_when_update_happens_in_the_middle_of_the_recent_window(): void
    {
        // 直近3件のうち、真ん中でPRを更新していれば停滞ではない。
        $sessions = [
            $this->session(60.0, 8, 60.0),
            $this->session(70.0, 8, 70.0), // baseline
            $this->session(65.0, 8, 65.0),
            $this->session(75.0, 8, 75.0), // 更新
            $this->session(68.0, 8, 68.0),
        ];

        $this->assertNull($this->service->detect($sessions, 2.5, 8, 12));
    }

    // ------------------------------------------------------------------
    // 停滞: 横ばい(stagnant)
    // ------------------------------------------------------------------

    public function test_flat_recent_sessions_are_stagnant_not_declining(): void
    {
        $sessions = [
            $this->session(55.0, 8, 55.0),
            $this->session(60.0, 8, 60.0), // baseline
            $this->session(60.0, 8, 60.0),
            $this->session(60.0, 8, 60.0),
            $this->session(60.0, 8, 60.0),
        ];

        $result = $this->service->detect($sessions, 2.5, 8, 12);

        $this->assertNotNull($result);
        $this->assertSame('stagnant', $result['status']);
        $this->assertSame(3, $result['sessions_without_update']);
        $this->assertSame(60.0, $result['baseline']['weight']);
        $this->assertSame(8, $result['baseline']['reps']);
    }

    public function test_a_single_dip_followed_by_recovery_is_stagnant_not_declining(): void
    {
        // 判断2: 体調不良の1回で「下がっている」と断定しない。
        // 直近3件: 60(baseline) -> 55(不調) -> 60(戻した) は非減少ではないため declining にならない。
        $sessions = [
            $this->session(50.0, 8, 50.0),
            $this->session(60.0, 8, 60.0), // baseline
            $this->session(60.0, 8, 60.0),
            $this->session(55.0, 8, 55.0), // 不調
            $this->session(60.0, 8, 60.0), // 戻した
        ];

        $result = $this->service->detect($sessions, 2.5, 8, 12);

        $this->assertNotNull($result);
        $this->assertSame('stagnant', $result['status']);
    }

    // ------------------------------------------------------------------
    // 停滞: 下降傾向(declining)
    // ------------------------------------------------------------------

    public function test_sustained_decline_is_declining(): void
    {
        // 直近3件が単調非増加かつ最後が最初より低い。
        $sessions = [
            $this->session(50.0, 8, 50.0),
            $this->session(60.0, 8, 60.0), // baseline
            $this->session(60.0, 8, 60.0),
            $this->session(57.5, 8, 57.5),
            $this->session(55.0, 8, 55.0),
        ];

        $result = $this->service->detect($sessions, 2.5, 8, 12);

        $this->assertNotNull($result);
        $this->assertSame('declining', $result['status']);
    }

    public function test_last_session_dropping_once_after_flat_sessions_is_stagnant_not_declining(): void
    {
        // 判断2: 直近3件が baseline, baseline, baseline未満(最後の1回だけ下がった)は、
        // 下降が「連続」していない(60→60は横ばい)ため declining にはしない。
        $sessions = [
            $this->session(50.0, 8, 50.0),
            $this->session(60.0, 8, 60.0), // baseline
            $this->session(60.0, 8, 60.0),
            $this->session(60.0, 8, 60.0),
            $this->session(50.0, 8, 50.0),
        ];

        $result = $this->service->detect($sessions, 2.5, 8, 12);

        $this->assertNotNull($result);
        $this->assertSame('stagnant', $result['status']);
    }

    // ------------------------------------------------------------------
    // 提案(デロード・レップレンジ)
    // ------------------------------------------------------------------

    public function test_weighted_exercise_suggests_weight_deload_and_rep_range_shift(): void
    {
        $sessions = [
            $this->session(50.0, 8, 50.0),
            $this->session(60.0, 8, 60.0),
            $this->session(60.0, 8, 60.0),
            $this->session(60.0, 8, 60.0),
            $this->session(60.0, 8, 60.0),
        ];

        $result = $this->service->detect($sessions, 2.5, 8, 12);

        $this->assertNotNull($result);
        $suggestions = $result['suggestions'];
        $this->assertCount(2, $suggestions);

        $deload = $suggestions[0];
        $this->assertSame('deload_weight', $deload['type']);
        $this->assertSame(60.0, $deload['current_weight']);
        // 60 * 0.9 = 54 → 2.5刻みで丸めると55.0
        $this->assertSame(55.0, $deload['deload_weight']);
        $this->assertLessThan($deload['current_weight'], $deload['deload_weight']);

        $repRange = $suggestions[1];
        $this->assertSame('rep_range', $repRange['type']);
        $this->assertSame(8, $repRange['current_rep_min']);
        $this->assertSame(12, $repRange['current_rep_max']);
        // 例: 8-12 → 5-8(仕様書の例と一致)
        $this->assertSame(5, $repRange['suggested_rep_min']);
        $this->assertSame(8, $repRange['suggested_rep_max']);
    }

    public function test_bodyweight_exercise_with_zero_added_weight_suggests_reps_deload(): void
    {
        // 自重種目・加重なし(weight=0)。metric は reps。
        $sessions = [
            $this->session(0.0, 8, 8.0),
            $this->session(0.0, 12, 12.0),
            $this->session(0.0, 12, 12.0),
            $this->session(0.0, 12, 12.0),
            $this->session(0.0, 12, 12.0),
        ];

        $result = $this->service->detect($sessions, 1.25, 8, 15);

        $this->assertNotNull($result);
        $deload = $result['suggestions'][0];
        $this->assertSame('deload_reps', $deload['type']);
        $this->assertSame(12, $deload['current_reps']);
        $this->assertLessThan(12, $deload['deload_reps']);
        $this->assertGreaterThanOrEqual(1, $deload['deload_reps']);
    }

    public function test_deload_weight_never_rounds_up_to_the_current_weight(): void
    {
        // increment が粗く、素朴な丸めだと 0.9倍 が現在重量に戻ってしまうケース。
        // weight=20, increment=5 → 20*0.9=18 → 5刻みで丸めると20(=現在重量)になるため、
        // 最低でも1刻み分(15)は下げる。
        $sessions = [
            $this->session(15.0, 8, 15.0),
            $this->session(20.0, 8, 20.0),
            $this->session(20.0, 8, 20.0),
            $this->session(20.0, 8, 20.0),
            $this->session(20.0, 8, 20.0),
        ];

        $result = $this->service->detect($sessions, 5.0, 8, 12);

        $deload = $result['suggestions'][0];
        $this->assertSame(15.0, $deload['deload_weight']);
    }
}
