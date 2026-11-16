<?php
/** Pantalla D9 · Candidaturas por decidir (evaluadas por la empresa, en revisión). */
require_once __DIR__ . '/../inc/inicio.php';
requiere_rol('administrador');
$pdo = conexion();

$filtros = [
    'proyecto' => limpiar($_GET['proyecto'] ?? ''),
    'presel'   => ($_GET['presel'] ?? '') === '1' ? '1' : '',
];
$ultima = "SELECT ev.* FROM evaluacion_candidato ev WHERE ev.c_evaluacion = (
    SELECT ev2.c_evaluacion FROM evaluacion_candidato ev2 WHERE ev2.c_candidatura = ev.c_candidatura ORDER BY ev2.fecha DESC, ev2.c_evaluacion DESC LIMIT 1)";
$donde = ["sc.estado = 'en_revision'"];
$params = [];
if ($filtros['proyecto'] !== '') {
    $donde[] = 'sc.c_proyecto = ?';
    $params[] = $filtros['proyecto'];
}
if ($filtros['presel']) {
    $donde[] = 'ev.preseleccionado = 1';
}
$from = "FROM solicitud_candidatura sc
    JOIN alumno al ON al.matricula = sc.matricula JOIN usuario u ON u.c_usuario = al.c_usuario
    JOIN proyecto p ON p.c_proyecto = sc.c_proyecto
    LEFT JOIN ($ultima) ev ON ev.c_candidatura = sc.c_candidatura
    WHERE " . implode(' AND ', $donde);
$s = $pdo->prepare("SELECT COUNT(*) $from");
$s->execute($params);
$pag = paginar((int) $s->fetchColumn(), 20);
$s = $pdo->prepare("SELECT sc.c_candidatura, sc.fecha, sc.estado, u.nombre, al.matricula, p.titulo, ev.puntuacion_total, ev.preseleccionado, ev.fecha AS fecha_eval
    $from ORDER BY ev.preseleccionado DESC, ev.puntuacion_total DESC, sc.fecha LIMIT {$pag['por_pagina']} OFFSET {$pag['desde']}");
$s->execute($params);
$lista = $s->fetchAll();
$proyectos = $pdo->query("SELECT DISTINCT p.c_proyecto, p.titulo FROM solicitud_candidatura sc JOIN proyecto p ON p.c_proyecto = sc.c_proyecto
    WHERE sc.estado = 'en_revision' ORDER BY p.titulo")->fetchAll(PDO::FETCH_KEY_PAIR);

$titulo = 'Candidaturas por decidir';
$menu_activo = 'mod_admin/candidaturas.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Revisión', null], ['Candidaturas por decidir', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Administración</span><h1 class="titulo-izquierda">Candidaturas por decidir</h1>
        <p class="text-mute mb-0 mt-1">Aparecen aquí cuando la empresa ya evaluó al alumno.</p></div>
</div>
<div class="tabla-marco tabla-responsiva-tarjetas">
    <form class="filtros" role="search" method="get">
        <div><label class="form-label small mb-1" for="proyecto">Proyecto</label>
            <select class="form-select" id="proyecto" name="proyecto"><option value="">Todos</option>
                <?php foreach ($proyectos as $k => $t): ?><option value="<?= e($k) ?>"<?= $filtros['proyecto'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <div class="pb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="presel" name="presel" value="1"<?= $filtros['presel'] ? ' checked' : '' ?>>
            <label class="form-check-label" for="presel">Solo preseleccionados</label></div></div>
        <button type="submit" class="btn btn-secundario"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button>
    </form>
    <?php if (!$lista): ?>
        <div class="vacio p-4"><div class="vacio-icono"><i class="bi bi-people" aria-hidden="true"></i></div>
            <h2>No hay candidaturas por decidir</h2><p>Cuando una empresa evalúe a un candidato, la candidatura aparecerá aquí.</p></div>
    <?php else: $decidir = fn ($c) => '<a class="btn btn-secundario btn-sm" href="' . e(url('mod_admin/candidatura.php?id=' . urlencode($c['c_candidatura']))) . '">Decidir</a>'; ?>
        <div class="table-responsive"><table class="table tabla-datos table-striped mb-0">
            <thead><tr><th scope="col">Alumno</th><th scope="col">Proyecto</th><th scope="col">Estado</th><th scope="col">Preseleccionado</th><th scope="col">Puntuación</th><th scope="col">Evaluada</th><th scope="col" class="acciones">Acciones</th></tr></thead>
            <tbody><?php foreach ($lista as $c): ?>
                <tr><td><strong><?= e($c['nombre']) ?></strong><br><span class="folio"><?= e($c['matricula']) ?></span></td>
                    <td><?= e($c['titulo']) ?></td><td><?= insignia('candidatura', $c['estado']) ?></td>
                    <td><?= $c['preseleccionado'] ? '<span class="estado estado-preseleccion"><i class="bi bi-star-fill" aria-hidden="true"></i>Sí</span>' : 'No' ?></td>
                    <td><span class="puntuacion"><?= puntos($c['puntuacion_total']) ?></span></td>
                    <td><?= $c['fecha_eval'] ? e(fecha_corta($c['fecha_eval'])) : '—' ?></td>
                    <td class="acciones"><?= $decidir($c) ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <div class="lista-tarjetas"><?php foreach ($lista as $c): ?>
            <div class="fila-tarjeta"><span class="titulo"><?= e($c['nombre']) ?></span>
                <span class="meta"><?= e($c['titulo']) ?> · <?= puntos($c['puntuacion_total']) ?><?= $c['preseleccionado'] ? ' · Preseleccionado' : '' ?></span>
                <div><?= $decidir($c) ?></div></div>
        <?php endforeach; ?></div>
    <?php endif; ?>
    <div class="pie-tabla"><?= pie_paginacion($pag, count($lista), 'mod_admin/candidaturas.php', $filtros) ?></div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
