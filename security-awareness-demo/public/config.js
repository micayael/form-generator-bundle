/*
 * Configuracion del ejercicio.
 */

export const config = {
    /*
     * 'reveal'  - Al hacer clic en "Volver a intentar" se muestra en el acto
     *             que era una simulacion. Es el modo para una campana por
     *             correo, donde no hay nadie presente para explicar.
     *
     * 'handoff' - El boton redirige al login real, donde la persona entra
     *             normalmente y queda sin entender que paso. Reproduce lo que
     *             hace un ataque de verdad: no se nota nada.
     *
     *             USAR SOLO EN TALLER PRESENCIAL. Este modo no explica nada
     *             por si mismo: depende enteramente de que el facilitador
     *             cierre la sesion contando que ocurrio. Si lo usas en una
     *             campana por correo, la gente se queda con la confusion y
     *             sin la leccion, que es lo unico que justifica el ejercicio.
     */
    mode: 'reveal',

    /*
     * Destino del modo 'handoff': el login legitimo de tu plataforma.
     * Debe ser https. Si falta o no es https, el ejercicio vuelve solo a
     * 'reveal' en lugar de mandar a la gente a ningun lado raro.
     */
    realLoginUrl: '',
};

export function resolveMode() {
    if ('handoff' !== config.mode) {
        return 'reveal';
    }

    let url;

    try {
        url = new URL(config.realLoginUrl);
    } catch {
        console.warn('[simulacion] realLoginUrl no es una URL valida; se usa el modo reveal.');

        return 'reveal';
    }

    if ('https:' !== url.protocol) {
        console.warn('[simulacion] realLoginUrl debe ser https; se usa el modo reveal.');

        return 'reveal';
    }

    return 'handoff';
}
