/*
 * Hasheo del lado del cliente para la simulacion de concientizacion.
 *
 * Invariante de diseno: la contrasena NUNCA sale de este navegador.
 * Solo se transmite un prefijo de 16 bits del hash PBKDF2, que es
 * insuficiente para identificar una contrasena (1 de cada 65.536
 * candidatos colisiona), pero suficiente para que la persona
 * compruebe la coincidencia con la suya durante el taller.
 */

export const PBKDF2_ITERATIONS = 310000;
export const PREFIX_BYTES = 2; // 16 bits -> 4 caracteres hex

function toHex(buffer) {
    return Array.from(new Uint8Array(buffer))
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('');
}

function fromHex(hex) {
    const out = new Uint8Array(hex.length / 2);

    for (let i = 0; i < out.length; i++) {
        out[i] = parseInt(hex.substr(i * 2, 2), 16);
    }

    return out;
}

export function newSalt() {
    return toHex(crypto.getRandomValues(new Uint8Array(16)));
}

/**
 * Deriva el prefijo de 16 bits a partir de la contrasena y el salt.
 *
 * La contrasena se normaliza en NFC para que dos tecleos identicos con
 * distinta composicion Unicode produzcan el mismo hash. No se recorta:
 * los espacios al principio o al final son parte de la contrasena.
 */
export async function derivePrefix(password, saltHex) {
    const material = await crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(password.normalize('NFC')),
        'PBKDF2',
        false,
        ['deriveBits']
    );

    const bits = await crypto.subtle.deriveBits(
        {
            name: 'PBKDF2',
            salt: fromHex(saltHex),
            iterations: PBKDF2_ITERATIONS,
            hash: 'SHA-256',
        },
        material,
        256
    );

    return toHex(bits).slice(0, PREFIX_BYTES * 2);
}
