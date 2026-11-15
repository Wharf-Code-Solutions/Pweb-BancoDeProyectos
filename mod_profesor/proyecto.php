<?php
/** Pantalla R3 · Detalle de proyecto y solicitar dirección (profesor). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('profesor');
$pdo = conexion();
$num = $usuario['clave'];

$id = (string) ($_GET['id'] ?? '');
$p = obtener_proyecto($id);
$s = $pdo->prepare('SELECT c_solicitud_direccion, estado FROM solicitud_direccion WHERE num_empleado = ? AND c_proyecto = ? ORDER BY fecha DESC LIMIT 1');
$s->execute([$num, $id]);
$mia = $s->fetch() ?: null;
if (!$p || (!in_array($p['estado'], ['publicado', 'en_curso'], true) && !$mia)) {
    error_http(404);
}

$activos = direcciones_activas($num);
$s = $pdo->prepare('SELECT experiencia FROM profesor WHERE num_empleado = ?');
$s->execute([$num]);
$experiencia_perfil = (string) $s->fetchColumn();

/** Motivo por el que no puede solicitar, o null si puede. */
$motivo_bloqueo = match (true) {
    !in_array($p['estado'], ['publicado', 'en_curso'], true) => 'El proyecto ya no acepta solicitudes de dirección.',
    $mia && $mia['estado'] === 'pendiente'                   => 'Ya tienes una solicitud pendiente para este proyecto.',
    $mia && $mia['estado'] === 'aprobada'                    => 'Ya eres director de este proyecto.',
    (bool) $p['num_empleado_director']                       => 'El proyecto ya tiene director.',
    $activos >= MAX_DIRECCIONES                              => 'Ya diriges 3 proyectos activos.',
    default                                                  => null,
};

$d = ['experiencia' => $experiencia_perfil, 'justificacion' => ''];
$errores = [];
if (es_post()) {
    verificar_csrf();
    if ($motivo_bloqueo) {
        aviso('advertencia', $motivo_bloqueo);
        redirigir('mod_profesor/proyecto.php?id=' . urlencode($id));
    }
    $d['experiencia']   = trim(str_replace("\r\n", "\n", (string) ($_POST['experiencia'] ?? '')));
    $d['justificacion'] = trim(str_replace("\r\n", "\n", (string) ($_POST['justificacion'] ?? '')));
    foreach (['experiencia' => 'tu experiencia relacionada', 'justificacion' => 'por qué quieres dirigirlo'] as $k => $que) {
        if (mb_strlen($d[$k]) < 20) {
            $errores[$k] = "Describe $que (al menos 20 caracteres).";
        } elseif (mb_strlen($d[$k]) > 3000) {
            $errores[$k] = 'Máximo 3000 caracteres.';
        }
    }
    if (!$errores) {
        try {
            $pdo->beginTransaction();
            $folio = siguiente_folio('SDI');
            $pdo->prepare('INSERT INTO solicitud_direccion (c_solicitud_direccion, num_empleado, c_proyecto, experiencia, justificacion) VALUES (?, ?, ?, ?, ?)')
                ->execute([$folio, $num, $id, $d['experiencia'], $d['justificacion']]);
            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log((string) $ex);
            $errores['_general'] = mensaje_error_bd($ex);
        }
        if (!$errores) {
            registrar_auditoria('solicitar_direccion', 'proyecto', $id);
            notificar_administradores($usuario['nombre'] . ' solicitó dirigir «' . $p['titulo'] . '».');
            aviso('exito', 'Solicitud de dirección enviada. La administración la revisará.');
            redirigir('mod_profesor/solicitudes.php');
        }
    }
}

$titulo = $p['titulo'];
$menu_activo = 'mod_profesor/proyectos.php';
$migas = [['Panel', 'mod_profesor/index.php'], ['Proyectos disponibles', 'mod_profesor/proyectos.php'], [$p['titulo'], null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$fila = fn (string $k, string $v) => '<div class="kv-fila"><span class="k">' . e($k) . '</span><span class="v">' . e($v) . '</span></div>';
?>
<div class="encabezado-pagina">
    <div>
        <span class="etiqueta">Proyecto</span>
        <h1 class="titulo-izquierda"><?= e($p['titulo']) ?></h1>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
            <?= insignia('proyecto', $p['estado']) ?><span class="folio"><?= e($id) ?></span>
            <span class="small text-mute"><?= e($p['razon_social']) ?> · <?= e($p['sector']) ?></span>
        </div>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Información del proyecto</h2></div>
            <div class="card-body"><?= html_contenido_proyecto($p, requisitos_proyecto($id)) ?></div></div>
    </div>
    <div class="col-lg-5 d-grid gap-3 align-self-start">
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Dirección</h2></div>
            <div class="card-body">
                <?= $fila('Director actual', $p['director'] ?? 'Sin director') ?>
                <?= $fila('Alumnos asignados', (int) $p['alumnos_asignados'] . ($p['cupo_alumnos'] ? ' de ' . (int) $p['cupo_alumnos'] : '')) ?>
                <?= $fila('Modalidad', MODALIDADES[$p['modalidad']] ?? '—') ?>
                <?= $fila('Fechas', rango_fechas($p['fecha_inicio'], $p['fecha_fin'])) ?>
            </div></div>
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Solicitar dirección</h2></div>
            <div class="card-body">
                <?php $restan = MAX_DIRECCIONES - $activos; ?>
                <div class="mb-3"><?= html_cupo("Proyectos que diriges: $activos de " . MAX_DIRECCIONES, $activos, MAX_DIRECCIONES,
                    $restan > 0 ? ($restan === 1 ? 'Te queda 1' : "Te quedan $restan") : 'Sin lugares disponibles', 'bi-briefcase') ?></div>
                <?php if ($motivo_bloqueo): ?>
                    <?= html_accion_bloqueada('Solicitar dirección', 'bi-send-check', $motivo_bloqueo) ?>
                    <?php if ($mia): ?><p class="small mt-3 mb-0"><a href="<?= e(url('mod_profesor/solicitudes.php')) ?>">Ver mis solicitudes de dirección</a></p><?php endif; ?>
                <?php else: ?>
                    <form method="post" class="d-grid gap-3" novalidate>
                        <?= csrf_campo() ?>
                        <?= alerta_errores(isset($errores['_general']) ? $errores : []) ?>
                        <?= campo_area('experiencia', 'Experiencia relacionada con este proyecto', $d['experiencia'], ['requerido' => true, 'filas' => 4, 'error' => $errores['experiencia'] ?? null]) ?>
                        <?= campo_area('justificacion', 'Justificación', $d['justificacion'], ['requerido' => true, 'filas' => 3, 'error' => $errores['justificacion'] ?? null]) ?>
                        <div class="barra-acciones-form pt-0">
                            <a class="btn btn-terciario" href="<?= e(url('mod_profesor/proyectos.php')) ?>">Cancelar</a>
                            <button type="submit" class="btn btn-principal"><i class="bi bi-send-check" aria-hidden="true"></i>Solicitar dirección</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div></div>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
