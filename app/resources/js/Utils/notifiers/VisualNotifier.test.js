import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { VisualNotifier } from './VisualNotifier';

function dispatchAnimationEnd(animationName) {
    // jsdom は実際に CSS アニメーションを実行しないため、`animationend` は
    // 自然には発火しない。クラス除去のロジックを検証するため、実際に
    // ブラウザが発火させるイベントを模して手動でディスパッチする。
    const event = new Event('animationend', { bubbles: true });
    event.animationName = animationName;
    document.body.dispatchEvent(event);
}

describe('VisualNotifier', () => {
    beforeEach(() => {
        document.body.classList.remove('rest-timer-flash');
    });

    afterEach(() => {
        document.body.classList.remove('rest-timer-flash');
    });

    it('isSupported() は document.body がある環境では true を返す', () => {
        expect(new VisualNotifier().isSupported()).toBe(true);
    });

    it('notify() で document.body にフラッシュ用クラスが付く', () => {
        new VisualNotifier().notify();
        expect(document.body.classList.contains('rest-timer-flash')).toBe(true);
    });

    it('animationend イベントでフラッシュ用クラスが外れる(setTimeout を使わない)', () => {
        new VisualNotifier().notify();
        expect(document.body.classList.contains('rest-timer-flash')).toBe(true);

        dispatchAnimationEnd('rest-timer-flash');

        expect(document.body.classList.contains('rest-timer-flash')).toBe(false);
    });

    it('無関係な animationend イベントではフラッシュ用クラスを外さない', () => {
        new VisualNotifier().notify();

        dispatchAnimationEnd('some-other-animation');

        expect(document.body.classList.contains('rest-timer-flash')).toBe(true);
    });

    it('短時間に連続で呼ばれても例外を投げない', () => {
        const notifier = new VisualNotifier();
        expect(() => {
            notifier.notify();
            notifier.notify();
        }).not.toThrow();
        expect(document.body.classList.contains('rest-timer-flash')).toBe(true);
    });

    it('document が無い(SSR相当)環境では isSupported() が false になり、notify() は何もしない', () => {
        const notifier = new VisualNotifier();
        vi.spyOn(notifier, 'isSupported').mockReturnValue(false);

        expect(() => notifier.notify()).not.toThrow();
        expect(document.body.classList.contains('rest-timer-flash')).toBe(false);
    });
});
