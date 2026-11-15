<?php
/**
 * Historial de auditoría (lo escribe la app porque un trigger no conoce
 * al usuario de la aplicación).
 *
 *   registrar_auditoria('aprobar', 'proyecto', 'PRY-00000012', 'exito');
 *
 * El folio HIS se reserva fuera de cualquier transacción abierta, como pide
 * el script SQL, para que un ROLLBACK de la operación no borre el registro.
 * Si hay una transacción abierta, el registro se guarda al terminar la
 * petición, después de que la operación se confirme o se revierta.
 */

/** Texto legible de un registro del historial (para el panel y la consulta). */
function describir_operacion(array $h): string
{
    $verbo = [
        'iniciar_sesion' => 'Inició sesión', 'cerrar_sesion' => 'Cerró sesión', 'acceso' => 'Acceso denegado a',
        'registrar' => 'Registró', 'crear' => 'Creó', 'editar' => 'Editó', 'activar' => 'Activó',
        'desactivar' => 'Desactivó', 'eliminar' => 'Eliminó', 'actualizar_datos' => 'Actualizó los datos de',
        'cambiar_contrasena' => 'Cambió la contraseña de', 'aprobar' => 'Aprobó', 'rechazar' => 'Rechazó',
        'devolver' => 'Devolvió', 'asignar' => 'Asignó', 'finalizar' => 'Finalizó', 'cancelar' => 'Canceló',
        'enviar_revision' => 'Envió a revisión', 'postular' => 'Se postuló a', 'evaluar' => 'Evaluó',
        'solicitar_direccion' => 'Solicitó dirigir',
    ][$h['operacion']] ?? ucfirst(str_replace('_', ' ', $h['operacion']));

    if (in_array($h['operacion'], ['iniciar_sesion', 'cerrar_sesion'], true)) {
        return $verbo;
    }
    $entidad = str_replace('_', ' ', $h['entidad_afectada']);
    if ($h['operacion'] === 'acceso') {
        return $verbo . ' ' . $h['registro_afectado'];
    }
    $texto = $verbo . ' ' . $entidad . ' ' . $h['registro_afectado'];
    return $h['resultado'] === 'error' ? $texto . ' (falló)' : $texto;
}

function registrar_auditoria(string $operacion, string $entidad, string $registro, string $resultado = 'exito', ?string $c_usuario = null): void
{
    $c_usuario ??= usuario_actual()['c_usuario'] ?? null;
    if ($c_usuario === null) {
        return; // sin usuario identificado no se puede registrar (la FK lo exige)
    }

    $guardar = function () use ($operacion, $entidad, $registro, $resultado, $c_usuario): void {
        try {
            $pdo = conexion();
            $folio = siguiente_folio('HIS');
            $pdo->prepare('INSERT INTO historial_auditoria
                    (c_historial, c_usuario, operacion, entidad_afectada, registro_afectado, resultado)
                    VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$folio, $c_usuario, mb_substr($operacion, 0, 50), mb_substr($entidad, 0, 40),
                           mb_substr($registro, 0, 100), $resultado]);
        } catch (Throwable $ex) {
            error_log('Auditoría no registrada: ' . $ex->getMessage());
        }
    };

    if (conexion()->inTransaction()) {
        register_shutdown_function($guardar);
    } else {
        $guardar();
    }
}
