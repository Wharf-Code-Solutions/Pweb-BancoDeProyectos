<?php
/** Pantalla A5 · Mis solicitudes (alumno): vigentes e historial, con cancelación. */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('alumno');
$pdo = conexion();
$matricula = $usuario['clave'];

if (es_post()) {
    verificar_csrf();
    $id = (string) ($_POST['c_candidatura'] ?? '');
    $motivo = mb_substr(limpiar($_POST['motivo'] ?? ''), 0, 500);
    // Solo se cancela una solicitud propia que siga pendiente o en revisión.
    $s = $pdo->prepare("SELECT sc.c_candidatura, p.titulo, e.c_usuario AS empresa
        FROM solicitud_candidatura sc JOIN proyecto p ON p.c_proyecto = sc.c_proyecto JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
        WHERE sc.c_candidatura = ? AND sc.matricula = ? AND sc.estado IN ('pendiente', 'en_revision')");
    $s->execute([$id, $matricula]);
    if ($c = $s->fetch()) {
        $pdo->prepare("UPDATE solicitud_candidatura SET estado = 'cancelada', observaciones = ? WHERE c_candidatura = ?")
            ->execute([$motivo !== '' ? 'Cancelada por el alumno: ' . $motivo : 'Cancelada por el alumno.', $id]);
        registrar_auditoria('cancelar', 'solicitud_candidatura', $id);
        notificar($c['empresa'], $usuario['nombre'] . ' canceló su solicitud a «' . $c['titulo'] . '».');
        aviso('exito', 'Solicitud cancelada. Liberaste un lugar de tus 3 candidaturas.');
    } else {
        aviso('advertencia', 'Esa solicitud ya no se puede cancelar.');
    }
    redirigir('mod_alumno/solicitudes.php');
}

$s = $pdo->prepare("SELECT sc.*, p.titulo, p.estado AS estado_proyecto, e.razon_social,
        ev.fecha AS fecha_evaluacion, ev.preseleccionado,
        a.decision, a.fecha AS fecha_decision, a.justificacion, ua.nombre AS administrador,
        aa.estado AS estado_asignacion,
        (v.c_candidatura IS NOT NULL) AS vigente
    FROM solicitud_candidatura sc
    JOIN proyecto p ON p.c_proyecto = sc.c_proyecto
    JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
    LEFT JOIN evaluacion_candidato ev ON ev.c_candidatura = sc.c_candidatura
    LEFT JOIN autorizacion a ON a.c_candidatura = sc.c_candidatura
    LEFT JOIN administrador ad ON ad.num_empleado = a.num_empleado_admin
    LEFT JOIN usuario ua ON ua.c_usuario = ad.c_usuario
    LEFT JOIN asignacion_alumno aa ON aa.c_candidatura = sc.c_candidatura
    LEFT JOIN v_candidatura_vigente v ON v.c_candidatura = sc.c_candidatura
    WHERE sc.matricula = ? ORDER BY sc.fecha DESC");
$s->execute([$matricula]);
$todas = $s->fetchAll();
$vigentes_lista = array_values(array_filter($todas, fn ($c) => $c['vigente']));
$historial = array_values(array_filter($todas, fn ($c) => !$c['vigente']));
$pestana = ($_GET['tab'] ?? '') === 'historial' ? 'historial' : 'vigentes';
$lista = $pestana === 'vigentes' ? $vigentes_lista : $historial;
$vigentes = count($vigentes_lista);

$titulo = 'Mis solicitudes';
$menu_activo = 'mod_alumno/solicitudes.php';
$migas = [['Panel', 'mod_alumno/index.php'], ['Mis solicitudes', null]];
require __DIR__ . '/../menumain/app_encabezado.php';

/** Línea de tiempo de una candidatura. */
$linea = function (array $c): string {
    $ev = [['bi-send', '', 'Solicitud enviada', $c['fecha'], null, null]];
    if ($c['fecha_evaluacion']) {
        $ev[] = ['bi-clipboard-check', '', 'Evaluada por la empresa' . ($c['preseleccionado'] ? ' · Preseleccionada' : ''), $c['fecha_evaluacion'], null, null];
    }
    if ($c['decision']) {
        [$icono, $clase, $texto] = $c['decision'] === 'aprobada' ? ['bi-check-lg', 'exito', 'Aprobada por el Tec'] : ['bi-x-lg', 'peligro', 'Rechazada por el Tec'];
        $ev[] = [$icono, $clase, $texto, $c['fecha_decision'], $c['administrador'], $c['justificacion']];
    }
    if ($c['estado'] === 'cancelada') {
        $ev[] = ['bi-x-circle', '', $c['observaciones'] ?: 'Solicitud cancelada', null, null, null];
    }
    $html = '<ol class="linea-tiempo">';
    foreach ($ev as [$icono, $clase, $texto, $fecha, $quien, $just]) {
        $html .= '<li><span class="hito ' . $clase . '"><i class="bi ' . $icono . '" aria-hidden="true"></i></span><div class="evento">' . e($texto) . '</div>'
               . ($fecha ? '<div class="cuando">' . e(fecha_hora($fecha)) . ($quien ? ' · ' . e($quien) : '') . '</div>' : '')
               . ($just ? '<div class="justificacion ' . $clase . '"><span class="quien">Justificación</span>' . nl2br(e($just)) . '</div>' : '') . '</li>';
    }
    return $html . '</ol>';
};
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Alumno</span><h1 class="titulo-izquierda">Mis solicitudes</h1></div>
    <div class="acciones"><a class="btn btn-principal" href="<?= e(url('mod_alumno/proyectos.php')) ?>"><i class="bi bi-search" aria-hidden="true"></i>Buscar proyectos</a></div>
</div>
<div class="mb-3"><?= html_cupo("Candidaturas vigentes: $vigentes de " . MAX_CANDIDATURAS, $vigentes, MAX_CANDIDATURAS,
    $vigentes < MAX_CANDIDATURAS ? 'Te ' . (MAX_CANDIDATURAS - $vigentes === 1 ? 'queda 1' : 'quedan ' . (MAX_CANDIDATURAS - $vigentes)) : 'Sin lugares disponibles') ?></div>

<ul class="nav nav-tabs mb-0">
    <li class="nav-item"><a class="nav-link<?= $pestana === 'vigentes' ? ' active" aria-current="page' : '' ?>" href="<?= e(url('mod_alumno/solicitudes.php')) ?>">Vigentes
        <?php if ($vigentes): ?><span class="badge rounded-pill text-bg-primary"><?= $vigentes ?></span><?php endif; ?></a></li>
    <li class="nav-item"><a class="nav-link<?= $pestana === 'historial' ? ' active" aria-current="page' : '' ?>" href="<?= e(url('mod_alumno/solicitudes.php?tab=historial')) ?>">Historial
        <?php if ($historial): ?><span class="badge rounded-pill text-bg-primary"><?= count($historial) ?></span><?php endif; ?></a></li>
</ul>
<div class="bg-white border border-top-0 p-3 d-grid gap-3" style="border-radius:0 0 8px 8px">
    <?php if (!$lista): ?>
        <div class="vacio">
            <div class="vacio-icono"><i class="bi bi-send" aria-hidden="true"></i></div>
            <h3><?= $pestana === 'vigentes' ? 'No tienes solicitudes vigentes' : 'Tu historial está vacío' ?></h3>
            <p><?= $pestana === 'vigentes' ? 'Busca un proyecto que te interese y postúlate. Puedes tener hasta 3 a la vez.' : 'Aquí verás las solicitudes rechazadas, canceladas o cerradas.' ?></p>
        </div>
    <?php endif; ?>
    <?php foreach ($lista as $c): $cancelable = in_array($c['estado'], ['pendiente', 'en_revision'], true); ?>
        <div class="card"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-2">
                <div><h3 class="h4 mb-1"><?= e($c['titulo']) ?></h3>
                    <span class="small text-mute"><?= e($c['razon_social']) ?> · enviada el <?= e(fecha_corta($c['fecha'])) ?></span> <span class="folio"><?= e($c['c_candidatura']) ?></span></div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php if ($c['preseleccionado']): ?><span class="etiqueta-dato"><i class="bi bi-star" aria-hidden="true"></i>Preseleccionado por la empresa</span><?php endif; ?>
                    <?php if ($c['estado_asignacion']): ?><?= insignia('asignacion', $c['estado_asignacion']) ?><?php endif; ?>
                    <?= insignia('candidatura', $c['estado']) ?>
                </div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-md-8"><?= $linea($c) ?></div>
                <div class="col-md-4 d-flex gap-2 justify-content-end flex-wrap">
                    <a class="btn btn-secundario btn-sm" href="<?= e(url('mod_alumno/proyecto.php?id=' . urlencode($c['c_proyecto']))) ?>">Ver proyecto</a>
                    <?php if ($cancelable): ?>
                        <button type="button" class="btn btn-rechazar-contorno btn-sm" data-bs-toggle="modal" data-bs-target="#modal-cancelar"
                            data-id="<?= e($c['c_candidatura']) ?>" data-titulo="<?= e($c['titulo']) ?>"><i class="bi bi-x-circle" aria-hidden="true"></i>Cancelar solicitud</button>
                    <?php endif; ?>
                </div>
            </div>
        </div></div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="modal-cancelar" tabindex="-1" aria-labelledby="modal-cancelar-t" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post"><?= csrf_campo() ?><input type="hidden" name="c_candidatura" value="">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-cancelar-t"><i class="bi bi-x-circle" aria-hidden="true"></i> Cancelar solicitud</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>Cancelarás tu solicitud a <strong class="titulo-cancelar"></strong>. Liberarás un lugar de tus 3 candidaturas.</p>
                <?= campo_area('motivo', 'Motivo', '', ['opcional' => true, 'filas' => 3, 'maxlength' => 400]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-terciario" data-bs-dismiss="modal">Volver</button>
                <button type="submit" class="btn btn-rechazar"><i class="bi bi-x-circle" aria-hidden="true"></i>Cancelar solicitud</button>
            </div>
        </form>
    </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const m = document.getElementById('modal-cancelar');
  m.addEventListener('show.bs.modal', (ev) => {
    m.querySelector('[name=c_candidatura]').value = ev.relatedTarget.dataset.id;
    m.querySelector('.titulo-cancelar').textContent = ev.relatedTarget.dataset.titulo;
    m.querySelector('[name=motivo]').value = '';
  });
});
</script>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
