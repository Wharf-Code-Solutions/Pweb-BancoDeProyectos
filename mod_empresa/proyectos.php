<?php
/** Pantalla E1 · Mis proyectos (empresa). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('empresa');
$pdo = conexion();

$filtros = [
    'q'      => limpiar($_GET['q'] ?? ''),
    'estado' => isset(ESTADOS['proyecto'][$_GET['estado'] ?? '']) ? $_GET['estado'] : '',
];
$donde = ['p.rfc_empresa = ?'];
$params = [$usuario['clave']];
if ($filtros['q'] !== '') {
    $donde[] = '(p.titulo LIKE ? OR p.c_proyecto LIKE ?)';
    $like = '%' . addcslashes($filtros['q'], '%_\\') . '%';
    array_push($params, $like, $like);
}
if ($filtros['estado'] !== '') {
    $donde[] = 'p.estado = ?';
    $params[] = $filtros['estado'];
}
$where = implode(' AND ', $donde);

$s = $pdo->prepare("SELECT COUNT(*) FROM proyecto p WHERE $where");
$s->execute($params);
$pag = paginar((int) $s->fetchColumn(), 20);

$s = $pdo->prepare("SELECT p.c_proyecto, p.titulo, p.estado, p.fecha_registro,
        (SELECT COUNT(*) FROM solicitud_candidatura sc WHERE sc.c_proyecto = p.c_proyecto AND sc.estado <> 'cancelada') AS candidatos,
        (SELECT COUNT(*) FROM solicitud_candidatura sc WHERE sc.c_proyecto = p.c_proyecto AND sc.estado = 'pendiente') AS sin_evaluar
    FROM proyecto p WHERE $where
    ORDER BY FIELD(p.estado, 'devuelto', 'borrador', 'en_revision', 'publicado', 'en_curso') = 0, FIELD(p.estado, 'devuelto', 'borrador', 'en_revision', 'publicado', 'en_curso'), p.fecha_registro DESC
    LIMIT {$pag['por_pagina']} OFFSET {$pag['desde']}");
$s->execute($params);
$lista = $s->fetchAll();

$titulo = 'Mis proyectos';
$menu_activo = 'mod_empresa/proyectos.php';
$migas = [['Panel', 'mod_empresa/index.php'], ['Mis proyectos', null]];
require __DIR__ . '/../menumain/app_encabezado.php';

$acciones = function (array $p): string {
    $id = urlencode($p['c_proyecto']);
    if (in_array($p['estado'], ['borrador', 'devuelto'], true)) {
        return '<a class="btn btn-secundario btn-sm" href="' . e(url("mod_empresa/proyecto_form.php?id=$id")) . '">' . ($p['estado'] === 'devuelto' ? 'Corregir' : 'Editar') . '</a>';
    }
    $html = '<a class="btn btn-secundario btn-sm" href="' . e(url("mod_empresa/proyecto.php?id=$id")) . '">Ver</a>';
    if ((int) $p['candidatos'] > 0) {
        $html .= ' <a class="btn btn-secundario btn-sm" href="' . e(url("mod_empresa/candidatos.php?id=$id")) . '">Candidatos</a>';
    }
    return $html;
};
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Empresa</span><h1 class="titulo-izquierda">Mis proyectos</h1></div>
    <div class="acciones"><a class="btn btn-principal" href="<?= e(url('mod_empresa/proyecto_form.php')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i>Nuevo proyecto</a></div>
</div>

<?php if ($pag['total'] === 0 && !array_filter($filtros)): ?>
    <div class="card"><div class="card-body">
        <div class="vacio">
            <div class="vacio-icono"><i class="bi bi-folder2-open" aria-hidden="true"></i></div>
            <h2>Aún no tienes proyectos</h2>
            <p>Registra la necesidad tecnológica de tu empresa como proyecto y envíala a revisión del Tec.</p>
            <a class="btn btn-principal" href="<?= e(url('mod_empresa/proyecto_form.php')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i>Registrar mi primer proyecto</a>
        </div>
    </div></div>
<?php else: ?>
<div class="tabla-marco tabla-responsiva-tarjetas">
    <form class="filtros" role="search" method="get">
        <div class="flex-grow-1"><label class="form-label small mb-1" for="q">Buscar</label>
            <input class="form-control" id="q" name="q" type="search" placeholder="Título o folio del proyecto" value="<?= e($filtros['q']) ?>"></div>
        <div><label class="form-label small mb-1" for="estado">Estado</label>
            <select class="form-select" id="estado" name="estado"><option value="">Todos</option>
                <?php foreach (ESTADOS['proyecto'] as $k => [$t]): ?><option value="<?= $k ?>"<?= $filtros['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <button type="submit" class="btn btn-secundario"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button>
    </form>
    <?php if (!$lista): ?>
        <p class="p-3 mb-0 text-mute">No hay proyectos con esos filtros.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tabla-datos table-striped mb-0">
            <thead><tr><th scope="col">Proyecto</th><th scope="col">Estado</th><th scope="col">Registro</th><th scope="col">Candidatos</th><th scope="col" class="acciones">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $p): ?>
                <tr>
                    <td><strong><?= e($p['titulo']) ?></strong><br><span class="folio"><?= e($p['c_proyecto']) ?></span></td>
                    <td><?= insignia('proyecto', $p['estado']) ?></td>
                    <td><?= e(fecha_corta($p['fecha_registro'])) ?></td>
                    <td><?php if ((int) $p['candidatos']): ?><strong><?= (int) $p['candidatos'] ?></strong>
                        <?php if ((int) $p['sin_evaluar']): ?><span class="small text-mute">· <?= (int) $p['sin_evaluar'] ?> sin evaluar</span><?php endif; ?>
                        <?php else: ?>—<?php endif; ?></td>
                    <td class="acciones text-nowrap"><?= $acciones($p) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="lista-tarjetas">
        <?php foreach ($lista as $p): ?>
            <div class="fila-tarjeta">
                <span class="titulo"><?= e($p['titulo']) ?></span>
                <span class="meta"><?= e($p['c_proyecto']) ?> · <?= e(fecha_corta($p['fecha_registro'])) ?></span>
                <span><?= insignia('proyecto', $p['estado']) ?></span>
                <div><?= $acciones($p) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="pie-tabla"><?= pie_paginacion($pag, count($lista), 'mod_empresa/proyectos.php', $filtros) ?></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
