import { RestNotifier } from './RestNotifier';

/**
 * navigator.vibrate によるバイブレーション通知。
 *
 * iOS Safari には実装されていない(Vibration API は WebKit が長年未対応の
 * まま)。`isSupported()` で feature-detect し、非対応環境では基底クラスの
 * `notify()` が何もせずに終わる。Android Chrome 等では有効に動作する。
 */
export class VibrationNotifier extends RestNotifier {
    label() {
        return 'バイブレーション';
    }

    isSupported() {
        return typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function';
    }

    doNotify() {
        navigator.vibrate([200, 100, 200, 100, 200]);
    }
}
