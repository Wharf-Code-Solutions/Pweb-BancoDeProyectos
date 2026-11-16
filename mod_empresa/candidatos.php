<?php
/** Pantalla E4 · Candidatos de un proyecto (empresa). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('empresa');
$pdo = conexion();

$id = (string) ($_GET['id'] ?? '');
$p = obtener_proyecto($id);
if (!$p || $p['rfc_empresa'] !== $usuario['clave']) {
    error_http(404);
}
$filtros = [
    'id'     => $id,
    'estado' => isset(ESTADOS['candidatura'][$_GET['estado'] ?? '']) ? $_GET['estado'] : '',
    'presel' => ($_GET['presel'] ?? '') === '1' ? '1' : '',
];

// Última evaluación de cada candidatura (la más reciente manda).
$ultima = "SELECT ev.* FROM evaluacion_candidato ev WHERE ev.c_evaluacion = (
    SELECT ev2.c_evaluacion FROM evaluacion_candidato ev2 WHERE ev2.c_candidatura = ev.c_candidatura ORDER BY ev2.fecha DESC, ev2.c_evaluacion DESC LIMIT 1)";
$donde = ['sc.c_proyecto = ?'];
$params = [$id];
if ($filtros['estado']) {
    $donde[] = 'sc.estado = ?';
    $params[] = $filtros['estado'];
}
if ($filtros['presel']) {
    $donde[] = 'ev.preseleccionado = 1';
}
$s = $pdo->prepare("SELECT sc.c_candidatura, sc.matricula, sc.estado, sc.fecha, u.nombre, al.carrera, al.semestre,
        ev.puntuacion_total, ev.preseleccionado
    FROM solicitud_candidatura sc
    JOIN alumno al ON al.matricula = sc.matricula JOIN usuario u ON u.c_usuario = al.c_usuario
    LEFT JOIN ($ultima) ev ON ev.c_candidatura = sc.c_candidatura
    WHERE " . implode(' AND ', $donde) . "
    ORDER BY sc.estado = 'pendiente' DESC, FIELD(sc.estado, 'pendiente', 'en_revision', 'aprobada', 'rechazada', 'cancelada'), ev.puntuacion_total DESC, sc.fecha");
$s->execute($params);
$lista = $s->fetchAll();
$requisitos = requisitos_proyecto($id);

$titulo = 'Candidatos · ' . $p['titulo'];
$menu_activo = 'mod_empresa/proyectos.php';
$migas = [['Panel', 'mod_empresa/index.php'], ['Mis proyectos', 'mod_empresa/proyectos.php'], [$p['titulo'], 'mod_empresa/proyecto.php?id=' . urlencode($id)], ['Candidatos', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$asignados = (int) $p['alumnos_asignados'];
$evaluable = in_array($p['estado'], ['publicado', 'en_curso'], true);
?>
<div class="encabezado-pagina">
    <div>
        <span class="etiqueta">Candidatos</span>
        <h1 class="titulo-izquierda"><?= e($p['titulo']) ?></h1>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center"><?= insignia('proyecto', $p['estado']) ?><span class="folio"><?= e($id) ?></span></div>
    </div>
</div>
<ul class="nav nav-tabs mb-0">
    <li class="nav-item"><a class="nav-link" href="<?= e(url('mod_empresa/proyecto.php?id=' . urlencode($id))) ?>">Información</a></li>
    <li class="nav-item"><a class="nav-link active" aria-current="page" href="<?= e(url('mod_empresa/candidatos.php?id=' . urlencode($id))) ?>">Candidatos
        <?php if ($lista): ?><span class="badge rounded-pill text-bg-primary"><?= count($lista) ?></span><?php endif; ?></a></li>
    <li class="nav-item"><a class="nav-link" href="<?= e(url('mod_empresa/proyecto.php?id=' . urlencode($id) . '&tab=historial')) ?>">Historial</a></li>
</ul>
<div class="bg-white border border-top-0 p-3 d-flex flex-wrap gap-3 align-items-center justify-content-between" style="border-bottom:0!important">
    <?= $p['cupo_alumnos']
        ? html_cupo("Cupo: $asignados de {$p['cupo_alumnos']} asignados", $asignados, (int) $p['cupo_alumnos'], 'La asignación la autoriza el Tec', 'bi-people')
        : html_cupo("Alumnos asignados: $asignados", 0, 0, 'Sin límite de cupo · la asignación la autoriza el Tec', 'bi-people') ?>
    <span class="small text-mute">Orden: pendientes primero, luego por puntuación.</span>
</div>
<?php if (!criterios_listos()): ?>
    <div class="alert alert-warning mb-0" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div class="alert-cuerpo">Por ahora no se puede evaluar: el Tec está ajustando los criterios de evaluación.</div></div>
<?php endif; ?>
<div class="tabla-marco tabla-responsiva-tarjetas">
    <form class="filtros" role="search" method="get">
        <input type="hidden" name="id" value="<?= e($id) ?>">
        <div><label class="form-label small mb-1" for="estado">Estado</label>
            <select class="form-select" id="estado" name="estado"><option value="">Todos</option>
                <?php foreach (ESTADOS['candidatura'] as $k => [$t]): ?><option value="<?= $k ?>"<?= $filtros['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <div><label class="form-label small mb-1" for="presel">Preselección</label>
            <select class="form-select" id="presel" name="presel"><option value="">Todos</option><option value="1"<?= $filtros['presel'] ? ' selected' : '' ?>>Preseleccionados</option></select></div>
        <button type="submit" class="btn btn-secundario"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button>
    </form>
    <?php if (!$lista): ?>
        <div class="vacio p-4"><div class="vacio-icono"><i class="bi bi-people" aria-hidden="true"></i></div>
            <h2><?= $filtros['estado'] || $filtros['presel'] ? 'No hay candidatos con esos filtros' : 'Aún no hay candidatos' ?></h2>
            <p>Cuando un alumno se postule a este proyecto aparecerá aquí.</p></div>
    <?php else:
        $boton = function (array $c) use ($evaluable): string {
            $puede = $evaluable && in_array($c['estado'], ['pendiente', 'en_revision'], true);
            return '<a class="btn btn-secundario btn-sm" href="' . e(url('mod_empresa/evaluar.php?id=' . urlencode($c['c_candidatura']))) . '">'
                 . ($puede ? ($c['puntuacion_total'] === null ? 'Evaluar' : 'Reevaluar') : 'Ver') . '</a>';
        }; ?>
        <div class="table-responsive"><table class="table tabla-datos table-striped mb-0">
            <thead><tr><th scope="col">Alumno</th><th scope="col">Carrera y semestre</th><th scope="col">Coincidencia de habilidades</th><th scope="col">Estado</th>
                <th scope="col">Puntuación</th><th scope="col">Preseleccionado</th><th scope="col" class="acciones">Acciones</th></tr></thead>
            <tbody><?php foreach ($lista as $c): $co = coincidencia($requisitos, perfil_alumno($c['matricula'])); ?>
                <tr><td><strong><?= e($c['nombre']) ?></strong><br><span class="folio"><?= e($c['matricula']) ?></span></td>
                    <td><?= e($c['carrera']) ?><br><span class="small text-mute"><?= (int) $c['semestre'] ?>.° semestre</span></td>
                    <td><?= str_replace('Cumples', 'Cumple', html_coincidencia($co)) ?></td>
                    <td><?= insignia('candidatura', $c['estado']) ?></td>
                    <td><?= $c['puntuacion_total'] !== null ? '<span class="puntuacion">' . puntos($c['puntuacion_total']) . '</span>' : '—' ?></td>
                    <td><?= $c['preseleccionado'] ? 'Sí' : 'No' ?></td>
                    <td class="acciones"><?= $boton($c) ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <div class="lista-tarjetas"><?php foreach ($lista as $c): ?>
            <div class="fila-tarjeta"><span class="titulo"><?= e($c['nombre']) ?></span>
                <span class="meta"><?= e($c['matricula']) ?> · <?= (int) $c['semestre'] ?>.° semestre<?= $c['puntuacion_total'] !== null ? ' · ' . puntos($c['puntuacion_total']) : '' ?></span>
                <span><?= insignia('candidatura', $c['estado']) ?></span><div><?= $boton($c) ?></div></div>
        <?php endforeach; ?></div>
    <?php endif; ?>
    <div class="pie-tabla"><span><?= count($lista) ?> candidato<?= count($lista) === 1 ? '' : 's' ?></span></div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
