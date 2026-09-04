<?php

declare(strict_types=1);

/*
 * Backend minimo para la simulacion de concientizacion.
 *
 * Este servidor NUNCA recibe contrasenas. Solo acepta un prefijo de 16 bits
 * de un hash PBKDF2 calculado en el navegador. El limite de longitud es una
 * regla dura: si un cliente manipulado intentara enviar el hash completo (o
 * la contrasena), la peticion se rechaza. Esa validacion es lo que sostiene
 * la garantia de que aca no hay nada que robar.
 */

const PREFIX_HEX_LEN = 4;   // 16 bits
const SALT_HEX_LEN = 32;    // 128 bits
const MAX_PARTICIPANTS = 200;
const RETENTION_DAYS = 30;
const MAX_VERIFY_ATTEMPTS = 10;

const STORE = __DIR__.'/../data/participants.json';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);

    exit;
}

function ok(array $payload): never
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);

    exit;
}

function isHex(string $value, int $length): bool
{
    return strlen($value) === $length && 1 === preg_match('/^[0-9a-f]+$/', $value);
}

/**
 * Lee el almacen descartando los registros vencidos.
 *
 * La caducidad se aplica en cada lectura, no solo en el purgado manual, para
 * que los datos de un taller no sobrevivan por olvido del facilitador.
 */
function loadStore(): array
{
    if (!is_file(STORE)) {
        return [];
    }

    $raw = file_get_contents(STORE);
    $data = json_decode((string) $raw, true);

    if (!is_array($data)) {
        return [];
    }

    $cutoff = time() - RETENTION_DAYS * 86400;

    return array_filter($data, static fn (array $r): bool => ($r['ts'] ?? 0) >= $cutoff);
}

function saveStore(array $data): void
{
    if (!is_dir(dirname(STORE))) {
        mkdir(dirname(STORE), 0770, true);
    }

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    file_put_contents(STORE, $encoded, LOCK_EX);
    chmod(STORE, 0660);
}

function jsonBody(): array
{
    $body = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($body)) {
        fail(400, 'Cuerpo JSON invalido.');
    }

    return $body;
}

function normalizeEmail(mixed $value): string
{
    $email = strtolower(trim((string) $value));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail(400, 'Correo invalido.');
    }

    return $email;
}

$action = $_GET['action'] ?? '';

if ('record' === $action) {
    $body = jsonBody();
    $email = normalizeEmail($body['email'] ?? '');
    $salt = (string) ($body['salt'] ?? '');
    $prefix = (string) ($body['prefix'] ?? '');

    // Control central: se rechaza cualquier cosa que no sea exactamente
    // 16 bits de hash. Un hash completo o una contrasena no pasan de aca.
    if (!isHex($salt, SALT_HEX_LEN) || !isHex($prefix, PREFIX_HEX_LEN)) {
        fail(422, 'Formato de huella invalido.');
    }

    $store = loadStore();

    if (!isset($store[$email]) && count($store) >= MAX_PARTICIPANTS) {
        fail(429, 'El ejercicio alcanzo su limite de participantes.');
    }

    $store[$email] = [
        'salt' => $salt,
        'prefix' => $prefix,
        'ts' => time(),
        'attempts' => 0,
    ];

    saveStore($store);

    ok(['status' => 'recorded']);
}

if ('salt' === $action) {
    $email = normalizeEmail($_GET['email'] ?? '');
    $store = loadStore();

    if (!isset($store[$email])) {
        fail(404, 'No hay ningun registro para ese correo en este ejercicio.');
    }

    ok(['salt' => $store[$email]['salt']]);
}

if ('verify' === $action) {
    $body = jsonBody();
    $email = normalizeEmail($body['email'] ?? '');
    $prefix = (string) ($body['prefix'] ?? '');

    if (!isHex($prefix, PREFIX_HEX_LEN)) {
        fail(422, 'Formato de huella invalido.');
    }

    $store = loadStore();

    if (!isset($store[$email])) {
        fail(404, 'No hay ningun registro para ese correo en este ejercicio.');
    }

    if ($store[$email]['attempts'] >= MAX_VERIFY_ATTEMPTS) {
        fail(429, 'Se agotaron los intentos de comprobacion para este correo.');
    }

    ++$store[$email]['attempts'];
    saveStore($store);

    ok(['match' => hash_equals($store[$email]['prefix'], $prefix)]);
}

if ('stats' === $action) {
    // Solo el recuento agregado: es lo unico que necesita la presentacion.
    ok(['participants' => count(loadStore())]);
}

fail(404, 'Accion desconocida.');
