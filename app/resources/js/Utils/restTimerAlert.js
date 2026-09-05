/**
 * インターバルタイマー(休憩タイマー)の完了通知(音 + バイブレーション)。
 *
 * iOS Safari の制約(Issue #20 で調査):
 * - AudioContext は「ユーザー操作を起点とした呼び出し」でないと再生できない
 *   (音の自動再生制限)。タイマーの終了はユーザー操作(セット記録のタップ)
 *   から数十秒〜数分後に非同期で発生するため、終了の瞬間に AudioContext を
 *   新規生成しても iOS では鳴らせない。
 *   回避策として、ページ内で最初に指が触れた瞬間(記録ボタンのタップも含む)
 *   に AudioContext を生成 → resume() して「アンロック」しておき、以後は
 *   同じインスタンスを使い回す。一度アンロックすれば、そのページを離れない
 *   限り任意のタイミングで再生できる。
 * - navigator.vibrate は iOS Safari には実装されていない(Vibration API は
 *   WebKit が長年未対応のまま)。feature-detect し、非対応環境では何もせず
 *   例外も投げない。Android Chrome 等では有効に動作する。
 */

let audioContext = null;
let unlocked = false;

/**
 * ユーザー操作(pointerdown 等)のコールバック内で呼ぶこと。
 * AudioContext の生成・resume() は「ユーザー操作起点の呼び出し」である
 * 必要があるため、setInterval のコールバックなど非同期の中で呼んでも
 * iOS Safari では効果がない。
 */
export function unlockRestTimerAudio() {
    if (unlocked) {
        return;
    }
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) {
            return;
        }
        if (!audioContext) {
            audioContext = new AudioContextClass();
        }
        if (audioContext.state === 'suspended') {
            audioContext.resume();
        }
        unlocked = true;
    } catch {
        // Web Audio 非対応・生成失敗時は何もしない(タイマー自体は継続する)。
    }
}

export function playRestTimerAlert() {
    beep();
    vibrate();
}

function beep() {
    try {
        if (!audioContext) {
            // unlockRestTimerAudio が一度も成功していない(ユーザー操作を
            // まだ経ていない、または iOS で Web Audio 未対応)場合は鳴らせない。
            return;
        }
        if (audioContext.state === 'suspended') {
            audioContext.resume();
        }
        const ctx = audioContext;
        const startAt = ctx.currentTime;
        // 短いビープを3回。ジムの騒音下でも気づきやすいよう明確なリズムにする。
        [0, 0.22, 0.44].forEach((offset) => {
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = 880;
            gain.gain.setValueAtTime(0.0001, startAt + offset);
            gain.gain.exponentialRampToValueAtTime(0.5, startAt + offset + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, startAt + offset + 0.2);
            oscillator.connect(gain).connect(ctx.destination);
            oscillator.start(startAt + offset);
            oscillator.stop(startAt + offset + 0.22);
        });
    } catch {
        // 再生に失敗しても、タイマー自体(表示)は動き続けさせる。
    }
}

function vibrate() {
    try {
        if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
            navigator.vibrate([200, 100, 200, 100, 200]);
        }
    } catch {
        // 非対応環境(iOS Safari 等)では何もしない。
    }
}
