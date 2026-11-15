<?php
/** Pantalla A4 · Detalle de proyecto (alumno) y postulación. */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('alumno');
$pdo = conexion();
$matricula = $usuario['clave'];

$id = (string) ($_GET['id'] ?? '');
$p = obtener_proyecto($id);
// El alumno ve proyectos disponibles, o cualquiera en el que haya participado.
$s = $pdo->prepare('SELECT c_candidatura, estado FROM solicitud_candidatura WHERE matricula = ? AND c_proyecto = ? ORDER BY fecha DESC LIMIT 1');
$s->execute([$matricula, $id]);
$mia = $s->fetch() ?: null;
if (!$p || (!in_array($p['estado'], ['publicado', 'en_curso'], true) && !$mia)) {
    error_http(404);
}
$activa = $mia && in_array($mia['estado'], ['pendiente', 'en_revision', 'aprobada'], true);
$vigentes = candidaturas_vigentes($matricula);
$disponible = in_array($p['estado'], ['publicado', 'en_curso'], true);
$cupo_lleno = $p['cupo_alumnos'] && (int) $p['alumnos_asignados'] >= (int) $p['cupo_alumnos'];

if (es_post()) {
    verificar_csrf();
    // Las mismas reglas que la base (RN-03, RN-09, RN-11), con mensajes claros.
    $motivo = match (true) {
        !$disponible                     => 'El proyecto ya no acepta candidaturas.',
        $activa                          => 'Ya tienes una solicitud vigente para este proyecto.',
        $vigentes >= MAX_CANDIDATURAS    => 'Ya tienes 3 candidaturas vigentes. Cancela una o espera su resolución.',
        default                          => null,
    };
    if ($motivo) {
        aviso('advertencia', $motivo);
        redirigir('mod_alumno/proyecto.php?id=' . urlencode($id));
    }
    try {
        $pdo->beginTransaction();
        $folio = siguiente_folio('CAN');
        $pdo->prepare('INSERT INTO solicitud_candidatura (c_candidatura, matricula, c_proyecto) VALUES (?, ?, ?)')->execute([$folio, $matricula, $id]);
        $pdo->commit();
    } catch (PDOException $ex) {
        $pdo->rollBack();
        error_log((string) $ex);
        aviso('peligro', mensaje_error_bd($ex));
        redirigir('mod_alumno/proyecto.php?id=' . urlencode($id));
    }
    registrar_auditoria('postular', 'proyecto', $id);
    notificar($p['c_usuario_empresa'], $usuario['nombre'] . ' se postuló a «' . $p['titulo'] . '».');
    aviso('exito', 'Te postulaste al proyecto. La empresa revisará tu perfil.');
    redirigir('mod_alumno/solicitudes.php');
}

$requisitos = requisitos_proyecto($id);
$c = coincidencia($requisitos, perfil_alumno($matricula));

