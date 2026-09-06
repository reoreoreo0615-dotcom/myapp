import { afterEach } from 'vitest';

// 各テストの後で jsdom のグローバル状態(localStorage / タイマー / モック)を
// 必ずリセットする。IntervalTimer は localStorage に永続化するため、これを
// 怠るとテスト間でキーが衝突し、意図せず「復元」されたテストになってしまう。
afterEach(() => {
    window.localStorage.clear();
});
