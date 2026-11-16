<?php
/**
 * Pantalla D11 · Solicitudes de dirección: lista de pendientes y decisión.
 * Aprobar crea la autorización y la asignación del director en una transacción.
 */
require_once __DIR__ . '/../inc/inicio.php';
$admin = requiere_rol('administrador');
$pdo = conexion();

$sql_solicitud = "SELECT sd.*, u.nombre, u.c_usuario AS c_usuario_profesor, pr.especialidad, pr.experiencia AS experiencia_general,
        p.titulo, p.estado AS estado_proyecto, e.razon_social, r.num_empleado_director, ud.nombre AS director,
        COALESCE(vc.proyectos_activos, 0) AS activos
    FROM solicitud_direccion sd
    JOIN profesor pr ON pr.num_empleado = sd.num_empleado JOIN usuario u ON u.c_usuario = pr.c_usuario
    JOIN proyecto p ON p.c_proyecto = sd.c_proyecto JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
    JOIN v_proyecto_resumen r ON r.c_proyecto = p.c_proyecto
    LEFT JOIN profesor pd ON pd.num_empleado = r.num_empleado_director LEFT JOIN usuario ud ON ud.c_usuario = pd.c_usuario
    LEFT JOIN v_carga_profesor vc ON vc.num_empleado = sd.num_empleado";

$pendientes = $pdo->query("$sql_solicitud WHERE sd.estado = 'pendiente' ORDER BY sd.fecha")->fetchAll();
$id = (string) ($_GET['id'] ?? ($pendientes[0]['c_solicitud_direccion'] ?? ''));
$sol = null;
if ($id !== '') {
    $s = $pdo->prepare("$sql_solicitud WHERE sd.c_solicitud_direccion = ?");
    $s->execute([$id]);
    $sol = $s->fetch() ?: null;
}

/** Motivo por el que no se puede aprobar, o null. */
$bloqueo = fn (array $x) => match (true) {
    !in_array($x['estado_proyecto'], ['publicado', 'en_curso'], true) => 'El proyecto ya no admite asignaciones.',
    (bool) $x['num_empleado_director']                                 => 'El proyecto ya tiene director.',
    (int) $x['activos'] >= MAX_DIRECCIONES                             => 'El profesor ya dirige 3 proyectos activos.',
    default                                                            => null,
};

