import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

const { restNotifierStatuses } = vi.hoisted(() => ({
    restNotifierStatuses: vi.fn(),
}));

vi.mock('@/Utils/restTimerAlert', () => ({
    restNotifierStatuses,
}));

// vi.mock は巻き上げられるため、コンポーネント自体は各テストの中で
// 動的 import する(restNotifierStatuses の戻り値をテストごとに変えたいため)。
async function mountStatus() {
    const { default: RestNotifierStatus } = await import('./RestNotifierStatus.vue');
    return mount(RestNotifierStatus);
}

describe('RestNotifierStatus', () => {
    it('各通知手段のラベルと対応状況を表示する(型を知らず、渡された配列をそのまま表示する)', async () => {
        restNotifierStatuses.mockReturnValue([
            { label: '音', supported: true },
            { label: 'バイブレーション', supported: false },
            { label: '画面フラッシュ', supported: true },
        ]);

        const wrapper = await mountStatus();

        expect(wrapper.text()).toContain('音');
        expect(wrapper.text()).toContain('バイブレーション');
        expect(wrapper.text()).toContain('画面フラッシュ');
        expect(wrapper.text()).toContain('○');
        expect(wrapper.text()).toContain('×');
    });

    it('使えない通知手段がある場合、補足の注意文を表示する', async () => {
        restNotifierStatuses.mockReturnValue([{ label: 'バイブレーション', supported: false }]);

        const wrapper = await mountStatus();

        expect(wrapper.text()).toContain('この端末では使えません');
    });

    it('すべて使える場合、補足の注意文は表示しない', async () => {
        restNotifierStatuses.mockReturnValue([
            { label: '音', supported: true },
            { label: '画面フラッシュ', supported: true },
        ]);

        const wrapper = await mountStatus();

        expect(wrapper.text()).not.toContain('この端末では使えません');
    });
});
