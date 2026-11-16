<?php
/** Pantalla D8 · Detalle de proyecto (administrador). */
require_once __DIR__ . '/../inc/inicio.php';
requiere_rol('administrador');
$pdo = conexion();

$id = (string) ($_GET['id'] ?? '');
$p = obtener_proyecto($id);
if (!$p || $p['estado'] === 'borrador') {
    error_http(404);
}
$pestanas = ['info' => 'Información', 'participantes' => 'Participantes', 'candidaturas' => 'Candidaturas', 'direccion' => 'Solicitudes de dirección', 'historial' => 'Historial'];
$pestana = isset($pestanas[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'info';

// Participantes activos e históricos (director y alumnos), con la fecha de su autorización.
$s = $pdo->prepare("SELECT 'Director' AS tipo, u.nombre, pr.num_empleado AS clave, ap.estado, ap.fecha_cierre, a.fecha AS desde
        FROM asignacion_profesor ap
        JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion
        JOIN profesor pr ON pr.num_empleado = sd.num_empleado JOIN usuario u ON u.c_usuario = pr.c_usuario
        LEFT JOIN autorizacion a ON a.c_solicitud_direccion = sd.c_solicitud_direccion AND a.decision = 'aprobada'
        WHERE sd.c_proyecto = ?
    UNION ALL
    SELECT 'Alumno', u.nombre, al.matricula, aa.estado, aa.fecha_cierre, a.fecha
        FROM asignacion_alumno aa
        JOIN solicitud_candidatura sc ON sc.c_candidatura = aa.c_candidatura
        JOIN alumno al ON al.matricula = sc.matricula JOIN usuario u ON u.c_usuario = al.c_usuario
        LEFT JOIN autorizacion a ON a.c_candidatura = sc.c_candidatura AND a.decision = 'aprobada'
        WHERE sc.c_proyecto = ?
    ORDER BY tipo = 'Director' DESC, estado = 'activa' DESC, nombre");
$s->execute([$id, $id]);
$participantes = $s->fetchAll();

$s = $pdo->prepare("SELECT sc.c_candidatura, sc.fecha, sc.estado, u.nombre, al.matricula, ev.puntuacion_total, ev.preseleccionado
    FROM solicitud_candidatura sc JOIN alumno al ON al.matricula = sc.matricula JOIN usuario u ON u.c_usuario = al.c_usuario
    LEFT JOIN evaluacion_candidato ev ON ev.c_candidatura = sc.c_candidatura
    WHERE sc.c_proyecto = ? ORDER BY sc.fecha DESC");
$s->execute([$id]);
$candidaturas = $s->fetchAll();

$s = $pdo->prepare("SELECT sd.c_solicitud_direccion, sd.fecha, sd.estado, u.nombre, pr.num_empleado
    FROM solicitud_direccion sd JOIN profesor pr ON pr.num_empleado = sd.num_empleado JOIN usuario u ON u.c_usuario = pr.c_usuario
    WHERE sd.c_proyecto = ? ORDER BY sd.fecha DESC");
$s->execute([$id]);
$solicitudes = $s->fetchAll();

$en_proceso = count(array_filter($candidaturas, fn ($c) => in_array($c['estado'], ['pendiente', 'en_revision'], true)));
$dir_pendientes = count(array_filter($solicitudes, fn ($sd) => $sd['estado'] === 'pendiente'));
$activos = count(array_filter($participantes, fn ($x) => $x['estado'] === 'activa'));

$titulo = $p['titulo'];
$menu_activo = 'mod_admin/proyectos.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Gestión', null], ['Proyectos', 'mod_admin/proyectos.php'], [$p['titulo'], null]];
require __DIR__ . '/../menumain/app_encabezado.php';

$boton = function (string $accion, string $texto, string $icono, string $clase, string $pregunta) use ($id): string {
    return '<form method="post" action="' . e(url('mod_admin/proyecto_accion.php')) . '" class="d-inline">' . csrf_campo()
         . '<input type="hidden" name="id" value="' . e($id) . '"><input type="hidden" name="accion" value="' . $accion . '">'
         . '<input type="hidden" name="volver" value="' . e('mod_admin/proyecto.php?id=' . urlencode($id)) . '">'
         . '<button type="submit" class="btn ' . $clase . '" data-confirmar="' . e($pregunta) . '"><i class="bi ' . $icono . '" aria-hidden="true"></i>' . $texto . '</button></form>';
};
$enlace_tab = fn (string $t) => e(url('mod_admin/proyecto.php?id=' . urlencode($id) . ($t === 'info' ? '' : '&tab=' . $t)));
$contador = ['participantes' => $activos, 'candidaturas' => $en_proceso, 'direccion' => $dir_pendientes];
?>
<div class="encabezado-pagina">
    <div>
        <span class="etiqueta">Proyecto</span>
        <h1 class="titulo-izquierda"><?= e($p['titulo']) ?></h1>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
            <?= insignia('proyecto', $p['estado']) ?><?= html_folio($id) ?>
            <span class="small text-mute"><?= e($p['razon_social']) ?> · <?= e($p['sector']) ?></span>
        </div>
    </div>
    <div class="acciones">
        <?php if ($p['estado'] === 'en_revision'): ?>
            <a class="btn btn-principal" href="<?= e(url('mod_admin/revisar_proyecto.php?id=' . urlencode($id))) ?>"><i class="bi bi-clipboard-check" aria-hidden="true"></i>Revisar</a>
        <?php endif; ?>
        <?php if (in_array($p['estado'], ['publicado', 'en_curso'], true)): ?>
            <?= $boton('cancelar', 'Cancelar proyecto', 'bi-x-circle', 'btn-rechazar-contorno', '¿Cancelar «' . $p['titulo'] . '»? Se cancelarán sus asignaciones y solicitudes abiertas. No se puede deshacer.') ?>
        <?php endif; ?>
        <?php if ($p['estado'] === 'publicado' && $p['num_empleado_director'] && $p['alumnos_asignados'] > 0): ?>
            <?= $boton('iniciar', 'Iniciar proyecto', 'bi-play-fill', 'btn-principal', '¿Iniciar «' . $p['titulo'] . '»? Pasará a «En curso».') ?>
        <?php elseif ($p['estado'] === 'en_curso'): ?>
            <?= $boton('finalizar', 'Finalizar proyecto', 'bi-flag', 'btn-secundario', '¿Finalizar «' . $p['titulo'] . '»? Se finalizarán las asignaciones activas.') ?>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-9">
        <ul class="nav nav-tabs mb-0">
            <?php foreach ($pestanas as $t => $nombre): ?>
                <li class="nav-item"><a class="nav-link<?= $pestana === $t ? ' active" aria-current="page' : '' ?>" href="<?= $enlace_tab($t) ?>"><?= e($nombre) ?>
                    <?php if (!empty($contador[$t])): ?><span class="badge rounded-pill text-bg-primary"><?= $contador[$t] ?></span><?php endif; ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="bg-white border border-top-0 p-3 p-md-4" style="border-radius:0 0 8px 8px">
            <?php if ($pestana === 'info'): ?>
                <h2 class="visually-hidden">Información del proyecto</h2>
                <?= html_contenido_proyecto($p, requisitos_proyecto($id)) ?>

            <?php elseif ($pestana === 'participantes'): ?>
                <?php if (!$participantes): ?>
                    <p class="text-mute mb-0">Aún no hay director ni alumnos asignados.</p>
                <?php else: ?>
                    <div class="tabla-marco"><div class="table-responsive"><table class="table tabla-datos table-striped mb-0">
                        <thead><tr><th scope="col">Persona</th><th scope="col">Tipo</th><th scope="col">Desde</th><th scope="col">Estado</th></tr></thead>
                        <tbody><?php foreach ($participantes as $x): ?>
                            <tr><td><strong><?= e($x['nombre']) ?></strong><br><span class="folio"><?= e($x['clave']) ?></span></td>
                                <td><span class="etiqueta-dato"><?= e($x['tipo']) ?></span></td>
                                <td><?= $x['desde'] ? e(fecha_corta($x['desde'])) : '—' ?></td>
                                <td><?= insignia('asignacion', $x['estado']) ?></td></tr>
                        <?php endforeach; ?></tbody></table></div>
                        <div class="pie-tabla"><span><?= count(array_filter($participantes, fn ($x) => $x['tipo'] === 'Director' && $x['estado'] === 'activa')) ?> director ·
                            <?= count(array_filter($participantes, fn ($x) => $x['tipo'] === 'Alumno' && $x['estado'] === 'activa')) ?> alumnos activos</span></div></div>
                <?php endif; ?>

            <?php elseif ($pestana === 'candidaturas'): ?>
                <?php if (!$candidaturas): ?>
                    <p class="text-mute mb-0">Ningún alumno se ha postulado todavía.</p>
                <?php else: ?>
                    <div class="tabla-marco"><div class="table-responsive"><table class="table tabla-datos table-striped mb-0">
                        <thead><tr><th scope="col">Alumno</th><th scope="col">Fecha</th><th scope="col">Evaluación</th><th scope="col">Estado</th></tr></thead>
                        <tbody><?php foreach ($candidaturas as $c): ?>
                            <tr><td><strong><?= e($c['nombre']) ?></strong><br><span class="folio"><?= e($c['matricula']) ?></span></td>
                                <td><?= e(fecha_corta($c['fecha'])) ?></td>
                                <td><?= $c['puntuacion_total'] !== null ? e(rtrim(rtrim($c['puntuacion_total'], '0'), '.')) . ' / 100' : '—' ?>
                                    <?= $c['preseleccionado'] ? '<span class="etiqueta-dato ms-1"><i class="bi bi-star" aria-hidden="true"></i>Preseleccionado</span>' : '' ?></td>
                                <td><?= insignia('candidatura', $c['estado']) ?></td></tr>
                        <?php endforeach; ?></tbody></table></div></div>
                <?php endif; ?>

            <?php elseif ($pestana === 'direccion'): ?>
                <?php if (!$solicitudes): ?>
                    <p class="text-mute mb-0">Ningún profesor ha solicitado dirigir este proyecto.</p>
                <?php else: ?>
                    <div class="tabla-marco"><div class="table-responsive"><table class="table tabla-datos table-striped mb-0">
                        <thead><tr><th scope="col">Profesor</th><th scope="col">Fecha</th><th scope="col">Estado</th></tr></thead>
                        <tbody><?php foreach ($solicitudes as $sd): ?>
                            <tr><td><strong><?= e($sd['nombre']) ?></strong><br><span class="folio"><?= e($sd['num_empleado']) ?></span></td>
                                <td><?= e(fecha_corta($sd['fecha'])) ?></td>
                                <td><?= insignia('direccion', $sd['estado']) ?></td></tr>
                        <?php endforeach; ?></tbody></table></div></div>
                <?php endif; ?>

            <?php else: ?>
                <?= html_linea_tiempo(linea_tiempo_proyecto($id)) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-3 d-grid gap-3 align-self-start">
        <?= html_resumen_proyecto($p) ?>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
