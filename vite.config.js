import { defineConfig } from 'vite';
import { resolve } from 'path';
import istanbul from 'vite-plugin-istanbul';

const coveragePlugins = process.env.VITE_COVERAGE === 'true'
    ? [istanbul({ include: 'resources/js/**', exclude: ['node_modules', 'tests'], extension: ['.js'], requireEnv: false, forceBuildInstrument: true })]
    : [];

export default defineConfig({
    publicDir: false,
    plugins: coveragePlugins,
    build: {
        outDir: 'public/assets',
        emptyOutDir: false,
        rollupOptions: {
            input: {
                main:         resolve(__dirname, 'resources/js/main.js'),
                'admin-charts': resolve(__dirname, 'resources/js/admin-charts.js'),
            },
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: ({ name }) => {
                    if (name?.endsWith('.css')) return 'css/main.css';
                    return '[name][extname]';
                },
            },
        },
    },
});
