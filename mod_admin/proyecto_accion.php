<?php
/**
 * Acciones del administrador sobre un proyecto (D7 y D8):
 * iniciar (publicado → en curso), finalizar (en curso → finalizado) y cancelar.
 * Solo POST con CSRF; vuelve a la página indicada en "volver".
 */
require_once __DIR__ . '/../inc/inicio.php';
requiere_rol('administrador');
if (!es_post()) {
    error_http(404);
}
verificar_csrf();
$pdo = conexion();

$id = (string) ($_POST['id'] ?? '');
$accion = (string) ($_POST['accion'] ?? '');
$volver = (string) ($_POST['volver'] ?? '');
$volver = preg_match('#^mod_admin/[a-z_]+\.php(\?[^\s]*)?$#', $volver) ? $volver : 'mod_admin/proyecto.php?id=' . urlencode($id);

$p = obtener_proyecto($id);
if (!$p) {
    error_http(404);
}

$reglas = [
    'iniciar'   => [['publicado'], 'en_curso'],
    'finalizar' => [['en_curso'], 'finalizado'],
    'cancelar'  => [['publicado', 'en_curso'], 'cancelado'],
];
if (!isset($reglas[$accion])) {
    error_http(404);
}
[$desde, $hacia] = $reglas[$accion];

if (!in_array($p['estado'], $desde, true)) {
    aviso('advertencia', 'El proyecto está «' . nombre_estado('proyecto', $p['estado']) . '»; esa acción ya no aplica.');
    redirigir($volver);
}
if ($accion === 'iniciar' && (!$p['num_empleado_director'] || (int) $p['alumnos_asignados'] < 1)) {
    aviso('advertencia', 'Para iniciar el proyecto necesita un director y al menos un alumno asignados.');
    redirigir($volver);
}

// Se toman los participantes antes de cerrar sus asignaciones, para avisarles.
$avisar = participantes_proyecto($id);
$solicitantes = $accion === 'iniciar' ? [] : array_diff(solicitantes_abiertos_proyecto($id), $avisar);

try {
    $pdo->beginTransaction();
    if ($accion === 'iniciar') {
        $pdo->prepare("UPDATE proyecto SET estado = 'en_curso' WHERE c_proyecto = ? AND estado = 'publicado'")->execute([$id]);
    } else {
        cerrar_proyecto($id, $hacia);
    }
    $pdo->commit();
} catch (PDOException $ex) {
    $pdo->rollBack();
    error_log((string) $ex);
    aviso('peligro', mensaje_error_bd($ex));
    redirigir($volver);
}

registrar_auditoria($accion, 'proyecto', $id);
$mensaje = match ($accion) {
    'iniciar'   => "El proyecto «{$p['titulo']}» inició.",
    'finalizar' => "El proyecto «{$p['titulo']}» finalizó. Gracias por tu participación.",
    'cancelar'  => "El proyecto «{$p['titulo']}» fue cancelado por la administración.",
};
foreach ($avisar as $c_usuario) {
    notificar($c_usuario, $mensaje);
}
foreach ($solicitantes as $c_usuario) {
    notificar($c_usuario, "Tu solicitud a «{$p['titulo']}» se canceló porque el proyecto "
        . ($accion === 'finalizar' ? 'finalizó.' : 'fue cancelado.') . ' Ya puedes usar ese lugar en otra solicitud.');
}
aviso('exito', match ($accion) {
    'iniciar'   => 'Proyecto iniciado: ahora está en curso.',
    'finalizar' => 'Proyecto finalizado. Las asignaciones activas quedaron finalizadas.',
    'cancelar'  => 'Proyecto cancelado. Se cerraron sus asignaciones y solicitudes abiertas.',
});
redirigir($volver);
