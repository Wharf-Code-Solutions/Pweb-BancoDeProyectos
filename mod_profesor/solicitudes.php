<?php
/** Pantalla R4 · Mis solicitudes de dirección (profesor). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('profesor');
$pdo = conexion();
$num = $usuario['clave'];

if (es_post()) {
    verificar_csrf();
    $id = (string) ($_POST['c_solicitud'] ?? '');
    $s = $pdo->prepare("UPDATE solicitud_direccion SET estado = 'cancelada' WHERE c_solicitud_direccion = ? AND num_empleado = ? AND estado = 'pendiente'");
    $s->execute([$id, $num]);
    if ($s->rowCount()) {
        registrar_auditoria('cancelar', 'solicitud_direccion', $id);
        aviso('exito', 'Solicitud de dirección cancelada.');
    } else {
        aviso('advertencia', 'Esa solicitud ya no se puede cancelar.');
    }
    redirigir('mod_profesor/solicitudes.php');
}

$estado = isset(ESTADOS['direccion'][$_GET['estado'] ?? '']) ? $_GET['estado'] : '';
$sql = "SELECT sd.*, p.titulo, e.razon_social, a.decision, a.fecha AS fecha_decision, a.justificacion AS justificacion_tec, ua.nombre AS administrador
    FROM solicitud_direccion sd
    JOIN proyecto p ON p.c_proyecto = sd.c_proyecto JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
    LEFT JOIN autorizacion a ON a.c_solicitud_direccion = sd.c_solicitud_direccion
    LEFT JOIN administrador ad ON ad.num_empleado = a.num_empleado_admin LEFT JOIN usuario ua ON ua.c_usuario = ad.c_usuario
    WHERE sd.num_empleado = ?" . ($estado ? ' AND sd.estado = ?' : '');
$s = $pdo->prepare("SELECT COUNT(*) FROM ($sql) t");
$s->execute($estado ? [$num, $estado] : [$num]);
$pag = paginar((int) $s->fetchColumn(), 20);
$s = $pdo->prepare("$sql ORDER BY sd.fecha DESC LIMIT {$pag['por_pagina']} OFFSET {$pag['desde']}");
$s->execute($estado ? [$num, $estado] : [$num]);
$lista = $s->fetchAll();
$ver = (string) ($_GET['ver'] ?? '');
$detalle = null;
foreach ($lista as $sd) {
    if ($sd['c_solicitud_direccion'] === $ver) {
        $detalle = $sd;
    }
}

$titulo = 'Mis solicitudes de dirección';
$menu_activo = 'mod_profesor/solicitudes.php';
$migas = [['Panel', 'mod_profesor/index.php'], ['Mis solicitudes de dirección', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$filtros = ['estado' => $estado];
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Profesor</span><h1 class="titulo-izquierda">Mis solicitudes de dirección</h1></div>
</div>
<div class="tabla-marco tabla-responsiva-tarjetas">
    <form class="filtros" role="search" method="get">
        <div><label class="form-label small mb-1" for="estado">Estado</label>
            <select class="form-select" id="estado" name="estado"><option value="">Todos</option>
                <?php foreach (ESTADOS['direccion'] as $k => [$t]): ?><option value="<?= $k ?>"<?= $estado === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <button type="submit" class="btn btn-secundario"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button>
    </form>
    <?php if (!$lista): ?>
        <div class="vacio p-4"><div class="vacio-icono"><i class="bi bi-send-check" aria-hidden="true"></i></div>
            <h3>No tienes solicitudes de dirección<?= $estado ? ' con ese estado' : '' ?></h3>
            <p><a href="<?= e(url('mod_profesor/proyectos.php')) ?>">Busca un proyecto</a> que quieras dirigir.</p></div>
    <?php else:
        $acciones = function (array $sd) use ($estado): string {
            $html = '<a class="btn btn-secundario btn-sm" href="' . e(url_lista('mod_profesor/solicitudes.php', ['estado' => $estado, 'ver' => $sd['c_solicitud_direccion']])) . '#detalle">Ver</a>';
            if ($sd['estado'] === 'pendiente') {
                $html .= ' <form method="post" class="d-inline">' . csrf_campo() . '<input type="hidden" name="c_solicitud" value="' . e($sd['c_solicitud_direccion']) . '">'
                       . '<button type="submit" class="btn btn-rechazar-contorno btn-sm" data-confirmar="¿Cancelar tu solicitud para dirigir «' . e($sd['titulo']) . '»?">Cancelar</button></form>';
            }
            return $html;
        }; ?>
        <div class="table-responsive"><table class="table tabla-datos table-striped mb-0">
            <thead><tr><th scope="col">Proyecto</th><th scope="col">Fecha</th><th scope="col">Estado</th><th scope="col" class="acciones">Acciones</th></tr></thead>
            <tbody><?php foreach ($lista as $sd): ?>
                <tr><td><strong><?= e($sd['titulo']) ?></strong><br><span class="folio"><?= e($sd['c_solicitud_direccion']) ?></span></td>
                    <td><?= e(fecha_corta($sd['fecha'])) ?></td><td><?= insignia('direccion', $sd['estado']) ?></td>
                    <td class="acciones text-nowrap"><?= $acciones($sd) ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <div class="lista-tarjetas"><?php foreach ($lista as $sd): ?>
            <div class="fila-tarjeta"><span class="titulo"><?= e($sd['titulo']) ?></span>
                <span class="meta"><?= e($sd['c_solicitud_direccion']) ?> · <?= e(fecha_corta($sd['fecha'])) ?></span>
                <span><?= insignia('direccion', $sd['estado']) ?></span><div><?= $acciones($sd) ?></div></div>
        <?php endforeach; ?></div>
    <?php endif; ?>
    <div class="pie-tabla"><?= pie_paginacion($pag, count($lista), 'mod_profesor/solicitudes.php', $filtros) ?></div>
</div>

<?php if ($detalle): ?>
<div class="row g-4 mt-1" id="detalle">
    <div class="col-lg-6"><div class="card">
        <div class="card-header"><h2 class="h3 mb-0">Historial · <?= e($detalle['titulo']) ?></h2></div>
        <div class="card-body"><ol class="linea-tiempo">
            <li><span class="hito"><i class="bi bi-send" aria-hidden="true"></i></span><div class="evento">Solicitud enviada</div><div class="cuando"><?= e(fecha_hora($detalle['fecha'])) ?></div></li>
            <?php if ($detalle['decision']): $ok = $detalle['decision'] === 'aprobada'; ?>
                <li><span class="hito <?= $ok ? 'exito' : 'peligro' ?>"><i class="bi <?= $ok ? 'bi-check-lg' : 'bi-x-lg' ?>" aria-hidden="true"></i></span>
                    <div class="evento">Revisión <?= insignia('direccion', $detalle['decision']) ?></div>
                    <div class="cuando"><?= e(fecha_hora($detalle['fecha_decision'])) ?> · <?= e($detalle['administrador']) ?></div>
                    <div class="justificacion <?= $ok ? '' : 'peligro' ?>"><span class="quien">Justificación del Tec</span><?= nl2br(e($detalle['justificacion_tec'])) ?></div></li>
            <?php elseif ($detalle['estado'] === 'cancelada'): ?>
                <li><span class="hito"><i class="bi bi-x-circle" aria-hidden="true"></i></span><div class="evento">Cancelada</div></li>
            <?php endif; ?>
        </ol></div></div></div>
    <div class="col-lg-6"><div class="card">
        <div class="card-header"><h2 class="h3 mb-0">Lo que enviaste</h2></div>
        <div class="card-body bloque-texto">
            <h3>Experiencia relacionada</h3><p><?= nl2br(e($detalle['experiencia'])) ?></p>
            <h3>Justificación</h3><p class="mb-0"><?= nl2br(e($detalle['justificacion'])) ?></p>
        </div></div></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
