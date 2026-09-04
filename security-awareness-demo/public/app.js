import { newSalt, derivePrefix } from './crypto.js';
import { config, resolveMode } from './config.js';

/*
 * Tiempo tras el cual se revela la simulacion aunque la persona no haya
 * tocado "Volver a intentar". Solo aplica al modo 'reveal': sin esto, quien
 * se aleja de la pantalla se queda sin la parte educativa, que es el unico
 * motivo del ejercicio. En 'handoff' no corre, porque ahi la explicacion la
 * da el facilitador al cerrar el taller.
 */
const AUTO_REVEAL_MS = 20000;

const mode = resolveMode();

const form = document.getElementById('login-form');
const submit = document.getElementById('submit');
const errorBox = document.getElementById('error');

let autoRevealTimer = null;

function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = false;
}

function show(id) {
    for (const screen of document.querySelectorAll('.screen')) {
        screen.hidden = screen.id !== id;
    }
}

function reveal(clickedRetry) {
    clearTimeout(autoRevealTimer);

    document.getElementById('retry-callout').hidden = !clickedRetry;
    document.getElementById('host').textContent = location.host;
    show('screen-result');
    window.scrollTo(0, 0);
}

function recordRetry() {
    navigator.sendBeacon?.(
        'api.php?action=retry',
        new Blob([JSON.stringify({ email: form.dataset.email })], { type: 'application/json' })
    );
}

document.getElementById('retry').addEventListener('click', () => {
    recordRetry();

    if ('handoff' === mode) {
        // La persona entra normalmente en el sitio legitimo y no nota nada.
        // Es justo lo que hace un ataque real, y por eso el cierre del taller
        // es obligatorio: sin el, esto no ensena nada.
        location.replace(config.realLoginUrl);

        return;
    }

    // El clic es la leccion: se revela en el acto.
    reveal(true);
});

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

    form.dataset.email = email;
    document.getElementById('prefix').textContent = prefix;

    show('screen-error');

    if ('reveal' === mode) {
        autoRevealTimer = setTimeout(() => reveal(false), AUTO_REVEAL_MS);
    }
});
