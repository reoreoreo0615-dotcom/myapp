import { RestNotifier } from './RestNotifier';

/**
 * Web Audio API によるビープ音通知。
 *
 * iOS Safari の制約(Issue #20 で調査):
 * AudioContext は「ユーザー操作を起点とした呼び出し」でないと再生できない
 * (音の自動再生制限)。タイマーの終了はユーザー操作(セット記録のタップ)
 * から数十秒〜数分後に非同期で発生するため、終了の瞬間に AudioContext を
 * 新規生成しても iOS では鳴らせない。
 * 回避策として、ページ内で最初に指が触れた瞬間(記録ボタンのタップも含む)
 * に `unlock()` を呼び、AudioContext を生成 → resume() しておく。一度
 * アンロックすれば、そのページを離れない限り任意のタイミングで再生できる。
 */
export class SoundNotifier extends RestNotifier {
    constructor() {
        super();
        this.audioContext = null;
        this.unlocked = false;
    }

    label() {
        return '音';
    }

    /**
     * Web Audio API 自体の対応可否(iOS Safari を含め、現行ブラウザは
     * ほぼすべて対応している)。「まだ unlock() していない」は非対応とは
     * 区別する(`doNotify()` 側で扱う)。
     */
    isSupported() {
        return (
            typeof window !== 'undefined' &&
            Boolean(window.AudioContext || window.webkitAudioContext)
        );
    }

    /**
     * ユーザー操作(pointerdown 等)のコールバック内で呼ぶこと。
     * AudioContext の生成・resume() は「ユーザー操作起点の呼び出し」である
     * 必要があるため、setInterval のコールバックなど非同期の中で呼んでも
     * iOS Safari では効果がない。
     */
    unlock() {
        if (this.unlocked || !this.isSupported()) {
            return;
        }
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!this.audioContext) {
                this.audioContext = new AudioContextClass();
            }
            if (this.audioContext.state === 'suspended') {
                this.audioContext.resume();
            }
            this.unlocked = true;
        } catch {
            // Web Audio 生成・resume に失敗した場合は何もしない。
        }
    }

    doNotify() {
        if (!this.audioContext) {
            // unlock() が一度も成功していない(ユーザー操作をまだ経ていない)
            // 場合は鳴らせない。
            return;
        }
        if (this.audioContext.state === 'suspended') {
            this.audioContext.resume();
        }
        const ctx = this.audioContext;
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
    }
}
