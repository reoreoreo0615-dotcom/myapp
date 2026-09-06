import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting';
import globals from 'globals';

// Prettier が担当する見た目の整形(改行・インデント・クォート等)は
// ESLint 側で二重にチェックしない。skipFormatting でそれらのルールだけ無効化する。
export default [
    {
        ignores: ['node_modules/**', 'public/**', 'vendor/**'],
    },
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    skipFormatting,
    {
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
                route: 'readonly',
            },
        },
        rules: {
            // テンプレート内で複数トップレベル要素を許可(v-if / v-else 等の分岐で頻出)。
            'vue/multi-word-component-names': 'off',
        },
    },
    {
        files: ['**/*.test.js'],
        languageOptions: {
            globals: {
                ...globals.vitest,
            },
        },
    },
];
