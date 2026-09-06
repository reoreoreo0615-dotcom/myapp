import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import FirstTimeTip from './FirstTimeTip.vue';

describe('FirstTimeTip', () => {
    it('未読の場合はガイドを表示する', () => {
        const wrapper = mount(FirstTimeTip, {
            props: { storageKey: 'tip:example' },
            slots: { default: 'はじめての方へ' },
        });
        expect(wrapper.text()).toContain('はじめての方へ');
    });

    it('閉じるとガイドが消え、localStorage に既読が保存される', async () => {
        const wrapper = mount(FirstTimeTip, {
            props: { storageKey: 'tip:example' },
            slots: { default: 'はじめての方へ' },
        });

        await wrapper.get('[aria-label="このガイドを閉じる"]').trigger('click');

        expect(wrapper.find('[aria-label="このガイドを閉じる"]').exists()).toBe(false);
        expect(window.localStorage.getItem('tip:example')).toBe('1');
    });

    it('既に既読(localStorageに保存済み)の場合は最初から表示しない', () => {
        window.localStorage.setItem('tip:example', '1');

        const wrapper = mount(FirstTimeTip, {
            props: { storageKey: 'tip:example' },
            slots: { default: 'はじめての方へ' },
        });

        expect(wrapper.find('[aria-label="このガイドを閉じる"]').exists()).toBe(false);
    });

    it('localStorage の読み取りが例外を投げても表示され、壊れない(プライベートブラウズ等)', () => {
        const getItemSpy = vi
            .spyOn(window.localStorage.__proto__, 'getItem')
            .mockImplementation(() => {
                throw new Error('SecurityError');
            });

        try {
            const wrapper = mount(FirstTimeTip, {
                props: { storageKey: 'tip:example' },
                slots: { default: 'はじめての方へ' },
            });
            expect(wrapper.text()).toContain('はじめての方へ');
        } finally {
            getItemSpy.mockRestore();
        }
    });

    it('localStorage の書き込みが例外を投げても、閉じる操作自体は成立する', async () => {
        const setItemSpy = vi
            .spyOn(window.localStorage.__proto__, 'setItem')
            .mockImplementation(() => {
                throw new Error('SecurityError');
            });

        try {
            const wrapper = mount(FirstTimeTip, {
                props: { storageKey: 'tip:example' },
                slots: { default: 'はじめての方へ' },
            });
            await wrapper.get('[aria-label="このガイドを閉じる"]').trigger('click');
            expect(wrapper.find('[aria-label="このガイドを閉じる"]').exists()).toBe(false);
        } finally {
            setItemSpy.mockRestore();
        }
    });
});
