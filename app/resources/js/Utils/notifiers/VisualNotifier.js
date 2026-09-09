import { RestNotifier } from './RestNotifier';

const FLASH_CLASS = 'rest-timer-flash';
const FLASH_ANIMATION_NAME = 'rest-timer-flash';

/**
 * 画面のフラッシュ(色変化)による通知。
 *
 * 音・バイブとは異なり、DOM の存在さえあればどの環境でも必ず使える
 * フォールバック手段(`isSupported()` は常に true に近い)。
 * `document.body` に一瞬だけクラスを付け外しし、実際のアニメーションは
 * `resources/css/app.css` 側の `@keyframes rest-timer-flash` に任せる。
 * クラスの除去はタイマーではなく `animationend` イベントで行う
 * (setTimeout をタイマーと切り離すことで、テスト側で実タイマーが
 * 残り続けるのを避ける)。
 */
export class VisualNotifier extends RestNotifier {
    constructor() {
        super();
        this._onAnimationEnd = (event) => {
            if (event.animationName === FLASH_ANIMATION_NAME) {
                document.body.classList.remove(FLASH_CLASS);
            }
        };
        if (this.isSupported()) {
            document.body.addEventListener('animationend', this._onAnimationEnd);
        }
    }

    label() {
        return '画面フラッシュ';
    }

    isSupported() {
        return typeof document !== 'undefined' && Boolean(document.body);
    }

    doNotify() {
        const { body } = document;
        // 直前のフラッシュがまだ残っている状態で連続して呼ばれても、
        // クラスの再付与でアニメーションを再生させるため、一度外して
        // 強制的にリフローさせてから付け直す。
        body.classList.remove(FLASH_CLASS);
        void body.offsetWidth;
        body.classList.add(FLASH_CLASS);
    }
}
