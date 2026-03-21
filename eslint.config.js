import js from '@eslint/js';
import globals from 'globals';

export default [
    js.configs.recommended,
    {
        files: ['resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            'no-console':    'warn',
            'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
            'no-var':        'error',
            'prefer-const':  'error',
            'eqeqeq':        ['error', 'always'],
        },
    },
    {
        files: ['tests/E2E/**/*.js', 'playwright.config.js'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.node,
            },
        },
    },
    {
        ignores: ['public/assets/**', 'node_modules/**', 'vendor/**'],
    },
];
