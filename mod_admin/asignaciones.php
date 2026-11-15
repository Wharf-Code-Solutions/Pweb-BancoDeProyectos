<?php
/** Pantalla D12 · Asignaciones de alumnos y directores, con finalizar y cancelar. */
require_once __DIR__ . '/../inc/inicio.php';
requiere_rol('administrador');
$pdo = conexion();

$filtros = [
    'tipo'     => in_array($_GET['tipo'] ?? '', ['alumno', 'director'], true) ? $_GET['tipo'] : '',
    'estado'   => isset(ESTADOS['asignacion'][$_GET['estado'] ?? '']) ? $_GET['estado'] : '',
    'proyecto' => limpiar($_GET['proyecto'] ?? ''),
];

if (es_post()) {
    verificar_csrf();
    $tipo = ($_POST['tipo'] ?? '') === 'director' ? 'director' : 'alumno';
    $id = (string) ($_POST['id'] ?? '');
    $nuevo = ($_POST['accion'] ?? '') === 'finalizar' ? 'finalizada' : 'cancelada';
    [$tabla, $clave] = $tipo === 'director' ? ['asignacion_profesor', 'c_asignacion_profesor'] : ['asignacion_alumno', 'c_asignacion_alumno'];
    $s = $pdo->prepare("UPDATE $tabla SET estado = ?, fecha_cierre = CURDATE() WHERE $clave = ? AND estado = 'activa'");
    $s->execute([$nuevo, $id]);
    if ($s->rowCount()) {
        registrar_auditoria($nuevo === 'finalizada' ? 'finalizar' : 'cancelar', $tabla, $id);
        $q = $tipo === 'director'
            ? 'SELECT pr.c_usuario, p.titulo FROM asignacion_profesor a JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = a.c_solicitud_direccion
               JOIN profesor pr ON pr.num_empleado = sd.num_empleado JOIN proyecto p ON p.c_proyecto = sd.c_proyecto WHERE a.c_asignacion_profesor = ?'
            : 'SELECT al.c_usuario, p.titulo FROM asignacion_alumno a JOIN solicitud_candidatura sc ON sc.c_candidatura = a.c_candidatura
               JOIN alumno al ON al.matricula = sc.matricula JOIN proyecto p ON p.c_proyecto = sc.c_proyecto WHERE a.c_asignacion_alumno = ?';
        $s = $pdo->prepare($q);
        $s->execute([$id]);
        if ($x = $s->fetch()) {
            notificar($x['c_usuario'], 'Tu asignación ' . ($tipo === 'director' ? 'como director ' : '') . 'en «' . $x['titulo'] . '» quedó ' . $nuevo . '.');
        }
        aviso('exito', 'Asignación ' . $nuevo . '.');
    } else {
        aviso('advertencia', 'Esa asignación ya estaba cerrada.');
    }
    redirigir(url_lista('mod_admin/asignaciones.php', $filtros + ['pagina' => (int) ($_GET['pagina'] ?? 1)]));
}

$union = "SELECT 'alumno' AS tipo, aa.c_asignacion_alumno AS id, u.nombre, al.matricula AS clave, p.c_proyecto, p.titulo, aa.estado, aa.fecha_cierre
        FROM asignacion_alumno aa JOIN solicitud_candidatura sc ON sc.c_candidatura = aa.c_candidatura
        JOIN alumno al ON al.matricula = sc.matricula JOIN usuario u ON u.c_usuario = al.c_usuario JOIN proyecto p ON p.c_proyecto = sc.c_proyecto
    UNION ALL
    SELECT 'director', ap.c_asignacion_profesor, u.nombre, pr.num_empleado, p.c_proyecto, p.titulo, ap.estado, ap.fecha_cierre
        FROM asignacion_profesor ap JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion
        JOIN profesor pr ON pr.num_empleado = sd.num_empleado JOIN usuario u ON u.c_usuario = pr.c_usuario JOIN proyecto p ON p.c_proyecto = sd.c_proyecto";
$donde = [];
$params = [];
foreach (['tipo' => 'tipo', 'estado' => 'estado', 'proyecto' => 'c_proyecto'] as $k => $col) {
    if ($filtros[$k] !== '') {
        $donde[] = "t.$col = ?";
        $params[] = $filtros[$k];
    }
}
$where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';
$s = $pdo->prepare("SELECT COUNT(*) FROM ($union) t $where");
$s->execute($params);
$pag = paginar((int) $s->fetchColumn(), 25);
$s = $pdo->prepare("SELECT * FROM ($union) t $where ORDER BY t.estado = 'activa' DESC, t.titulo, t.tipo = 'director' DESC, t.nombre LIMIT {$pag['por_pagina']} OFFSET {$pag['desde']}");
$s->execute($params);
$lista = $s->fetchAll();
$proyectos = $pdo->query("SELECT DISTINCT p.c_proyecto, p.titulo FROM proyecto p
    WHERE EXISTS (SELECT 1 FROM solicitud_candidatura sc JOIN asignacion_alumno aa ON aa.c_candidatura = sc.c_candidatura WHERE sc.c_proyecto = p.c_proyecto)
       OR EXISTS (SELECT 1 FROM solicitud_direccion sd JOIN asignacion_profesor ap ON ap.c_solicitud_direccion = sd.c_solicitud_direccion WHERE sd.c_proyecto = p.c_proyecto)
    ORDER BY p.titulo")->fetchAll(PDO::FETCH_KEY_PAIR);
$accion_form = e(url_lista('mod_admin/asignaciones.php', $filtros + ['pagina' => $pag['pagina']]));

$titulo = 'Asignaciones';
$menu_activo = 'mod_admin/asignaciones.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Gestión', null], ['Asignaciones', null]];
require __DIR__ . '/../menumain/app_encabezado.php';

