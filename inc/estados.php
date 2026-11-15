<?php
/**
 * Insignias de estado con los colores de las pantallas.
 *   echo insignia('proyecto', 'en_revision');   // <span class="estado estado-info">En revisión</span>
 */

const ESTADOS = [
    'proyecto' => [
        'borrador'    => ['Borrador', 'neutro'],
        'en_revision' => ['En revisión', 'info'],
        'devuelto'    => ['Devuelto para correcciones', 'advertencia'],
        'publicado'   => ['Publicado', 'exito'],
        'rechazado'   => ['Rechazado', 'peligro'],
        'en_curso'    => ['En curso', 'primario'],
        'finalizado'  => ['Finalizado', 'neutro-osc'],
        'cancelado'   => ['Cancelado', 'neutro'],
    ],
    'candidatura' => [
        'pendiente'   => ['Pendiente de evaluación', 'advertencia'],
        'en_revision' => ['En revisión', 'info'],
        'aprobada'    => ['Aprobada', 'exito'],
        'rechazada'   => ['Rechazada', 'peligro'],
        'cancelada'   => ['Cancelada', 'neutro'],
    ],
    'direccion' => [
        'pendiente' => ['Pendiente', 'advertencia'],
        'aprobada'  => ['Aprobada', 'exito'],
        'rechazada' => ['Rechazada', 'peligro'],
        'cancelada' => ['Cancelada', 'neutro'],
    ],
    'asignacion' => [
        'activa'     => ['Activa', 'exito'],
        'finalizada' => ['Finalizada', 'neutro-osc'],
        'cancelada'  => ['Cancelada', 'neutro'],
    ],
    'usuario' => [
        'activo'   => ['Activo', 'exito'],
        'inactivo' => ['Inactivo', 'neutro'],
    ],
];

function nombre_estado(string $tipo, string $estado): string
{
    return ESTADOS[$tipo][$estado][0] ?? ucfirst(str_replace('_', ' ', $estado));
}

function insignia(string $tipo, string $estado): string
{
    $clase = ESTADOS[$tipo][$estado][1] ?? 'neutro';
    return '<span class="estado estado-' . $clase . '">' . e(nombre_estado($tipo, $estado)) . '</span>';
}
