import { describe, expect, it, vi } from 'vitest';
import { CompositeNotifier } from './CompositeNotifier';
import { RestNotifier } from './RestNotifier';

// CompositeNotifier が「型を知らずに一様に扱う」ことを検証するため、
// 本番の Sound / Vibration / Visual とは異なる、テスト用の架空の
// RestNotifier サブクラスを使う。CompositeNotifier 側のコードを一切
// 変更せずに新しい通知手段を追加できることの実証も兼ねる。
class FakeSupportedNotifier extends RestNotifier {
    constructor(label = 'fake-supported') {
        super();
        this._label = label;
        this.notifyCalls = 0;
    }

    label() {
        return this._label;
    }

    isSupported() {
        return true;
    }

    doNotify() {
        this.notifyCalls += 1;
    }
}

class FakeUnsupportedNotifier extends RestNotifier {
    constructor(label = 'fake-unsupported') {
        super();
        this._label = label;
        this.notifyCalls = 0;
    }

    label() {
        return this._label;
    }

    isSupported() {
        return false;
    }

    doNotify() {
        this.notifyCalls += 1;
    }
}

describe('CompositeNotifier', () => {
    it('isSupported() は束ねている中に1つでも対応していれば true', () => {
        const composite = new CompositeNotifier([
            new FakeUnsupportedNotifier(),
            new FakeSupportedNotifier(),
        ]);
        expect(composite.isSupported()).toBe(true);
    });

    it('isSupported() は全て非対応なら false', () => {
        const composite = new CompositeNotifier([
            new FakeUnsupportedNotifier(),
            new FakeUnsupportedNotifier(),
        ]);
        expect(composite.isSupported()).toBe(false);
    });

    it('notify() は対応している Notifier だけを呼ぶ(型を知らずに一様に扱う)', () => {
        const supported = new FakeSupportedNotifier();
        const unsupported = new FakeUnsupportedNotifier();
        const composite = new CompositeNotifier([supported, unsupported]);

        composite.notify();

        expect(supported.notifyCalls).toBe(1);
        expect(unsupported.notifyCalls).toBe(0);
    });

    it('全て非対応の場合、notify() は何もせず例外も投げない', () => {
        const composite = new CompositeNotifier([
            new FakeUnsupportedNotifier(),
            new FakeUnsupportedNotifier(),
        ]);
        expect(() => composite.notify()).not.toThrow();
    });

    it('notifiers は notify() / isSupported() というインターフェースだけを呼ばれる(instanceof を使わない)', () => {
        // notifiers 配列に何が入っていても composite.notify() は notify() を
        // 呼ぶだけであることを、スパイで直接確認する。CompositeNotifier の
        // 実装が `instanceof Sound/Vibration/Visual` のような分岐をしていれば
        // このスパイベースの Notifier では動かないはずだが、正しく動く。
        const notifySpy = vi.fn();
        const duckTypedNotifier = {
            isSupported: () => true,
            notify: notifySpy,
        };
        const composite = new CompositeNotifier([duckTypedNotifier]);

        composite.notify();

        expect(notifySpy).toHaveBeenCalledTimes(1);
    });

    it('statuses() は各 Notifier の label() と isSupported() を返す(型を知らない)', () => {
        const composite = new CompositeNotifier([
            new FakeSupportedNotifier('音'),
            new FakeUnsupportedNotifier('バイブレーション'),
        ]);

        expect(composite.statuses()).toEqual([
            { label: '音', supported: true },
            { label: 'バイブレーション', supported: false },
        ]);
    });

    it('新しい通知手段を追加しても CompositeNotifier 自体は変更不要(既存クラスへの依存が無いことの実証)', () => {
        class BrandNewNotifier extends RestNotifier {
            label() {
                return '新しい通知手段';
            }

            isSupported() {
                return true;
            }

            doNotify() {
                this.called = true;
            }
        }

        const brandNew = new BrandNewNotifier();
        const composite = new CompositeNotifier([brandNew]);

        composite.notify();

        expect(brandNew.called).toBe(true);
    });
});
