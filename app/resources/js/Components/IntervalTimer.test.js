import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import IntervalTimer from './IntervalTimer.vue';

const { playRestTimerAlert, unlockRestTimerAudio } = vi.hoisted(() => ({
    playRestTimerAlert: vi.fn(),
    unlockRestTimerAudio: vi.fn(),
}));

vi.mock('@/Utils/restTimerAlert', () => ({
    playRestTimerAlert,
    unlockRestTimerAudio,
}));

function storageKey(workoutId) {
    return `overload:rest-timer:${workoutId}`;
}

function clockText(wrapper) {
    return wrapper.get('.font-display.text-3xl').text();
}

function labelText(wrapper) {
    return wrapper.get('.label-micro').text();
}

describe('IntervalTimer', () => {
    const baseline = new Date('2026-01-01T00:00:00Z').getTime();

    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(baseline);
        playRestTimerAlert.mockClear();
        unlockRestTimerAudio.mockClear();
        window.localStorage.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('経過時間は開始時刻との差分で計算される', () => {
        it('画面を長時間離れて戻っても(setIntervalの回数ではなく)経過時間が正しい', async () => {
            const wrapper = mount(IntervalTimer, { props: { workoutId: 1 } });
            wrapper.vm.start(90);
            await nextTick();

            expect(clockText(wrapper)).toBe('1:30');

            // 画面を20分離れていたことをシミュレートする。setInterval は
            // (タブが非アクティブな間などに)間引かれてほとんど発火しなかった
            // 想定で、時計だけを一気に進め、tick は1回だけ発生させる。
            vi.setSystemTime(baseline + 20 * 60 * 1000);
            await vi.advanceTimersByTimeAsync(500);

            // 90秒の目標に対して1200秒経過 = 1110秒(18:30)の超過。
            // setInterval のカウントを加算する実装だと、この時点では
            // 500ms 分(概ね1秒)しか進んでいないはずの値になってしまう。
            expect(clockText(wrapper)).toBe('+18:30');
            expect(labelText(wrapper)).toBe('目標超過');
        });

        it('目標に到達していない間はカウントダウン表示になる', async () => {
            const wrapper = mount(IntervalTimer, { props: { workoutId: 1 } });
            wrapper.vm.start(120);
            await nextTick();

            vi.setSystemTime(baseline + 45 * 1000);
            await vi.advanceTimersByTimeAsync(500);

            expect(clockText(wrapper)).toBe('1:15');
            expect(labelText(wrapper)).toBe('REST');
        });

        it('目標到達の瞬間に一度だけ通知が鳴る', async () => {
            const wrapper = mount(IntervalTimer, { props: { workoutId: 1 } });
            wrapper.vm.start(60);
            await nextTick();

            vi.setSystemTime(baseline + 61 * 1000);
            await vi.advanceTimersByTimeAsync(500);
            expect(playRestTimerAlert).toHaveBeenCalledTimes(1);

            // 超過中にさらに時間が経過しても再通知はしない。
            vi.setSystemTime(baseline + 90 * 1000);
            await vi.advanceTimersByTimeAsync(500);
            expect(playRestTimerAlert).toHaveBeenCalledTimes(1);
        });
    });

    describe('localStorage からの復元', () => {
        it('保存済みの target / startedAt / running を復元して表示する', async () => {
            window.localStorage.setItem(
                storageKey(42),
                JSON.stringify({
                    targetSeconds: 90,
                    startedAt: baseline - 30 * 1000,
                    running: true,
                }),
            );

            const wrapper = mount(IntervalTimer, { props: { workoutId: 42 } });
            await nextTick();

            // 30秒経過した状態で復元されるので、残り60秒。
            expect(clockText(wrapper)).toBe('1:00');
        });

        it('離れていた間に目標を超えていた場合、復帰時に通知が鳴らない', async () => {
            window.localStorage.setItem(
                storageKey(7),
                JSON.stringify({
                    targetSeconds: 60,
                    // 既に120秒経過している = 60秒超過した状態で復帰する。
                    startedAt: baseline - 120 * 1000,
                    running: true,
                }),
            );

            const wrapper = mount(IntervalTimer, { props: { workoutId: 7 } });
            await nextTick();

            expect(clockText(wrapper)).toBe('+1:00');
            expect(playRestTimerAlert).not.toHaveBeenCalled();

            // 復元後、さらに時間が経過して超過が続いても鳴らさない
            // (跨いだ瞬間ではないため)。
            vi.setSystemTime(baseline + 10 * 1000);
            await vi.advanceTimersByTimeAsync(500);
            expect(playRestTimerAlert).not.toHaveBeenCalled();
        });

        it('壊れた値(不正なJSON)が保存されていても例外を投げず初期状態になる', () => {
            window.localStorage.setItem(storageKey(99), '{not-json');

            expect(() => mount(IntervalTimer, { props: { workoutId: 99 } })).not.toThrow();
        });

        it('targetSeconds が数値でない場合は復元しない', async () => {
            window.localStorage.setItem(
                storageKey(100),
                JSON.stringify({ targetSeconds: 'abc', startedAt: baseline, running: true }),
            );

            const wrapper = mount(IntervalTimer, { props: { workoutId: 100 } });
            await nextTick();

            // バーごと非表示(targetSeconds が null のまま)。
            expect(wrapper.find('.rest-timer-bar').exists()).toBe(false);
        });
    });

    describe('localStorage が使えない環境', () => {
        it('getItem / setItem が例外を投げても壊れず、タイマーはメモリ上で機能する', async () => {
            const getItemSpy = vi
                .spyOn(window.localStorage.__proto__, 'getItem')
                .mockImplementation(() => {
                    throw new Error('SecurityError: localStorage disabled');
                });
            const setItemSpy = vi
                .spyOn(window.localStorage.__proto__, 'setItem')
                .mockImplementation(() => {
                    throw new Error('SecurityError: localStorage disabled');
                });

            try {
                const wrapper = mount(IntervalTimer, { props: { workoutId: 1 } });
                expect(() => wrapper.vm.start(90)).not.toThrow();
                await nextTick();

                expect(clockText(wrapper)).toBe('1:30');

                vi.setSystemTime(baseline + 10 * 1000);
                await vi.advanceTimersByTimeAsync(500);
                expect(clockText(wrapper)).toBe('1:20');
            } finally {
                getItemSpy.mockRestore();
                setItemSpy.mockRestore();
            }
        });
    });
});
