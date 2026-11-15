<?php
/** Pantalla D14 · Historial de auditoría (solo lectura). */
require_once __DIR__ . '/../inc/inicio.php';
requiere_rol('administrador');
$pdo = conexion();

$operaciones = $pdo->query('SELECT DISTINCT operacion FROM historial_auditoria ORDER BY operacion')->fetchAll(PDO::FETCH_COLUMN);
$entidades = $pdo->query('SELECT DISTINCT entidad_afectada FROM historial_auditoria ORDER BY entidad_afectada')->fetchAll(PDO::FETCH_COLUMN);
$resultados = ['exito' => ['Éxito', 'exito'], 'error' => ['Error', 'advertencia'], 'denegado' => ['Acceso denegado', 'peligro']];
$fecha_valida = fn (string $f) => DateTime::createFromFormat('!Y-m-d', $f) ? $f : '';

$filtros = [
    'usuario'   => limpiar($_GET['usuario'] ?? ''),
    'operacion' => in_array($_GET['operacion'] ?? '', $operaciones, true) ? $_GET['operacion'] : '',
    'entidad'   => in_array($_GET['entidad'] ?? '', $entidades, true) ? $_GET['entidad'] : '',
    'resultado' => isset($resultados[$_GET['resultado'] ?? '']) ? $_GET['resultado'] : '',
    'desde'     => $fecha_valida((string) ($_GET['desde'] ?? '')),
    'hasta'     => $fecha_valida((string) ($_GET['hasta'] ?? '')),
    'registro'  => limpiar($_GET['registro'] ?? ''),
];
$donde = [];
$params = [];
if ($filtros['usuario'] !== '') {
    $donde[] = '(u.nombre LIKE ? OR u.correo LIKE ? OR u.c_usuario = ?)';
    $like = '%' . addcslashes($filtros['usuario'], '%_\\') . '%';
    array_push($params, $like, $like, $filtros['usuario']);
}
foreach (['operacion' => 'h.operacion', 'entidad' => 'h.entidad_afectada', 'resultado' => 'h.resultado', 'registro' => 'h.registro_afectado'] as $k => $col) {
    if ($filtros[$k] !== '') {
        $donde[] = "$col = ?";
        $params[] = $filtros[$k];
    }
}
if ($filtros['desde']) {
    $donde[] = 'h.fecha >= ?';
    $params[] = $filtros['desde'] . ' 00:00:00';
}
if ($filtros['hasta']) {
    $donde[] = 'h.fecha <= ?';
    $params[] = $filtros['hasta'] . ' 23:59:59';
}
$where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';
$from = "FROM historial_auditoria h JOIN usuario u ON u.c_usuario = h.c_usuario $where";

$s = $pdo->prepare("SELECT COUNT(*) $from");
$s->execute($params);
$pag = paginar((int) $s->fetchColumn(), 50);
$s = $pdo->prepare("SELECT h.*, u.nombre, u.rol $from ORDER BY h.fecha DESC, h.c_historial DESC LIMIT {$pag['por_pagina']} OFFSET {$pag['desde']}");
$s->execute($params);
$lista = $s->fetchAll();

$titulo = 'Historial de auditoría';
$menu_activo = 'mod_admin/historial.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Consulta', null], ['Historial', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$nombre_op = fn (string $op) => ucfirst(str_replace('_', ' ', $op));
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Consulta</span><h1 class="titulo-izquierda">Historial de auditoría</h1></div>
</div>
<div class="tabla-marco tabla-responsiva-tarjetas">
    <form class="filtros" role="search" method="get">
        <div><label class="form-label small mb-1" for="usuario">Usuario</label>
            <input class="form-control" id="usuario" name="usuario" type="search" placeholder="Nombre, correo o folio" value="<?= e($filtros['usuario']) ?>"></div>
        <div><label class="form-label small mb-1" for="operacion">Operación</label>
            <select class="form-select" id="operacion" name="operacion"><option value="">Todas</option>
                <?php foreach ($operaciones as $o): ?><option value="<?= e($o) ?>"<?= $filtros['operacion'] === $o ? ' selected' : '' ?>><?= e($nombre_op($o)) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label small mb-1" for="entidad">Entidad</label>
            <select class="form-select" id="entidad" name="entidad"><option value="">Todas</option>
                <?php foreach ($entidades as $en): ?><option value="<?= e($en) ?>"<?= $filtros['entidad'] === $en ? ' selected' : '' ?>><?= e($nombre_op($en)) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label small mb-1" for="resultado">Resultado</label>
            <select class="form-select" id="resultado" name="resultado"><option value="">Todos</option>
                <?php foreach ($resultados as $k => [$t]): ?><option value="<?= $k ?>"<?= $filtros['resultado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label small mb-1" for="desde">Desde</label><input class="form-control" id="desde" name="desde" type="date" value="<?= e($filtros['desde']) ?>"></div>
        <div><label class="form-label small mb-1" for="hasta">Hasta</label><input class="form-control" id="hasta" name="hasta" type="date" value="<?= e($filtros['hasta']) ?>"></div>
        <button type="submit" class="btn btn-secundario"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button>
        <?php if (array_filter($filtros)): ?><a class="btn btn-terciario" href="<?= e(url('mod_admin/historial.php')) ?>">Limpiar</a><?php endif; ?>
    </form>
    <?php if (!$lista): ?>
        <p class="p-3 mb-0 text-mute">No hay registros con esos filtros.</p>
    <?php else: ?>
        <div class="table-responsive"><table class="table tabla-datos table-striped mb-0">
            <thead><tr><th scope="col">Fecha y hora</th><th scope="col">Usuario</th><th scope="col">Operación</th><th scope="col">Entidad</th><th scope="col">Registro</th><th scope="col">Resultado</th></tr></thead>
            <tbody><?php foreach ($lista as $h): [$rt, $rc] = $resultados[$h['resultado']] ?? [$h['resultado'], 'neutro']; ?>
                <tr><td class="text-nowrap"><?= e(fecha_hora($h['fecha'])) ?></td>
                    <td><?= e($h['nombre']) ?><br><span class="small text-mute"><?= e(nombre_rol($h['rol'])) ?></span></td>
                    <td><?= e($nombre_op($h['operacion'])) ?></td><td><?= e($nombre_op($h['entidad_afectada'])) ?></td>
                    <td><a class="folio text-decoration-none" href="<?= e(url_lista('mod_admin/historial.php', ['registro' => $h['registro_afectado']])) ?>" title="Ver todo lo de este registro"><?= e($h['registro_afectado']) ?></a></td>
                    <td><span class="estado estado-<?= $rc ?>"><?= e($rt) ?></span></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <div class="lista-tarjetas"><?php foreach ($lista as $h): [$rt, $rc] = $resultados[$h['resultado']] ?? [$h['resultado'], 'neutro']; ?>
            <div class="fila-tarjeta"><span class="titulo"><?= e($nombre_op($h['operacion'])) ?> · <?= e($h['registro_afectado']) ?></span>
                <span class="meta"><?= e(fecha_hora($h['fecha'])) ?> · <?= e($h['nombre']) ?></span><span><span class="estado estado-<?= $rc ?>"><?= e($rt) ?></span></span></div>
        <?php endforeach; ?></div>
    <?php endif; ?>
    <div class="pie-tabla"><?= pie_paginacion($pag, count($lista), 'mod_admin/historial.php', $filtros) ?> <span class="small text-mute">Solo lectura: el historial no se modifica ni se borra.</span></div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
