<?php
/**
 * Avisos de una sola vez (flash) que se muestran en la siguiente página.
 *   aviso('exito', 'Proyecto enviado a revisión.');
 * Tipos: exito, info, advertencia, peligro.
 */

function aviso(string $tipo, string $mensaje): void
{
    $_SESSION['avisos'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Devuelve y borra los avisos pendientes. */
function tomar_avisos(): array
{
    $avisos = $_SESSION['avisos'] ?? [];
    unset($_SESSION['avisos']);
    return $avisos;
}

/** HTML de las alertas, con el estilo de las pantallas. */
function mostrar_avisos(): string
{
    $clases = [
        'exito'       => ['success', 'bi-check-circle-fill'],
        'info'        => ['info', 'bi-info-circle-fill'],
        'advertencia' => ['warning', 'bi-exclamation-triangle-fill'],
        'peligro'     => ['danger', 'bi-x-octagon-fill'],
    ];
    $html = '';
    foreach (tomar_avisos() as $a) {
        [$clase, $icono] = $clases[$a['tipo']] ?? $clases['info'];
        $html .= '<div class="alert alert-' . $clase . '" role="alert">'
               . '<i class="bi ' . $icono . '" aria-hidden="true"></i>'
               . '<div class="alert-cuerpo">' . e($a['mensaje']) . '</div></div>';
    }
    return $html;
}
