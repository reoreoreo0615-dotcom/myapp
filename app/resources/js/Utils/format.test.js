import { describe, expect, it } from 'vitest';
import { formatNumber } from './format';

describe('formatNumber', () => {
    it('整数はそのまま文字列にする', () => {
        expect(formatNumber(62)).toBe('62');
        expect(formatNumber(0)).toBe('0');
        expect(formatNumber(-5)).toBe('-5');
    });

    it('小数は末尾の不要な0を落として表示する(桁区切り相当)', () => {
        expect(formatNumber(62.5)).toBe('62.5');
        expect(formatNumber(62.1)).toBe('62.1');
    });

    it('小数第2位までに丸める', () => {
        // 62.345 は浮動小数の表現誤差を避けつつ、小数第2位で丸められること。
        expect(formatNumber(62.345)).toBe('62.35');
        expect(formatNumber(62.344)).toBe('62.34');
    });

    it('0.1 + 0.2 のような加算誤差を丸めで吸収する', () => {
        const sum = 0.1 + 0.2; // 0.30000000000000004
        expect(formatNumber(sum)).toBe('0.3');
    });

    it('null / undefined / NaN は空文字を返す', () => {
        expect(formatNumber(null)).toBe('');
        expect(formatNumber(undefined)).toBe('');
        expect(formatNumber(Number.NaN)).toBe('');
    });
});
