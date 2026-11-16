<?php
/** Pantalla D5 · Proyectos por revisar. */
require_once __DIR__ . '/../inc/inicio.php';
requiere_rol('administrador');
$pdo = conexion();

$filtros = [
    'q'    => limpiar($_GET['q'] ?? ''),
    'tipo' => in_array($_GET['tipo'] ?? '', ['reenviados', 'primera'], true) ? $_GET['tipo'] : '',
];
$reenviado_sql = "EXISTS (SELECT 1 FROM autorizacion a WHERE a.c_proyecto = p.c_proyecto AND a.decision = 'devuelta')";
$donde = ["p.estado = 'en_revision'"];
$params = [];
if ($filtros['q'] !== '') {
    $donde[] = '(p.titulo LIKE ? OR e.razon_social LIKE ? OR p.c_proyecto LIKE ?)';
    $like = '%' . addcslashes($filtros['q'], '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
if ($filtros['tipo'] === 'reenviados') {
    $donde[] = $reenviado_sql;
} elseif ($filtros['tipo'] === 'primera') {
    $donde[] = "NOT $reenviado_sql";
}
$where = implode(' AND ', $donde);

$s = $pdo->prepare("SELECT COUNT(*) FROM proyecto p JOIN empresa e ON e.rfc_empresa = p.rfc_empresa WHERE $where");
$s->execute($params);
$pag = paginar((int) $s->fetchColumn(), 20);

// Fecha de envío: el último envío registrado en el historial (o la de registro).
$s = $pdo->prepare("SELECT p.c_proyecto, p.titulo, e.razon_social, $reenviado_sql AS reenviado,
        COALESCE((SELECT MAX(h.fecha) FROM historial_auditoria h
                   WHERE h.entidad_afectada = 'proyecto' AND h.registro_afectado = p.c_proyecto
                     AND h.operacion IN ('enviar_revision', 'reenviar_revision')), p.fecha_registro) AS enviado
    FROM proyecto p JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
    WHERE $where ORDER BY enviado ASC
    LIMIT {$pag['por_pagina']} OFFSET {$pag['desde']}");
$s->execute($params);
$lista = $s->fetchAll();

$titulo = 'Proyectos por revisar';
$menu_activo = 'mod_admin/revisar_proyectos.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Revisión', null], ['Proyectos por revisar', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div><h1 class="titulo-izquierda">Proyectos por revisar</h1></div>
</div>
<div class="tabla-marco tabla-responsiva-tarjetas">
    <form class="filtros" role="search" method="get">
        <div class="flex-grow-1"><label class="form-label small mb-1" for="q">Buscar</label>
            <input class="form-control" id="q" name="q" type="search" placeholder="Título, empresa o folio" value="<?= e($filtros['q']) ?>"></div>
        <div><label class="form-label small mb-1" for="tipo">Tipo</label>
            <select class="form-select" id="tipo" name="tipo">
                <option value="">Todos</option>
                <option value="reenviados"<?= $filtros['tipo'] === 'reenviados' ? ' selected' : '' ?>>Reenviados</option>
                <option value="primera"<?= $filtros['tipo'] === 'primera' ? ' selected' : '' ?>>Primera revisión</option>
            </select></div>
        <button type="submit" class="btn btn-secundario">Filtrar</button>
    </form>
    <?php if (!$lista): ?>
        <div class="vacio">
            <h2>No hay proyectos por revisar</h2>
            <p>Cuando una empresa envíe un proyecto aparecerá aquí.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tabla-datos mb-0">
            <thead><tr><th scope="col">Proyecto</th><th scope="col">Empresa</th><th scope="col">Enviado</th><th scope="col">Tipo</th><th scope="col" class="acciones">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $p): ?>
                <tr>
                    <td><strong><?= e($p['titulo']) ?></strong><br><span class="folio"><?= e($p['c_proyecto']) ?></span></td>
                    <td><?= e($p['razon_social']) ?></td>
                    <td><?= e(fecha_hora($p['enviado'])) ?></td>
                    <td><?= $p['reenviado'] ? '<span class="etiqueta-reenviado">Reenviado</span>' : 'Primera revisión' ?></td>
                    <td class="acciones"><a class="btn btn-secundario btn-sm" href="<?= e(url('mod_admin/revisar_proyecto.php?id=' . urlencode($p['c_proyecto']))) ?>">Revisar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="lista-tarjetas">
        <?php foreach ($lista as $p): ?>
            <div class="fila-tarjeta">
                <span class="titulo"><?= e($p['titulo']) ?></span>
                <span class="meta"><?= e($p['razon_social']) ?> · <?= e(fecha_hora($p['enviado'])) ?><?= $p['reenviado'] ? ' · Reenviado' : '' ?></span>
                <div><a class="btn btn-secundario btn-sm" href="<?= e(url('mod_admin/revisar_proyecto.php?id=' . urlencode($p['c_proyecto']))) ?>">Revisar</a></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="pie-tabla"><?= pie_paginacion($pag, count($lista), 'mod_admin/revisar_proyectos.php', $filtros) ?></div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
