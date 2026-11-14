import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // The public site and Content Studio are separate bundles: visitors
            // never download the editor code.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/studio.css', 'resources/js/studio.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
