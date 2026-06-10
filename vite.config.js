import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],

    // Necessário para o NativePHP (Electron não usa localhost padrão)
    server: {
        host: '127.0.0.1',
        port: 5173,
    },
});
