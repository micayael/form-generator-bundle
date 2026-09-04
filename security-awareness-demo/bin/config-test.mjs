/*
 * Comprueba que el modo 'handoff' solo se active con una URL https valida.
 *
 * Es el unico modo que deja a la persona sin explicacion, asi que cualquier
 * configuracion incompleta tiene que degradar a 'reveal' en vez de mandar a
 * la gente a un destino inesperado.
 *
 * Uso: node bin/config-test.mjs
 */

import { config, resolveMode } from '../public/config.js';

let failures = 0;

function check(name, condition) {
    if (!condition) {
        failures++;
    }

    console.log(`  ${condition ? 'ok   ' : 'FALLA'} ${name}`);
}

function withConfig(mode, realLoginUrl) {
    config.mode = mode;
    config.realLoginUrl = realLoginUrl;

    return resolveMode();
}

console.log('Validacion de la configuracion\n');

check('por defecto no hay redireccion', 'reveal' === withConfig('reveal', ''));
check('handoff sin URL degrada a reveal', 'reveal' === withConfig('handoff', ''));
check('handoff con http degrada a reveal', 'reveal' === withConfig('handoff', 'http://portal.example.com/login'));
check('handoff con otro esquema degrada a reveal', 'reveal' === withConfig('handoff', 'ftp://portal.example.com/login'));
check('handoff con basura degrada a reveal', 'reveal' === withConfig('handoff', 'no-es-una-url'));
check('handoff con https se activa', 'handoff' === withConfig('handoff', 'https://portal.example.com/login'));
check('un modo desconocido degrada a reveal', 'reveal' === withConfig('otro', 'https://portal.example.com/login'));

console.log(`\n${0 === failures ? 'Todo en orden.' : `${failures} comprobacion(es) fallaron.`}`);
process.exit(failures ? 1 : 0);
