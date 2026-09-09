import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { SoundNotifier } from './SoundNotifier';

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

describe('SoundNotifier', () => {
    const originalAudioContext = window.AudioContext;

    afterEach(() => {
        window.AudioContext = originalAudioContext;
    });

    describe('AudioContext 非対応の環境(iOS Safari 相当…ではなく、より古い環境)', () => {
        beforeEach(() => {
            delete window.AudioContext;
            delete window.webkitAudioContext;
        });

        it('isSupported() は false を返す', () => {
            expect(new SoundNotifier().isSupported()).toBe(false);
        });

        it('unlock() を呼んでも例外を投げない', () => {
            const notifier = new SoundNotifier();
            expect(() => notifier.unlock()).not.toThrow();
        });

        it('notify() は isSupported() が false なので何もしない(例外も投げない)', () => {
            const notifier = new SoundNotifier();
            expect(() => notifier.notify()).not.toThrow();
        });
    });

    describe('AudioContext 対応環境', () => {
        let fake;

        beforeEach(() => {
            fake = createFakeAudioContext();
            window.AudioContext = fake.FakeAudioContext;
        });

        it('isSupported() は true を返す', () => {
            expect(new SoundNotifier().isSupported()).toBe(true);
        });

        it('unlock() は AudioContext を生成して resume する', () => {
            const notifier = new SoundNotifier();
            notifier.unlock();

            expect(fake.FakeAudioContext.instances).toHaveLength(1);
            expect(fake.FakeAudioContext.instances[0].resumeCalls).toBe(1);
        });

        it('2回目の unlock() は AudioContext を再生成しない', () => {
            const notifier = new SoundNotifier();
            notifier.unlock();
            notifier.unlock();

            expect(fake.FakeAudioContext.instances).toHaveLength(1);
        });

        it('unlock() していない状態で notify() を呼んでもビープは鳴らない(例外も投げない)', () => {
            const notifier = new SoundNotifier();
            expect(() => notifier.notify()).not.toThrow();
            expect(fake.oscillators).toHaveLength(0);
        });

        it('unlock() 後に notify() を呼ぶとビープを3回鳴らす', () => {
            const notifier = new SoundNotifier();
            notifier.unlock();
            notifier.notify();

            expect(fake.oscillators).toHaveLength(3);
            fake.oscillators.forEach((oscillator) => {
                expect(oscillator.start).toHaveBeenCalledTimes(1);
                expect(oscillator.stop).toHaveBeenCalledTimes(1);
            });
        });
    });
});
