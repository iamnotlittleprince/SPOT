import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        // O servidor escuta em todas as interfaces, mas o navegador deve usar
        // um endereço navegável. Sem isso o plugin pode publicar 0.0.0.0 no HTML.
        origin: 'http://localhost:5173',
        // Durante o desenvolvimento, a página é servida pelo Laravel na porta
        // 8000 e carrega os módulos do Vite na 5173.
        cors: {
            origin: 'http://localhost:8000',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
