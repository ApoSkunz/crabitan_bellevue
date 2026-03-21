import { defineConfig } from 'vite';
import { resolve } from 'path';
import istanbul from 'vite-plugin-istanbul';

const coveragePlugins = process.env.VITE_COVERAGE === 'true'
    ? [istanbul({ include: 'resources/js/**', exclude: ['node_modules', 'tests'], extension: ['.js'], requireEnv: false })]
    : [];

export default defineConfig({
    publicDir: false,
    plugins: coveragePlugins,
    build: {
        outDir: 'public/assets',
        emptyOutDir: false,
        rollupOptions: {
            input: resolve(__dirname, 'resources/js/main.js'),
            output: {
                entryFileNames: 'js/main.js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: ({ name }) => {
                    if (name?.endsWith('.css')) return 'css/main.css';
                    return '[name][extname]';
                },
            },
        },
    },
});
