import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { VibrationNotifier } from './VibrationNotifier';

describe('VibrationNotifier', () => {
    const originalVibrate = window.navigator.vibrate;

    afterEach(() => {
        Object.defineProperty(window.navigator, 'vibrate', {
            value: originalVibrate,
            configurable: true,
        });
    });

    describe('navigator.vibrate が未定義の環境(iOS Safari 相当)', () => {
        beforeEach(() => {
            Object.defineProperty(window.navigator, 'vibrate', {
                value: undefined,
                configurable: true,
            });
        });

        it('isSupported() は false を返す', () => {
            expect(new VibrationNotifier().isSupported()).toBe(false);
        });

        it('notify() を呼んでも例外を投げず、何もしない', () => {
            const notifier = new VibrationNotifier();
            expect(() => notifier.notify()).not.toThrow();
        });
    });

    describe('navigator.vibrate に対応した環境(Android Chrome 相当)', () => {
        let vibrateSpy;

        beforeEach(() => {
            vibrateSpy = vi.fn();
            Object.defineProperty(window.navigator, 'vibrate', {
                value: vibrateSpy,
                configurable: true,
            });
        });

        it('isSupported() は true を返す', () => {
            expect(new VibrationNotifier().isSupported()).toBe(true);
        });

        it('notify() は既定のパターンでバイブレーションさせる', () => {
            new VibrationNotifier().notify();
            expect(vibrateSpy).toHaveBeenCalledWith([200, 100, 200, 100, 200]);
        });

        it('navigator.vibrate が例外を投げても notify() は例外を投げない', () => {
            vibrateSpy.mockImplementation(() => {
                throw new Error('boom');
            });
            const notifier = new VibrationNotifier();
            expect(() => notifier.notify()).not.toThrow();
        });
    });
});
