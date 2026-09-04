import { newSalt, derivePrefix } from './crypto.js';

const form = document.getElementById('login-form');
const submit = document.getElementById('submit');
const errorBox = document.getElementById('error');

function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = false;
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;

    const email = document.getElementById('email').value.trim().toLowerCase();
    const password = document.getElementById('password').value;

    if (!email || !password) {
        showError('Ingresa tu correo y tu contrasena.');

        return;
    }

    submit.disabled = true;
    submit.textContent = 'Verificando…';

    // El salt y el hash se calculan aca. La contrasena no se envia a ningun lado.
    const salt = newSalt();
    const prefix = await derivePrefix(password, salt);

    // Borrado inmediato del campo: nada de plaintext queda vivo en el DOM.
    document.getElementById('password').value = '';

    try {
        const response = await fetch('api.php?action=record', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, salt, prefix }),
        });

        if (!response.ok) {
            const detail = await response.json().catch(() => ({}));

            throw new Error(detail.error || 'No se pudo registrar el intento.');
        }
    } catch (e) {
        submit.disabled = false;
        submit.textContent = 'Iniciar sesion';
        showError(e.message);

        return;
    }

    document.getElementById('prefix').textContent = prefix;
    document.getElementById('host').textContent = location.host;
    document.getElementById('screen-login').hidden = true;
    document.getElementById('screen-result').hidden = false;
});
