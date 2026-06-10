// resources/js/app.js
// Spot Desktop — NativePHP + Laravel 13

import './bootstrap';

// Loading state no botão de login
const form   = document.getElementById('loginForm');
const btn    = document.getElementById('btnEnter');

if (form && btn) {
    form.addEventListener('submit', () => {
        btn.classList.add('loading');
        btn.disabled = true;
    });
}
