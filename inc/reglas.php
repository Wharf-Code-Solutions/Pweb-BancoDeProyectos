<?php
/**
 * Traduce los errores de los triggers y restricciones de la base a
 * mensajes para el usuario. Los triggers lanzan SQLSTATE 45000 con un texto
 * que empieza con la regla (ej. "RN-03: ..."); la app valida antes, así que
 * este es el respaldo cuando algo se escapa.
 */

function mensaje_error_bd(PDOException $ex): string
{
    $info = $ex->errorInfo ?? [];
    $estado = $info[0] ?? '';
    $texto = (string) ($info[2] ?? $ex->getMessage());

    if ($estado === '45000') {
        // Quita el prefijo "RN-xx: " y deja el mensaje del trigger.
        return preg_replace('/^[A-Z]+-\d+:\s*/', '', $texto);
    }
    if (($info[1] ?? 0) === 1062) {
        return 'Ese registro ya existe.';
    }
    if (in_array($info[1] ?? 0, [1451, 1452], true)) {
        return 'El registro está relacionado con otros datos y no se puede completar la operación.';
    }
    if (($info[1] ?? 0) === 4025) {
        return 'Algún dato no cumple con el formato permitido.';
    }
    return 'No se pudo completar la operación. Intenta de nuevo.';
}
