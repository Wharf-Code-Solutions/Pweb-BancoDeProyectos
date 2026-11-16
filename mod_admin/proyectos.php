<?php
/** Pantalla D7 · Proyectos (gestión del administrador). */
require_once __DIR__ . '/../inc/inicio.php';
requiere_rol('administrador');
$pdo = conexion();

$filtros = [
    'q'       => limpiar($_GET['q'] ?? ''),
    'estado'  => isset(ESTADOS['proyecto'][$_GET['estado'] ?? '']) ? $_GET['estado'] : '',
    'empresa' => limpiar($_GET['empresa'] ?? ''),
    'sector'  => in_array($_GET['sector'] ?? '', SECTORES_EMPRESA, true) ? $_GET['sector'] : '',
    'listos'  => ($_GET['listos'] ?? '') === '1' ? '1' : '',
];
// La administración no ve los borradores: todavía son de la empresa.
$donde = ["r.estado <> 'borrador'"];
$params = [];
if ($filtros['q'] !== '') {
    $donde[] = '(r.titulo LIKE ? OR r.c_proyecto LIKE ?)';
    $like = '%' . addcslashes($filtros['q'], '%_\\') . '%';
    array_push($params, $like, $like);
}
foreach (['estado' => 'r.estado', 'empresa' => 'r.rfc_empresa', 'sector' => 'e.sector'] as $k => $col) {
    if ($filtros[$k] !== '') {
        $donde[] = "$col = ?";
        $params[] = $filtros[$k];
    }
}
if ($filtros['listos']) {
    $donde[] = "r.estado = 'publicado' AND r.num_empleado_director IS NOT NULL AND r.alumnos_asignados > 0";
}
$where = implode(' AND ', $donde);
$from = 'FROM v_proyecto_resumen r JOIN empresa e ON e.rfc_empresa = r.rfc_empresa';

$s = $pdo->prepare("SELECT COUNT(*) $from WHERE $where");
$s->execute($params);
$pag = paginar((int) $s->fetchColumn(), 20);

$s = $pdo->prepare("SELECT r.*, ud.nombre AS director $from
        LEFT JOIN profesor pr ON pr.num_empleado = r.num_empleado_director
        LEFT JOIN usuario ud ON ud.c_usuario = pr.c_usuario
    WHERE $where
    ORDER BY FIELD(r.estado, 'en_revision', 'publicado', 'en_curso', 'devuelto', 'finalizado', 'rechazado', 'cancelado'), r.c_proyecto DESC
    LIMIT {$pag['por_pagina']} OFFSET {$pag['desde']}");
$s->execute($params);
$lista = $s->fetchAll();

$empresas = $pdo->query('SELECT rfc_empresa, razon_social FROM empresa ORDER BY razon_social')->fetchAll(PDO::FETCH_KEY_PAIR);
$volver = url_lista('mod_admin/proyectos.php', $filtros + ['pagina' => $pag['pagina']]);
$volver = substr($volver, strlen(base_url()) + 1);

$titulo = 'Proyectos';
$menu_activo = 'mod_admin/proyectos.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Gestión', null], ['Proyectos', null]];
require __DIR__ . '/../menumain/app_encabezado.php';

