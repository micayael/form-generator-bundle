import { derivePrefix } from './crypto.js';

const form = document.getElementById('verify-form');
const submit = document.getElementById('submit');
const errorBox = document.getElementById('error');
const resultBox = document.getElementById('result');

function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = false;
}

async function call(url, options) {
    const response = await fetch(url, options);
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload.error || 'Error de comunicacion con el servidor.');
    }

    return payload;
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    resultBox.hidden = true;

    const email = document.getElementById('email').value.trim().toLowerCase();
    const password = document.getElementById('password').value;

    if (!email || !password) {
        showError('Completa los dos campos.');

        return;
    }

    submit.disabled = true;
    submit.textContent = 'Comparando…';

    try {
        // El salt original es necesario para reproducir exactamente el mismo hash.
        const { salt } = await call(`api.php?action=salt&email=${encodeURIComponent(email)}`);

        const prefix = await derivePrefix(password, salt);

        document.getElementById('password').value = '';

        const { match } = await call('api.php?action=verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, prefix }),
        });

        resultBox.className = match ? 'match' : 'nomatch';
        resultBox.innerHTML = match
            ? '<strong>Coincide.</strong> Esta es la contrasena que escribiste en '
              + 'el sitio falso. Un atacante real la tendria completa, en texto '
              + 'plano, y ya estaria dentro de tu cuenta.'
            : '<strong>No coincide.</strong> Esta no es la contrasena que '
              + 'escribiste ese dia.';
        resultBox.hidden = false;
    } catch (e) {
        showError(e.message);
    } finally {
        submit.disabled = false;
        submit.textContent = 'Comparar';
    }
});
