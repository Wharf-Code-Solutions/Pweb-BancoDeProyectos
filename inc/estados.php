<?php
/**
 * Insignias de estado. Cuatro grupos de color: proceso (en trámite),
 * activo (aprobado o vigente), atencion (alguien debe actuar) y cerrado.
 *   echo insignia('proyecto', 'en_revision');   // <span class="estado estado-proceso">En revisión</span>
 */

const ESTADOS = [
    'proyecto' => [
        'borrador'    => ['Borrador', 'proceso'],
        'en_revision' => ['En revisión', 'proceso'],
        'devuelto'    => ['Devuelto para correcciones', 'atencion'],
        'publicado'   => ['Publicado', 'activo'],
        'rechazado'   => ['Rechazado', 'cerrado'],
        'en_curso'    => ['En curso', 'activo'],
        'finalizado'  => ['Finalizado', 'cerrado'],
        'cancelado'   => ['Cancelado', 'cerrado'],
    ],
    'candidatura' => [
        'pendiente'   => ['Pendiente de evaluación', 'atencion'],
        'en_revision' => ['En revisión', 'proceso'],
        'aprobada'    => ['Aprobada', 'activo'],
        'rechazada'   => ['Rechazada', 'cerrado'],
        'cancelada'   => ['Cancelada', 'cerrado'],
    ],
    'direccion' => [
        'pendiente' => ['Pendiente', 'atencion'],
        'aprobada'  => ['Aprobada', 'activo'],
        'rechazada' => ['Rechazada', 'cerrado'],
        'cancelada' => ['Cancelada', 'cerrado'],
    ],
    'asignacion' => [
        'activa'     => ['Activa', 'activo'],
        'finalizada' => ['Finalizada', 'cerrado'],
        'cancelada'  => ['Cancelada', 'cerrado'],
    ],
    'usuario' => [
        'activo'   => ['Activo', 'activo'],
        'inactivo' => ['Inactivo', 'cerrado'],
    ],
];

function nombre_estado(string $tipo, string $estado): string
{
    return ESTADOS[$tipo][$estado][0] ?? ucfirst(str_replace('_', ' ', $estado));
}

function insignia(string $tipo, string $estado): string
{
    $clase = ESTADOS[$tipo][$estado][1] ?? 'cerrado';
    return '<span class="estado estado-' . $clase . '">' . e(nombre_estado($tipo, $estado)) . '</span>';
}