$acciones = function (array $a) use ($accion_form): string {
    if ($a['estado'] !== 'activa') {
        return '';
    }
    $quien = ($a['tipo'] === 'director' ? 'del director ' : 'de ') . $a['nombre'];
    $form = fn (string $accion, string $texto, string $clase, string $pregunta) => '<form method="post" action="' . $accion_form . '" class="d-inline">' . csrf_campo()
        . '<input type="hidden" name="tipo" value="' . $a['tipo'] . '"><input type="hidden" name="id" value="' . e($a['id']) . '"><input type="hidden" name="accion" value="' . $accion . '">'
        . '<button type="submit" class="btn ' . $clase . ' btn-sm" data-confirmar="' . e($pregunta) . '">' . $texto . '</button></form>';
    return $form('finalizar', 'Finalizar', 'btn-secundario', "¿Finalizar la asignación $quien en «{$a['titulo']}»?") . ' '
         . $form('cancelar', 'Cancelar', 'btn-rechazar-contorno', "¿Cancelar la asignación $quien en «{$a['titulo']}»? No se puede reabrir.");
};
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Administración</span><h1 class="titulo-izquierda">Asignaciones</h1></div>
</div>
<div class="tabla-marco tabla-responsiva-tarjetas">
    <form class="filtros" role="search" method="get">
        <div><label class="form-label small mb-1" for="tipo">Tipo</label>
            <select class="form-select" id="tipo" name="tipo"><option value="">Todos</option>
                <option value="alumno"<?= $filtros['tipo'] === 'alumno' ? ' selected' : '' ?>>Alumno</option><option value="director"<?= $filtros['tipo'] === 'director' ? ' selected' : '' ?>>Director</option></select></div>
        <div><label class="form-label small mb-1" for="estado">Estado</label>
            <select class="form-select" id="estado" name="estado"><option value="">Todos</option>
                <?php foreach (ESTADOS['asignacion'] as $k => [$t]): ?><option value="<?= $k ?>"<?= $filtros['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label small mb-1" for="proyecto">Proyecto</label>
            <select class="form-select" id="proyecto" name="proyecto"><option value="">Todos</option>
                <?php foreach ($proyectos as $k => $t): ?><option value="<?= e($k) ?>"<?= $filtros['proyecto'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <button type="submit" class="btn btn-secundario"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button>
    </form>
    <?php if (!$lista): ?>
        <div class="vacio p-4"><div class="vacio-icono"><i class="bi bi-diagram-3" aria-hidden="true"></i></div>
            <h3>No hay asignaciones<?= array_filter($filtros) ? ' con esos filtros' : '' ?></h3><p>Se crean al aprobar candidaturas y solicitudes de dirección.</p></div>
    <?php else: ?>
        <div class="table-responsive"><table class="table tabla-datos table-striped mb-0">
            <thead><tr><th scope="col">Tipo</th><th scope="col">Persona</th><th scope="col">Proyecto</th><th scope="col">Estado</th><th scope="col">Fecha de cierre</th><th scope="col" class="acciones">Acciones</th></tr></thead>
            <tbody><?php foreach ($lista as $a): ?>
                <tr><td><span class="etiqueta-dato"><?= $a['tipo'] === 'director' ? 'Director' : 'Alumno' ?></span></td>
                    <td><strong><?= e($a['nombre']) ?></strong><br><span class="folio"><?= e($a['clave']) ?></span></td>
                    <td><a href="<?= e(url('mod_admin/proyecto.php?id=' . urlencode($a['c_proyecto']) . '&tab=participantes')) ?>"><?= e($a['titulo']) ?></a></td>
                    <td><?= insignia('asignacion', $a['estado']) ?></td>
                    <td><?= $a['fecha_cierre'] ? e(fecha_corta($a['fecha_cierre'])) : '—' ?></td>
                    <td class="acciones text-nowrap"><?= $acciones($a) ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <div class="lista-tarjetas"><?php foreach ($lista as $a): ?>
            <div class="fila-tarjeta"><span class="titulo"><?= e($a['nombre']) ?></span>
                <span class="meta"><?= $a['tipo'] === 'director' ? 'Director' : 'Alumno' ?> · <?= e($a['titulo']) ?></span>
                <span><?= insignia('asignacion', $a['estado']) ?></span><div><?= $acciones($a) ?></div></div>
        <?php endforeach; ?></div>
    <?php endif; ?>
    <div class="pie-tabla"><?= pie_paginacion($pag, count($lista), 'mod_admin/asignaciones.php', $filtros) ?></div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
