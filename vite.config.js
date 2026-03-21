import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    publicDir: false,
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
