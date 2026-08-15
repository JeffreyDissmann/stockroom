import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // v4 runs as a Vite plugin rather than through PostCSS, and handles
        // vendor prefixing itself — hence no autoprefixer here or in deps.
        tailwindcss(),
        // Regenerates resources/js/{actions,routes,wayfinder} from the Laravel
        // route table during dev (file watch). The generated files are
        // committed (see CLAUDE.md) and verified in CI via wayfinder:check —
        // so we pin this plugin to dev-only (`apply: 'serve'`). Production
        // builds (including the Docker frontend stage, which has no PHP)
        // consume the committed tree directly.
        { ...wayfinder(), apply: 'serve' },
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './resources/js'),
        },
    },
});