$titulo = $p['titulo'];
$menu_activo = 'mod_alumno/proyectos.php';
$migas = [['Panel', 'mod_alumno/index.php'], ['Proyectos disponibles', 'mod_alumno/proyectos.php'], [$p['titulo'], null]];
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
    <div class="col-lg-8 d-grid gap-3 align-self-start">
        <?php if ($requisitos): ?>
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h3 mb-0">Tus habilidades frente a lo que pide el proyecto</h2><?= html_coincidencia($c) ?>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3"><?= $c['chips'] ?></div>
                <div class="d-flex flex-wrap gap-3 small text-mute">
                    <span><i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i> Cumples</span>
                    <span><i class="bi bi-dash-circle-fill text-warning" aria-hidden="true"></i> Nivel menor</span>
                    <span><i class="bi bi-circle" aria-hidden="true"></i> No la tienes</span>
                    <span><i class="bi bi-asterisk text-primary" aria-hidden="true"></i> Obligatoria</span>
                </div>
                <?php if ($c['cumplidas'] < $c['obligatorias']): ?>
                    <p class="small mb-0 mt-3"><a href="<?= e(url('mod_alumno/habilidades.php')) ?>">Actualiza tus habilidades</a> si ya dominas lo que pide el proyecto.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Información del proyecto</h2></div>
            <div class="card-body"><?= html_contenido_proyecto($p, []) ?></div></div>
    </div>

    <div class="col-lg-4 d-grid gap-3 align-self-start order-first order-lg-last">
        <div class="card">
            <div class="card-header"><h2 class="h3 mb-0">Postulación</h2></div>
            <div class="card-body"><div class="d-grid gap-3">
                <?php if ($activa): ?>
                    <span class="ya-postulado"><i class="bi bi-check2" aria-hidden="true"></i>Ya te postulaste · <?= e(nombre_estado('candidatura', $mia['estado'])) ?></span>
                    <a class="btn btn-secundario" href="<?= e(url('mod_alumno/solicitudes.php')) ?>">Ver mis solicitudes</a>
                <?php else:
                    $restan = MAX_CANDIDATURAS - $vigentes;
                    echo html_cupo("Candidaturas vigentes: $vigentes de " . MAX_CANDIDATURAS, $vigentes, MAX_CANDIDATURAS,
                        $restan > 0 ? 'Usarás 1 de tus ' . MAX_CANDIDATURAS . ' candidaturas' : 'Sin lugares disponibles'); ?>
                    <div class="acciones-movil-completo">
                        <?php if (!$disponible): ?>
                            <?= html_accion_bloqueada('Postularme', 'bi-send', 'El proyecto ya no acepta candidaturas.') ?>
                        <?php elseif ($restan <= 0): ?>
                            <?= html_accion_bloqueada('Postularme', 'bi-send', 'Ya tienes 3 candidaturas vigentes. Cancela una o espera su resolución.') ?>
                        <?php else: ?>
                            <button type="button" class="btn btn-principal" data-bs-toggle="modal" data-bs-target="#modal-postular"><i class="bi bi-send" aria-hidden="true"></i>Postularme</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($cupo_lleno && $disponible && $restan > 0): ?>
                        <p class="small text-mute mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> El cupo ya está completo; tu solicitud quedará en espera por si se libera un lugar.</p>
                    <?php endif; ?>
                    <?php if ($mia): ?><p class="small text-mute mb-0">Tu solicitud anterior quedó «<?= e(nombre_estado('candidatura', $mia['estado'])) ?>». Puedes volver a postularte.</p><?php endif; ?>
                <?php endif; ?>
            </div></div>
        </div>
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Datos del proyecto</h2></div>
            <div class="card-body">
                <?= $fila('Empresa', $p['razon_social']) ?><?= $fila('Sector', $p['sector']) ?>
                <?= $fila('Modalidad', MODALIDADES[$p['modalidad']] ?? '—') ?><?= $fila('Duración', $p['duracion_estimada'] . ' semanas') ?>
                <?= $fila('Fechas', rango_fechas($p['fecha_inicio'], $p['fecha_fin'])) ?>
                <?= $fila('Cupo', $p['cupo_alumnos'] ? (int) $p['alumnos_asignados'] . ' de ' . (int) $p['cupo_alumnos'] . ' asignados' : 'Sin límite de cupo') ?>
                <?= $fila('Director', $p['director'] ?? 'Sin director') ?>
            </div></div>
    </div>
</div>

<?php if (!$activa && $disponible && $vigentes < MAX_CANDIDATURAS): $despues = $vigentes + 1; ?>
<div class="modal fade" id="modal-postular" tabindex="-1" aria-labelledby="modal-postular-t" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post"><?= csrf_campo() ?>
            <div class="modal-header">
                <h2 class="modal-title" id="modal-postular-t"><i class="bi bi-send" aria-hidden="true"></i> Postularme a este proyecto</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong><?= e($p['titulo']) ?></strong><br><span class="small text-mute"><?= e($p['razon_social']) ?></span></p>
                <?= html_cupo('Usarás 1 de tus ' . MAX_CANDIDATURAS . ' candidaturas', $despues, MAX_CANDIDATURAS, 'Te quedarán ' . (MAX_CANDIDATURAS - $despues)) ?>
                <?php if ($c['cumplidas'] < $c['obligatorias']): ?>
                    <div class="alert alert-warning mt-3 mb-0" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <div class="alert-cuerpo">No cumples todas las habilidades obligatorias. Puedes postularte, pero la empresa lo tomará en cuenta.</div></div>
                <?php endif; ?>
                <p class="small text-mute mt-3 mb-0">La empresa revisará tu perfil y tus habilidades. Puedes cancelar la solicitud mientras esté pendiente o en revisión.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-terciario" data-bs-dismiss="modal">Volver</button>
                <button type="submit" class="btn btn-principal"><i class="bi bi-send" aria-hidden="true"></i>Postularme</button>
            </div>
        </form>
    </div></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
