/*
 * Panel para proyectar en el cierre del taller. Solo consume recuentos
 * agregados: el backend no expone correos ni huellas por esta via.
 */

const REFRESH_MS = 5000;

async function refresh() {
    const status = document.getElementById('status');

    try {
        const response = await fetch('api.php?action=stats', { cache: 'no-store' });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const stats = await response.json();

        document.getElementById('participants').textContent = stats.participants;
        document.getElementById('retried').textContent = stats.retried;
        status.textContent = `Actualizado ${new Date().toLocaleTimeString('es')}`;
    } catch (e) {
        status.textContent = `No se pudo leer el resultado: ${e.message}`;
    }
}

refresh();
setInterval(refresh, REFRESH_MS);
