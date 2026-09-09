import { describe, expect, it, vi } from 'vitest';
import { RestNotifier } from './RestNotifier';

describe('RestNotifier(基底クラス)', () => {
    it('isSupported() は既定で false を返す', () => {
        const notifier = new RestNotifier();
        expect(notifier.isSupported()).toBe(false);
    });

    it('isSupported() が false のとき notify() は doNotify() を呼ばない', () => {
        const notifier = new RestNotifier();
        const doNotifySpy = vi.spyOn(notifier, 'doNotify');

        expect(() => notifier.notify()).not.toThrow();
        expect(doNotifySpy).not.toHaveBeenCalled();
    });

    it('サブクラスで isSupported() が true になれば doNotify() が呼ばれる', () => {
        class AlwaysSupportedNotifier extends RestNotifier {
            isSupported() {
                return true;
            }
        }
        const notifier = new AlwaysSupportedNotifier();
        const doNotifySpy = vi.spyOn(notifier, 'doNotify');

        notifier.notify();

        expect(doNotifySpy).toHaveBeenCalledTimes(1);
    });

    it('doNotify() が例外を投げても notify() は例外を投げない', () => {
        class ThrowingNotifier extends RestNotifier {
            isSupported() {
                return true;
            }

            doNotify() {
                throw new Error('boom');
            }
        }
        const notifier = new ThrowingNotifier();

        expect(() => notifier.notify()).not.toThrow();
    });

    it('label() は既定の文字列を返す', () => {
        const notifier = new RestNotifier();
        expect(notifier.label()).toBe('通知');
    });
});
