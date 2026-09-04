#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Prueba de extremo a extremo contra un servidor local.
 *
 * Reproduce en PHP el mismo PBKDF2 que hace el navegador, para verificar
 * que el ida y vuelta funciona y que el backend rechaza cualquier intento
 * de enviar mas de 16 bits.
 */

const BASE = 'http://127.0.0.1:8399';
const ITERATIONS = 310000;

$failures = 0;

function check(string $name, bool $condition): void
{
    global $failures;

    if (!$condition) {
        ++$failures;
    }

    echo ($condition ? '  ok    ' : '  FALLA ').$name."\n";
}

function request(string $path, ?array $body = null): array
{
    $context = stream_context_create([
        'http' => [
            'method' => null === $body ? 'GET' : 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => null === $body ? null : json_encode($body),
            'ignore_errors' => true,
        ],
    ]);

    $raw = file_get_contents(BASE.$path, false, $context);
    $status = (int) explode(' ', $http_response_header[0])[1];

    return [$status, json_decode((string) $raw, true) ?? []];
}

/** Equivalente exacto de derivePrefix() del navegador. */
function derivePrefix(string $password, string $saltHex, int $bytes = 2): string
{
    $full = hash_pbkdf2('sha256', $password, hex2bin($saltHex), ITERATIONS, 64, false);

    return substr($full, 0, $bytes * 2);
}

$email = 'prueba@empresa.com';
$password = 'Verano2024!';
$salt = bin2hex(random_bytes(16));
$prefix = derivePrefix($password, $salt);

echo "Autoprueba de la simulacion\n\n";

[$status] = request('/api.php?action=record', ['email' => $email, 'salt' => $salt, 'prefix' => $prefix]);
check('se registra la huella', 200 === $status);

[$status, $payload] = request('/api.php?action=salt&email='.urlencode($email));
check('se recupera el salt', 200 === $status && $payload['salt'] === $salt);

[$status, $payload] = request('/api.php?action=verify', ['email' => $email, 'prefix' => derivePrefix($password, $payload['salt'])]);
check('la contrasena correcta coincide', 200 === $status && true === $payload['match']);

[, $payload] = request('/api.php?action=verify', ['email' => $email, 'prefix' => derivePrefix('OtraClave99', $salt)]);
check('una contrasena distinta no coincide', false === $payload['match']);

// El control que sostiene toda la garantia de privacidad.
$fullHash = hash_pbkdf2('sha256', $password, hex2bin($salt), ITERATIONS, 64, false);
[$status] = request('/api.php?action=record', ['email' => $email, 'salt' => $salt, 'prefix' => $fullHash]);
check('se rechaza el hash completo', 422 === $status);

[$status] = request('/api.php?action=record', ['email' => $email, 'salt' => $salt, 'prefix' => 'abcdef']);
check('se rechaza una huella de mas de 16 bits', 422 === $status);

[$status] = request('/api.php?action=salt&email='.urlencode('nadie@empresa.com'));
check('un correo desconocido no filtra nada', 404 === $status);

[$status, $payload] = request('/api.php?action=stats');
check('las estadisticas son solo un recuento', 200 === $status && ['participants' => 1] === $payload);

echo "\n".(0 === $failures ? "Todo en orden.\n" : "{$failures} comprobacion(es) fallaron.\n");

exit($failures > 0 ? 1 : 0);
