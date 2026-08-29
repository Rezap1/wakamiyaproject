import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    // Keep manifest keys stable across Windows and POSIX builds. Laravel's
    // @vite() resolver looks up the source-relative entry names.
    build: {
        rollupOptions: {
            input: {
                'resources/css/app.css': 'resources/css/app.css',
                'resources/js/app.js': 'resources/js/app.js',
            },
        },
    },
});
