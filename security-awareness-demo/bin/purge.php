#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Borra los datos del ejercicio. Ejecutalo apenas termina el taller:
 * el archivo no contiene contrasenas, pero tampoco tiene por que sobrevivir.
 */

$store = __DIR__.'/../data/participants.json';

if (!is_file($store)) {
    echo "No hay datos que borrar.\n";

    exit(0);
}

$count = count((array) json_decode((string) file_get_contents($store), true));

// Se sobrescribe antes de desvincular para que el contenido no quede
// recuperable con herramientas de undelete sobre el bloque liberado.
file_put_contents($store, str_repeat("\0", max(1, filesize($store))));
unlink($store);

echo "Borrados {$count} registros.\n";
