import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Ensure Chart.js plugins file is included in the Vite manifest
                'resources/js/filament-chart-js-plugins.js',
            ],
            refresh: true,
        }),
    ],
});
