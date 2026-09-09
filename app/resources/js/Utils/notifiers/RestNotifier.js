/**
 * 休憩タイマー完了を知らせる通知手段の基底クラス(Issue #28)。
 *
 * サブクラスは `isSupported()` と `doNotify()`(必要なら `label()`)だけを
 * 実装すればよい。「未対応の環境では例外を投げず黙って何もしない」という
 * 共通の振る舞いはここに1箇所だけ書き、サブクラス側で毎回 try/catch や
 * feature-detect を書かなくて済むようにする。
 */
export class RestNotifier {
    /**
     * この環境でこの通知手段が使えるかどうか。
     * サブクラスで必ず上書きすること(基底クラスは常に false = 何もしない)。
     */
    isSupported() {
        return false;
    }

    /**
     * 設定画面・状態表示に出す名前。サブクラスで上書きする。
     */
    label() {
        return '通知';
    }

    /**
     * 通知を実行する。`isSupported()` が false の場合は何もしない。
     * `doNotify()` が例外を投げても、呼び出し側(タイマー本体)の処理を
     * 止めないようここで握りつぶす。
     */
    notify() {
        if (!this.isSupported()) {
            return;
        }
        try {
            this.doNotify();
        } catch {
            // 通知の実行に失敗しても、タイマー自体は動き続けさせる。
        }
    }

    /**
     * 実際の通知処理。サブクラスで実装する。
     */
    doNotify() {
        // 基底クラスでは何もしない。
    }
}