$acciones = function (array $p) use ($volver): string {
    $id = $p['c_proyecto'];
    $html = '<a class="btn btn-secundario btn-sm" href="' . e(url('mod_admin/proyecto.php?id=' . urlencode($id))) . '">' . ($p['estado'] === 'en_revision' ? 'Revisar' : 'Ver') . '</a>';
    $boton = function (string $accion, string $texto, string $pregunta) use ($id, $volver): string {
        return ' <form method="post" action="' . e(url('mod_admin/proyecto_accion.php')) . '" class="d-inline">' . csrf_campo()
             . '<input type="hidden" name="id" value="' . e($id) . '"><input type="hidden" name="accion" value="' . $accion . '">'
             . '<input type="hidden" name="volver" value="' . e($volver) . '">'
             . '<button type="submit" class="btn btn-secundario btn-sm" data-confirmar="' . e($pregunta) . '">' . $texto . '</button></form>';
    };
    if ($p['estado'] === 'publicado' && $p['num_empleado_director'] && $p['alumnos_asignados'] > 0) {
        $html .= $boton('iniciar', 'Iniciar', '¿Iniciar «' . $p['titulo'] . '»? Pasará a «En curso».');
    } elseif ($p['estado'] === 'en_curso') {
        $html .= $boton('finalizar', 'Finalizar', '¿Finalizar «' . $p['titulo'] . '»? Se cerrarán las asignaciones activas.');
    }
    return $html;
};
?>
<div class="encabezado-pagina">
    <div><h1 class="titulo-izquierda">Proyectos</h1></div>
</div>
<div class="tabla-marco tabla-responsiva-tarjetas">
    <form class="filtros" role="search" method="get">
        <div><label class="form-label small mb-1" for="q">Buscar</label>
            <input class="form-control" id="q" name="q" type="search" placeholder="Título o folio" value="<?= e($filtros['q']) ?>"></div>
        <div><label class="form-label small mb-1" for="estado">Estado</label>
            <select class="form-select" id="estado" name="estado"><option value="">Todos</option>
                <?php foreach (ESTADOS['proyecto'] as $k => [$t]): if ($k === 'borrador') continue; ?>
                    <option value="<?= $k ?>"<?= $filtros['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <div><label class="form-label small mb-1" for="empresa">Empresa</label>
            <select class="form-select" id="empresa" name="empresa"><option value="">Todas</option>
                <?php foreach ($empresas as $rfc => $rs): ?><option value="<?= e($rfc) ?>"<?= $filtros['empresa'] === $rfc ? ' selected' : '' ?>><?= e($rs) ?></option><?php endforeach; ?>
            </select></div>
        <div><label class="form-label small mb-1" for="sector">Sector</label>
            <select class="form-select" id="sector" name="sector"><option value="">Todos</option>
                <?php foreach (SECTORES_EMPRESA as $sec): ?><option<?= $filtros['sector'] === $sec ? ' selected' : '' ?>><?= e($sec) ?></option><?php endforeach; ?>
            </select></div>
        <div class="pb-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="listos" name="listos" value="1"<?= $filtros['listos'] ? ' checked' : '' ?>>
            <label class="form-check-label" for="listos">Listos para iniciar</label></div></div>
        <button type="submit" class="btn btn-secundario">Filtrar</button>
    </form>
    <?php if (!$lista): ?>
        <p class="p-3 mb-0 text-mute">No hay proyectos con esos filtros.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tabla-datos mb-0">
            <thead><tr><th scope="col">Proyecto</th><th scope="col">Empresa</th><th scope="col">Estado</th><th scope="col">Cupo</th>
                <th scope="col">Director</th><th scope="col">Candidaturas en proceso</th><th scope="col" class="acciones">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $p): ?>
                <tr>
                    <td><strong><?= e($p['titulo']) ?></strong><br><span class="folio"><?= e($p['c_proyecto']) ?></span></td>
                    <td><?= e($p['razon_social']) ?></td>
                    <td><?= insignia('proyecto', $p['estado']) ?></td>
                    <td><?= (int) $p['alumnos_asignados'] ?> / <?= $p['cupo_alumnos'] ? (int) $p['cupo_alumnos'] : '∞' ?></td>
                    <td><?= e($p['director'] ?? '—') ?></td>
                    <td><?= (int) $p['candidaturas_en_proceso'] ?></td>
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
                <span class="meta"><?= e($p['razon_social']) ?> · <?= e($p['c_proyecto']) ?></span>
                <span><?= insignia('proyecto', $p['estado']) ?></span>
                <div><?= $acciones($p) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="pie-tabla"><?= pie_paginacion($pag, count($lista), 'mod_admin/proyectos.php', $filtros) ?></div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
