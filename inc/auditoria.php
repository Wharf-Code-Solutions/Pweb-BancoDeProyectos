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
