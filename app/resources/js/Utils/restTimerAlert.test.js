import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// audioContext / unlocked はモジュール内のプライベート状態なので、テストごとに
// vi.resetModules() でモジュールレジストリをリセットし、動的 import し直すことで
// テスト間の状態漏れを防ぐ。
async function importFresh() {
    vi.resetModules();
    return import('./restTimerAlert');
}

function createFakeAudioContext() {
    const oscillators = [];
    class FakeAudioContext {
        constructor() {
            this.state = 'suspended';
            this.currentTime = 0;
            this.resumeCalls = 0;
            FakeAudioContext.instances.push(this);
        }
        resume() {
            this.resumeCalls += 1;
            this.state = 'running';
        }
        createOscillator() {
            const oscillator = {
                type: '',
                frequency: { value: 0 },
                start: vi.fn(),
                stop: vi.fn(),
                connect: vi.fn((dest) => dest),
            };
            oscillators.push(oscillator);
            return oscillator;
        }
        createGain() {
            return {
                gain: {
                    setValueAtTime: vi.fn(),
                    exponentialRampToValueAtTime: vi.fn(),
                },
                connect: vi.fn((dest) => dest),
            };
        }
        get destination() {
            return {};
        }
    }
    FakeAudioContext.instances = [];
    return { FakeAudioContext, oscillators };
}

describe('restTimerAlert (feature-detect)', () => {
    const originalAudioContext = window.AudioContext;
    const originalVibrate = window.navigator.vibrate;

    afterEach(() => {
        window.AudioContext = originalAudioContext;
        Object.defineProperty(window.navigator, 'vibrate', {
            value: originalVibrate,
            configurable: true,
        });
    });

    describe('AudioContext / navigator.vibrate が非対応の環境(jsdom既定 = iOS Safari相当)', () => {
        beforeEach(() => {
            delete window.AudioContext;
            delete window.webkitAudioContext;
            Object.defineProperty(window.navigator, 'vibrate', {
                value: undefined,
                configurable: true,
            });
        });

        it('unlockRestTimerAudio() を呼んでも例外を投げない', async () => {
            const { unlockRestTimerAudio } = await importFresh();
            expect(() => unlockRestTimerAudio()).not.toThrow();
        });

        it('unlock していなくても playRestTimerAlert() は例外を投げない(音もバイブも鳴らせないだけ)', async () => {
            const { playRestTimerAlert } = await importFresh();
            expect(() => playRestTimerAlert()).not.toThrow();
        });
    });

    describe('AudioContext / navigator.vibrate に両方対応した環境(Android Chrome相当)', () => {
        let fake;
        let vibrateSpy;

        beforeEach(() => {
            fake = createFakeAudioContext();
            window.AudioContext = fake.FakeAudioContext;
            vibrateSpy = vi.fn();
            Object.defineProperty(window.navigator, 'vibrate', {
                value: vibrateSpy,
                configurable: true,
            });
        });

        it('unlockRestTimerAudio() は AudioContext を生成して resume する', async () => {
            const { unlockRestTimerAudio } = await importFresh();
            unlockRestTimerAudio();
            expect(fake.FakeAudioContext.instances).toHaveLength(1);
            expect(fake.FakeAudioContext.instances[0].resumeCalls).toBe(1);
        });

        it('2回目の unlockRestTimerAudio() は AudioContext を再生成しない', async () => {
            const { unlockRestTimerAudio } = await importFresh();
            unlockRestTimerAudio();
            unlockRestTimerAudio();
            expect(fake.FakeAudioContext.instances).toHaveLength(1);
        });

        it('unlock 前に playRestTimerAlert() を呼んでも音は鳴らないが、バイブは鳴る', async () => {
            const { playRestTimerAlert } = await importFresh();
            playRestTimerAlert();
            expect(fake.oscillators).toHaveLength(0);
            expect(vibrateSpy).toHaveBeenCalledWith([200, 100, 200, 100, 200]);
        });

        it('unlock 後に playRestTimerAlert() を呼ぶとビープを3回鳴らし、バイブも鳴らす', async () => {
            const { unlockRestTimerAudio, playRestTimerAlert } = await importFresh();
            unlockRestTimerAudio();
            playRestTimerAlert();
            expect(fake.oscillators).toHaveLength(3);
            fake.oscillators.forEach((oscillator) => {
                expect(oscillator.start).toHaveBeenCalledTimes(1);
                expect(oscillator.stop).toHaveBeenCalledTimes(1);
            });
            expect(vibrateSpy).toHaveBeenCalledWith([200, 100, 200, 100, 200]);
        });
    });

    describe('navigator.vibrate が非対応(iOS)だが AudioContext は対応している環境', () => {
        it('vibrate 呼び出しで例外を投げず、音だけ鳴る', async () => {
            const fake = createFakeAudioContext();
            window.AudioContext = fake.FakeAudioContext;
            Object.defineProperty(window.navigator, 'vibrate', {
                value: undefined,
                configurable: true,
            });

            const { unlockRestTimerAudio, playRestTimerAlert } = await importFresh();
            unlockRestTimerAudio();
            expect(() => playRestTimerAlert()).not.toThrow();
            expect(fake.oscillators).toHaveLength(3);
        });
    });
});
