import { fileURLToPath } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

// vite.config.js とは別にする。laravel-vite-plugin は開発サーバー/ビルド用の
// 前提(hot ファイルの有無など)を持っており、テスト実行時に読み込むと
// 余計な依存が発生するため、テストに必要な最小構成だけをここに書く。
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.test.js'],
        setupFiles: ['./resources/js/testSetup.js'],
    },
});
