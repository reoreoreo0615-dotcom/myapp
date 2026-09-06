import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import NumberStepper from './NumberStepper.vue';

// NumberStepper は v-model で親から modelValue を受け取る前提のコンポーネント。
// 実際の使われ方(親が update:modelValue を受けて prop を更新する)を
// テストでも再現するため、update イベントを受けたら setProps で
// modelValue を書き戻す薄いヘルパーを使う。
function mountStepper(modelValue, props = {}) {
    let wrapper;
    wrapper = mount(NumberStepper, {
        props: {
            modelValue,
            ...props,
            'onUpdate:modelValue': (value) => {
                wrapper.setProps({ modelValue: value });
            },
        },
    });
    return wrapper;
}

function minusButton(wrapper) {
    return wrapper.get('[aria-label="減らす"]');
}

function plusButton(wrapper) {
    return wrapper.get('[aria-label="増やす"]');
}

describe('NumberStepper', () => {
    it('+ を1回タップすると step ぶん増える(タップ1回=1ステップ)', async () => {
        const wrapper = mountStepper(10, { step: 2.5 });
        await plusButton(wrapper).trigger('click');
        expect(wrapper.props('modelValue')).toBe(12.5);
    });

    it('- を1回タップすると step ぶん減る', async () => {
        const wrapper = mountStepper(10, { step: 2.5 });
        await minusButton(wrapper).trigger('click');
        expect(wrapper.props('modelValue')).toBe(7.5);
    });

    it('max を超えてタップしても max でクランプされる', async () => {
        const wrapper = mountStepper(9, { step: 5, min: 0, max: 10 });
        await plusButton(wrapper).trigger('click');
        expect(wrapper.props('modelValue')).toBe(10);
    });

    it('min を下回ってタップしても min でクランプされる', async () => {
        const wrapper = mountStepper(1, { step: 5, min: 0, max: 100 });
        await minusButton(wrapper).trigger('click');
        expect(wrapper.props('modelValue')).toBe(0);
    });

    it('既に max に達している場合、+ ボタンは disabled になる', () => {
        const wrapper = mountStepper(10, { step: 1, max: 10 });
        expect(plusButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('既に min に達している場合、- ボタンは disabled になる', () => {
        const wrapper = mountStepper(0, { step: 1, min: 0 });
        expect(minusButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('クランプされて値が変わらない場合は update:modelValue を発火しない', async () => {
        const wrapper = mountStepper(10, { step: 5, max: 10 });
        await plusButton(wrapper).trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('0.1 + 0.2 のような加算で浮動小数の誤差が出ない', async () => {
        const wrapper = mountStepper(0.1, { step: 0.2, max: 100 });
        await plusButton(wrapper).trigger('click');
        // 生の 0.1 + 0.2 は 0.30000000000000004 になるが、
        // コンポーネント内部の丸めにより厳密に 0.3 になっていること。
        expect(wrapper.props('modelValue')).toBe(0.3);
    });

    it('長押しすると連続で増減する(フェイクタイマー)', async () => {
        vi.useFakeTimers();
        try {
            const wrapper = mountStepper(0, { step: 1, max: 100 });
            const button = plusButton(wrapper);

            await button.trigger('pointerdown');
            // pointerdown 直後に1回目が即時反映される(タップ1回=1ステップを崩さない)。
            expect(wrapper.props('modelValue')).toBe(1);

            // 450ms 経過するまでは連続増加が始まらない。
            await vi.advanceTimersByTimeAsync(449);
            expect(wrapper.props('modelValue')).toBe(1);

            // 450ms 経過で holdInterval が起動し、以後 100ms ごとに増加する。
            await vi.advanceTimersByTimeAsync(1);
            await vi.advanceTimersByTimeAsync(100);
            expect(wrapper.props('modelValue')).toBe(2);

            await vi.advanceTimersByTimeAsync(300);
            expect(wrapper.props('modelValue')).toBe(5);

            await button.trigger('pointerup');
            await vi.advanceTimersByTimeAsync(1000);
            // pointerup 後はタイマーがクリアされ、これ以上増えない。
            expect(wrapper.props('modelValue')).toBe(5);
        } finally {
            vi.useRealTimers();
        }
    });

    describe('pointerdown 経由の click 二重発火防止', () => {
        beforeEach(() => {
            vi.useFakeTimers();
        });
        afterEach(() => {
            vi.useRealTimers();
        });

        it('pointerdown の直後に発火する合成 click は無視される', async () => {
            const wrapper = mountStepper(0, { step: 1, max: 100 });
            const button = plusButton(wrapper);

            await button.trigger('pointerdown');
            expect(wrapper.props('modelValue')).toBe(1);

            await button.trigger('pointerup');
            // ブラウザは pointerup の直後、ほぼ同じタイミングで click を発火する。
            // pointerActive の解除には猶予(300ms)があるため、ここではまだ無視される。
            await button.trigger('click');
            expect(wrapper.props('modelValue')).toBe(1);
        });

        it('猶予(300ms)を過ぎてからの click(キーボード操作等)は通常どおり処理される', async () => {
            const wrapper = mountStepper(0, { step: 1, max: 100 });
            const button = plusButton(wrapper);

            await button.trigger('pointerdown');
            await button.trigger('pointerup');
            expect(wrapper.props('modelValue')).toBe(1);

            await vi.advanceTimersByTimeAsync(300);
            await button.trigger('click');
            expect(wrapper.props('modelValue')).toBe(2);
        });
    });
});
