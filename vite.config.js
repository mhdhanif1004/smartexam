import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        port: 5173,
        hmr: { host: '127.0.0.1' },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('chart.js')) return 'vendor-chart';
                        if (id.includes('pusher-js') || id.includes('laravel-echo')) return 'vendor-echo';
                        if (id.includes('@hotwired/turbo')) return 'vendor-turbo';
                        if (id.includes('alpinejs')) return 'vendor-alpine';
                        return 'vendor';
                    }
                },
            },
        },
    },
});
