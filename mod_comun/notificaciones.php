<?php
/** Pantalla C2 · Notificaciones (todos los roles). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_sesion();
$pdo = conexion();
$yo = $usuario['c_usuario'];

if (es_post()) {
    verificar_csrf();
    if (($_POST['accion'] ?? '') === 'todas') {
        $pdo->prepare('UPDATE notificacion SET leida = 1 WHERE c_usuario = ? AND leida = 0')->execute([$yo]);
        aviso('exito', 'Todas las notificaciones quedaron como leídas.');
    } elseif (($_POST['accion'] ?? '') === 'una' && is_string($_POST['id'] ?? null)) {
        // El filtro por c_usuario impide marcar notificaciones ajenas.
        $pdo->prepare('UPDATE notificacion SET leida = 1 WHERE c_notificacion = ? AND c_usuario = ?')->execute([$_POST['id'], $yo]);
    }
    $volver = 'mod_comun/notificaciones.php?' . http_build_query(array_filter([
        'filtro' => ($_GET['filtro'] ?? '') === 'no_leidas' ? 'no_leidas' : null,
        'pagina' => max(1, (int) ($_GET['pagina'] ?? 1)) > 1 ? (int) $_GET['pagina'] : null,
    ]));
    redirigir(rtrim($volver, '?'));
}

$solo_no_leidas = ($_GET['filtro'] ?? '') === 'no_leidas';
$por_pagina = 20;

$s = $pdo->prepare('SELECT COUNT(*) total, COALESCE(SUM(leida = 0), 0) sin_leer FROM notificacion WHERE c_usuario = ?');
$s->execute([$yo]);
['total' => $total, 'sin_leer' => $sin_leer] = array_map('intval', $s->fetch());

$total_lista = $solo_no_leidas ? $sin_leer : $total;
$paginas = max(1, (int) ceil($total_lista / $por_pagina));
$pagina = min($paginas, max(1, (int) ($_GET['pagina'] ?? 1)));
$desde = ($pagina - 1) * $por_pagina;

$s = $pdo->prepare('SELECT c_notificacion, mensaje, fecha, leida FROM notificacion
                     WHERE c_usuario = ?' . ($solo_no_leidas ? ' AND leida = 0' : '') . '
                     ORDER BY fecha DESC, c_notificacion DESC
                     LIMIT ' . (int) $por_pagina . ' OFFSET ' . (int) $desde);
$s->execute([$yo]);
$lista = $s->fetchAll();

$base = 'mod_comun/notificaciones.php';
$enlace = fn (int $p) => url($base . '?' . http_build_query(array_filter(['filtro' => $solo_no_leidas ? 'no_leidas' : null, 'pagina' => $p > 1 ? $p : null])));
$accion_form = e(url($base . '?' . http_build_query(array_filter(['filtro' => $solo_no_leidas ? 'no_leidas' : null, 'pagina' => $pagina > 1 ? $pagina : null]))));

$titulo = 'Notificaciones';
$menu_activo = 'mod_comun/notificaciones.php';
$migas = [['Panel', ruta_panel($usuario) . 'index.php'], ['Notificaciones', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div><h1 class="titulo-izquierda">Notificaciones</h1></div>
    <?php if ($sin_leer): ?>
        <div class="acciones">
            <form method="post" action="<?= $accion_form ?>">
                <?= csrf_campo() ?><input type="hidden" name="accion" value="todas">
                <button type="submit" class="btn btn-secundario">Marcar todas como leídas</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link<?= $solo_no_leidas ? '' : ' active" aria-current="page' ?>" href="<?= e(url($base)) ?>">Todas</a></li>
        <li class="nav-item"><a class="nav-link<?= $solo_no_leidas ? ' active" aria-current="page' : '' ?>" href="<?= e(url($base . '?filtro=no_leidas')) ?>">No leídas
            <?php if ($sin_leer): ?><span class="badge"><?= $sin_leer ?></span><?php endif; ?></a></li>
    </ul>
    
</div>

<?php if (!$lista): ?>
    <div class="card"><div class="card-body">
        <div class="vacio">
            <h2><?= $solo_no_leidas ? 'No tienes notificaciones sin leer' : 'Aún no tienes notificaciones' ?></h2>
            <p>Aquí verás los avisos sobre tus proyectos, solicitudes y asignaciones.</p>
        </div>
    </div></div>
<?php else: ?>
    <div class="list-group list-group-flush lista-notif">
        <?php foreach ($lista as $n): $nueva = !$n['leida']; ?>
            <div class="list-group-item d-flex gap-3 align-items-start<?= $nueva ? ' no-leida' : '' ?>">
                <span class="flex-grow-1">
                    <span class="d-block<?= $nueva ? ' fw-semibold' : '' ?>"><?= e($n['mensaje']) ?></span>
                    <span class="small text-mute"><?= e(fecha_relativa($n['fecha'])) ?></span>
                </span>
                <?php if ($nueva): ?>
                    <form method="post" action="<?= $accion_form ?>" class="flex-none">
                        <?= csrf_campo() ?><input type="hidden" name="accion" value="una"><input type="hidden" name="id" value="<?= e($n['c_notificacion']) ?>">
                        <button type="submit" class="btn btn-sm btn-terciario" title="Marcar como leída" aria-label="Marcar como leída">
                            <span class="punto-nueva"></span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3 small text-mute">
        <span>Mostrando <?= $desde + 1 ?>–<?= $desde + count($lista) ?> de <?= $total_lista ?></span>
        <?php if ($paginas > 1): ?>
            <nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">
                <li class="page-item<?= $pagina <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($enlace($pagina - 1)) ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Anterior</span></a></li>
                <?php for ($p = 1; $p <= $paginas; $p++): ?>
                    <?php if ($p === $pagina): ?><li class="page-item active" aria-current="page"><span class="page-link"><?= $p ?></span></li>
                    <?php else: ?><li class="page-item"><a class="page-link" href="<?= e($enlace($p)) ?>"><?= $p ?></a></li><?php endif; ?>
                <?php endfor; ?>
                <li class="page-item<?= $pagina >= $paginas ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($enlace($pagina + 1)) ?>"><i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Siguiente</span></a></li>
            </ul></nav>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