$justificacion = '';
$errores = [];
$otras = [];
if (es_post() && $sol) {
    verificar_csrf();
    $decision = ($_POST['decision'] ?? '') === 'aprobar' ? 'aprobar' : 'rechazar';
    $justificacion = trim(str_replace("\r\n", "\n", (string) ($_POST['justificacion'] ?? '')));
    if ($sol['estado'] !== 'pendiente') {
        aviso('advertencia', 'Esta solicitud ya fue decidida.');
        redirigir('mod_admin/direccion.php');
    }
    if (mb_strlen($justificacion) < 10) {
        $errores['justificacion'] = 'Escribe la justificación (al menos 10 caracteres).';
    } elseif ($decision === 'aprobar' && ($motivo = $bloqueo($sol))) {
        $errores['_general'] = $motivo;
    }
    if (!$errores) {
        try {
            $pdo->beginTransaction();
            $s = $pdo->prepare("UPDATE solicitud_direccion SET estado = ? WHERE c_solicitud_direccion = ? AND estado = 'pendiente'");
            $s->execute([$decision === 'aprobar' ? 'aprobada' : 'rechazada', $id]);
            if ($s->rowCount() !== 1) {
                throw new RuntimeException('ya_decidida');
            }
            $pdo->prepare('INSERT INTO autorizacion (c_autorizacion, num_empleado_admin, c_solicitud_direccion, decision, justificacion) VALUES (?, ?, ?, ?, ?)')
                ->execute([siguiente_folio('AUT'), $admin['clave'], $id, $decision === 'aprobar' ? 'aprobada' : 'rechazada', $justificacion]);
            if ($decision === 'aprobar') {
                // El trigger valida el límite de 3 (RN-05) y un solo director por proyecto.
                $pdo->prepare('INSERT INTO asignacion_profesor (c_asignacion_profesor, c_solicitud_direccion) VALUES (?, ?)')->execute([siguiente_folio('APR'), $id]);
                // Las demás solicitudes pendientes para este proyecto ya no pueden prosperar: se rechazan con su autorización.
                $s = $pdo->prepare("SELECT sd.c_solicitud_direccion, pr.c_usuario FROM solicitud_direccion sd JOIN profesor pr ON pr.num_empleado = sd.num_empleado
                    WHERE sd.c_proyecto = ? AND sd.estado = 'pendiente' AND sd.c_solicitud_direccion <> ? FOR UPDATE");
                $s->execute([$sol['c_proyecto'], $id]);
                $otras = $s->fetchAll();
                foreach ($otras as $o) {
                    $pdo->prepare("UPDATE solicitud_direccion SET estado = 'rechazada' WHERE c_solicitud_direccion = ?")->execute([$o['c_solicitud_direccion']]);
                    $pdo->prepare("INSERT INTO autorizacion (c_autorizacion, num_empleado_admin, c_solicitud_direccion, decision, justificacion) VALUES (?, ?, ?, 'rechazada', ?)")
                        ->execute([siguiente_folio('AUT'), $admin['clave'], $o['c_solicitud_direccion'], 'El proyecto ya tiene un director asignado.']);
                }
            }
            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log((string) $ex);
            $errores['_general'] = mensaje_error_bd($ex);
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            aviso('advertencia', 'Otro administrador ya decidió esta solicitud.');
            redirigir('mod_admin/direccion.php');
        }
        if (!$errores) {
            registrar_auditoria($decision === 'aprobar' ? 'asignar' : 'rechazar', 'solicitud_direccion', $id);
            if ($decision === 'aprobar') {
                notificar($sol['c_usuario_profesor'], 'Tu solicitud para dirigir «' . $sol['titulo'] . '» fue aprobada. Ya eres el director.');
                $s = $pdo->prepare("SELECT e.c_usuario FROM proyecto p JOIN empresa e ON e.rfc_empresa = p.rfc_empresa WHERE p.c_proyecto = ?");
                $s->execute([$sol['c_proyecto']]);
                notificar((string) $s->fetchColumn(), $sol['nombre'] . ' será el director del proyecto «' . $sol['titulo'] . '».');
                foreach ($otras as $o) {
                    registrar_auditoria('rechazar', 'solicitud_direccion', $o['c_solicitud_direccion']);
                    notificar($o['c_usuario'], 'Tu solicitud para dirigir «' . $sol['titulo'] . '» fue rechazada: el proyecto ya tiene director.');
                }
                aviso('exito', 'Solicitud aprobada: ' . $sol['nombre'] . ' quedó como director.');
            } else {
                notificar($sol['c_usuario_profesor'], 'Tu solicitud para dirigir «' . $sol['titulo'] . '» fue rechazada.');
                aviso('exito', 'Solicitud rechazada.');
            }
            redirigir('mod_admin/direccion.php');
        }
    }
}

$titulo = 'Solicitudes de dirección';
$menu_activo = 'mod_admin/direccion.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Revisión', null], ['Solicitudes de dirección', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Administración</span><h1 class="titulo-izquierda">Solicitudes de dirección</h1></div>
</div>
<?php if (!$pendientes && !$sol): ?>
    <div class="card"><div class="card-body"><div class="vacio"><div class="vacio-icono"><i class="bi bi-person-check" aria-hidden="true"></i></div>
        <h2>No hay solicitudes de dirección pendientes</h2><p>Cuando un profesor solicite dirigir un proyecto aparecerá aquí.</p></div></div></div>
<?php else: ?>
<div class="row g-4">
    <div class="col-lg-5">
        <?php if (!$pendientes): ?>
            <p class="text-mute">No quedan solicitudes pendientes.</p>
        <?php else: ?>
            <div class="list-group shadow-sm">
                <?php foreach ($pendientes as $x): $sel = $sol && $x['c_solicitud_direccion'] === $sol['c_solicitud_direccion']; ?>
                    <a href="<?= e(url('mod_admin/direccion.php?id=' . urlencode($x['c_solicitud_direccion']))) ?>" class="list-group-item d-grid gap-1"
                        <?= $sel ? 'aria-current="true" style="background:var(--primario-claro-bg);border-left:4px solid var(--navy)"' : '' ?>>
                        <strong><?= e($x['nombre']) ?></strong><span class="small"><?= e($x['titulo']) ?></span>
                        <span class="d-flex justify-content-between small text-mute"><span><?= e(fecha_corta($x['fecha'])) ?></span><span>Carga: <?= (int) $x['activos'] ?> de <?= MAX_DIRECCIONES ?></span></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($sol): $motivo = $bloqueo($sol); $act = (int) $sol['activos']; ?>
    <div class="col-lg-7 d-grid gap-3 align-self-start">
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Profesor</h2></div>
            <div class="card-body">
                <div class="d-flex gap-3 align-items-center mb-3"><span class="avatar" style="width:52px;height:52px"><?= e(iniciales($sol['nombre'])) ?></span>
                    <div><strong class="d-block fs-5 text-navy"><?= e($sol['nombre']) ?></strong><span class="folio"><?= e($sol['num_empleado']) ?></span>
                        <span class="small text-mute">· <?= e($sol['especialidad']) ?></span></div></div>
                <?php if ($sol['experiencia_general']): ?><p class="small mb-3"><strong>Experiencia general:</strong> <?= nl2br(e($sol['experiencia_general'])) ?></p><?php endif; ?>
                <?= html_cupo("Proyectos que dirige: $act de " . MAX_DIRECCIONES, $act, MAX_DIRECCIONES,
                    $act >= MAX_DIRECCIONES ? 'Sin lugares' : 'Le queda' . (MAX_DIRECCIONES - $act === 1 ? ' 1' : 'n ' . (MAX_DIRECCIONES - $act)), 'bi-briefcase') ?>
            </div></div>
        <div class="card"><div class="card-header d-flex flex-wrap gap-2 align-items-center"><h2 class="h3 mb-0">Solicitud</h2><span class="folio"><?= e($sol['c_solicitud_direccion']) ?></span><?= insignia('direccion', $sol['estado']) ?></div>
            <div class="card-body"><div class="bloque-texto">
                <h3>Proyecto</h3><p><a href="<?= e(url('mod_admin/proyecto.php?id=' . urlencode($sol['c_proyecto']))) ?>"><?= e($sol['titulo']) ?></a> · <?= e($sol['razon_social']) ?> ·
                    <strong>Director actual:</strong> <?= e($sol['director'] ?? 'sin director') ?></p>
                <h3>Experiencia relacionada</h3><p><?= nl2br(e($sol['experiencia'])) ?></p>
                <h3>Justificación</h3><p class="mb-0"><?= nl2br(e($sol['justificacion'])) ?></p>
            </div></div></div>
        <?php if ($sol['estado'] === 'pendiente'): ?>
        <div class="panel-decision">
            <div class="cab">Decisión</div>
            <form method="post" action="<?= e(url('mod_admin/direccion.php?id=' . urlencode($sol['c_solicitud_direccion']))) ?>" class="p-3 d-grid gap-3" novalidate>
                <?= csrf_campo() ?>
                <?= alerta_errores(isset($errores['_general']) ? $errores : []) ?>
                <?= campo_area('justificacion', 'Justificación', $justificacion, ['placeholder' => 'Motivo de la decisión (mínimo 10 caracteres)', 'minlength' => 10, 'maxlength' => 3000, 'requerido' => true, 'filas' => 3, 'error' => $errores['justificacion'] ?? null, 'ayuda' => 'Obligatoria. La verá el profesor.']) ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($motivo): ?>
                        <div class="accion-bloqueada"><button type="button" class="btn btn-aprobar" disabled aria-describedby="bd"><i class="bi bi-check-circle" aria-hidden="true"></i>Aprobar y asignar</button>
                            <span class="motivo" id="bd"><i class="bi bi-info-circle" aria-hidden="true"></i><?= e($motivo) ?></span></div>
                    <?php else: ?>
                        <button type="submit" name="decision" value="aprobar" class="btn btn-aprobar" data-confirmar="¿Aprobar a <?= e($sol['nombre']) ?> como director de «<?= e($sol['titulo']) ?>»?"><i class="bi bi-check-circle" aria-hidden="true"></i>Aprobar y asignar</button>
                    <?php endif; ?>
                    <button type="submit" name="decision" value="rechazar" class="btn btn-rechazar" data-confirmar="¿Rechazar la solicitud de <?= e($sol['nombre']) ?>?"><i class="bi bi-x-circle" aria-hidden="true"></i>Rechazar</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
