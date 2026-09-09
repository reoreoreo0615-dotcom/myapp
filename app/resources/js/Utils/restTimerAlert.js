/**
 * インターバルタイマー(休憩タイマー)の完了通知(音 + バイブレーション + 画面フラッシュ)。
 *
 * Issue #28: 通知手段ごとの処理は `Utils/notifiers/` のクラス階層
 * (RestNotifier を継承した Sound / Vibration / Visual)に切り出した。
 * このファイルはそれらを `CompositeNotifier` で束ね、`IntervalTimer.vue` から
 * 呼ばれる薄い関数エントリポイントを提供するだけの役割になっている。
 *
 * iOS Safari の制約(Issue #20 で調査。詳細は各 Notifier クラスを参照):
 * - AudioContext は「ユーザー操作を起点とした呼び出し」でないと再生できない。
 *   `unlockRestTimerAudio()` をユーザー操作のコールバック内で呼んでおく必要がある。
 * - navigator.vibrate は iOS Safari には実装されていない。
 */
import { CompositeNotifier } from './notifiers/CompositeNotifier';
import { SoundNotifier } from './notifiers/SoundNotifier';
import { VibrationNotifier } from './notifiers/VibrationNotifier';
import { VisualNotifier } from './notifiers/VisualNotifier';

const soundNotifier = new SoundNotifier();
const vibrationNotifier = new VibrationNotifier();
const visualNotifier = new VisualNotifier();

const compositeNotifier = new CompositeNotifier([soundNotifier, vibrationNotifier, visualNotifier]);

/**
 * ユーザー操作(pointerdown 等)のコールバック内で呼ぶこと。
 * `SoundNotifier` の AudioContext アンロック処理を参照。
 */
export function unlockRestTimerAudio() {
    soundNotifier.unlock();
}

export function playRestTimerAlert() {
    compositeNotifier.notify();
}

/**
 * どの通知手段がこの環境で使えるかを画面表示用に返す。
 * 型を知らず、`CompositeNotifier.statuses()` に委譲するだけ。
 */
export function restNotifierStatuses() {
    return compositeNotifier.statuses();
}
