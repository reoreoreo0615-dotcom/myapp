import { RestNotifier } from './RestNotifier';

/**
 * 複数の RestNotifier を束ね、使えるものすべてで通知する。
 *
 * 個々の Notifier の具体的な型は一切知らない(instanceof で分岐しない)。
 * `isSupported()` / `notify()` という共通のインターフェースだけに依存する
 * ため、新しい通知手段を追加してもこのクラスは変更不要。
 */
export class CompositeNotifier extends RestNotifier {
    constructor(notifiers = []) {
        super();
        this.notifiers = notifiers;
    }

    label() {
        return 'すべての通知手段';
    }

    // 束ねている中に1つでも使えるものがあれば「対応している」とみなす。
    isSupported() {
        return this.notifiers.some((notifier) => notifier.isSupported());
    }

    // 型を知らずに一様に扱う。使えないものは各 Notifier 自身の notify() が
    // 何もしないので、ここで instanceof による分岐は不要。
    doNotify() {
        this.notifiers
            .filter((notifier) => notifier.isSupported())
            .forEach((notifier) => notifier.notify());
    }

    /**
     * 画面表示用に、束ねている Notifier それぞれの対応状況を返す。
     * これも型を知らず、共通インターフェース(label / isSupported)だけを見る。
     */
    statuses() {
        return this.notifiers.map((notifier) => ({
            label: notifier.label(),
            supported: notifier.isSupported(),
        }));
    }
}
