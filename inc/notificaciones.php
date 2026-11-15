<?php
/**
 * Avisos a los usuarios (tabla notificacion).
 *
 * Igual que el historial, el folio NTF se reserva fuera de la transacción
 * de la operación: llama a notificar() DESPUÉS del commit, para que solo se
 * avise de cambios que sí se guardaron.
 */

function notificar(string $c_usuario, string $mensaje): void
{
    $pdo = conexion();
    if ($pdo->inTransaction()) {
        throw new LogicException('notificar() se llama después de confirmar la transacción.');
    }
    $folio = siguiente_folio('NTF');
    $pdo->prepare('INSERT INTO notificacion (c_notificacion, c_usuario, mensaje) VALUES (?, ?, ?)')
        ->execute([$folio, $c_usuario, mb_substr($mensaje, 0, 500)]);
}

/** Avisa a todos los administradores activos. */
function notificar_administradores(string $mensaje): void
{
    $ids = conexion()->query("SELECT c_usuario FROM usuario WHERE rol = 'administrador' AND estado = 'activo'")
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        notificar($id, $mensaje);
    }
}

/** Ícono según el contenido del mensaje (la tabla no guarda un tipo). */
function icono_notificacion(string $mensaje): string
{
    $m = mb_strtolower($mensaje);
    return match (true) {
        str_contains($m, 'devuel')                                  => 'bi-arrow-return-left',
        str_contains($m, 'rechaz')                                  => 'bi-x-circle',
        str_contains($m, 'postul') || str_contains($m, 'candidat')  => 'bi-person-plus',
        str_contains($m, 'direcci')                                 => 'bi-person-check',
        str_contains($m, 'asign')                                   => 'bi-diagram-3',
        str_contains($m, 'aprob') || str_contains($m, 'publicad')   => 'bi-check-circle',
        str_contains($m, 'revisi')                                  => 'bi-send',
        str_contains($m, 'bienvenid') || str_contains($m, 'registr') => 'bi-building-check',
        default                                                     => 'bi-bell',
    };
}

/** Fecha relativa corta: "hace 5 min", "hace 2 h", "ayer, 18:42", "10 nov 2026". */
function fecha_relativa(string $fecha): string
{
    $t = strtotime($fecha);
    $dif = time() - $t;
    if ($dif < 60) {
        return 'hace un momento';
    }
    if ($dif < 3600) {
        return 'hace ' . intdiv($dif, 60) . ' min';
    }
    if (date('Y-m-d', $t) === date('Y-m-d')) {
        return 'hace ' . intdiv($dif, 3600) . ' h';
    }
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) {
        return 'ayer, ' . date('H:i', $t);
    }
    return fecha_corta($fecha);
}

function fecha_corta(string $fecha): string
{
    static $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $t = strtotime($fecha);
    return date('j', $t) . ' ' . $meses[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}
