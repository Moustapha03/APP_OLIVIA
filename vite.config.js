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
    // Inline (empty) PostCSS config: Tailwind v4 runs through its Vite plugin,
    // and this stops Vite from picking up a postcss.config.js in a parent folder.
    css: {
        postcss: {},
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
