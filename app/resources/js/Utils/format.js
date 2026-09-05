/**
 * 数値表示の整形。
 * 整数はそのまま、小数は小数第2位までに丸め、末尾の不要な0は toString() の性質上
 * 自然に落ちる(62.50 -> 62.5)。
 */
export function formatNumber(value) {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '';
    }

    if (Number.isInteger(value)) {
        return String(value);
    }

    return String(Math.round(value * 100) / 100);
}
