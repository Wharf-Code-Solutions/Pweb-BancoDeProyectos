<?php
/** Pantalla E0 · Panel de empresa. */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('empresa');
$pdo = conexion();
$rfc = $usuario['clave'];

$s = $pdo->prepare('SELECT estado, COUNT(*) FROM proyecto WHERE rfc_empresa = ? GROUP BY estado');
$s->execute([$rfc]);
$conteo = array_map('intval', $s->fetchAll(PDO::FETCH_KEY_PAIR));

// Requieren atención: proyectos devueltos y candidatos sin evaluar.
$s = $pdo->prepare("SELECT c_proyecto, titulo FROM proyecto WHERE rfc_empresa = ? AND estado = 'devuelto' ORDER BY fecha_registro DESC");
$s->execute([$rfc]);
$devueltos = $s->fetchAll();
$s = $pdo->prepare("SELECT p.c_proyecto, p.titulo, COUNT(*) AS sin_evaluar
    FROM solicitud_candidatura sc JOIN proyecto p ON p.c_proyecto = sc.c_proyecto
    WHERE p.rfc_empresa = ? AND sc.estado = 'pendiente' GROUP BY p.c_proyecto, p.titulo");
$s->execute([$rfc]);
$por_evaluar = $s->fetchAll();
$pendientes = count($devueltos) + count($por_evaluar);

// Actividad reciente: últimas notificaciones de la empresa.
$s = $pdo->prepare('SELECT mensaje, fecha FROM notificacion WHERE c_usuario = ? ORDER BY fecha DESC, c_notificacion DESC LIMIT 5');
$s->execute([$usuario['c_usuario']]);
$actividad = $s->fetchAll();

$titulo = 'Panel';
$menu_activo = 'mod_empresa/index.php';
$migas = [['Panel', null]];
require __DIR__ . '/../menumain/app_encabezado.php';

$tarjetas = ['borrador' => 'Borradores', 'en_revision' => 'En revisión', 'devuelto' => 'Devueltos', 'publicado' => 'Publicados', 'en_curso' => 'En curso'];
$clase_hito = fn (string $m) => match (icono_notificacion($m)) {
    'bi-arrow-return-left' => 'advertencia', 'bi-x-circle' => 'peligro', 'bi-check-circle' => 'exito', default => '',
};
?>
<div class="encabezado-pagina">
    <div>
        <h1 class="titulo-izquierda">Hola, <?= e(explode(' ', $usuario['nombre'])[0]) ?></h1>
        <p class="text-mute mb-0 mt-1"><?= e($usuario['subtitulo']) ?></p>
    </div>
    <div class="acciones"><a class="btn btn-principal" href="<?= e(url('mod_empresa/proyecto_form.php')) ?>">Nuevo proyecto</a></div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($tarjetas as $estado => $rotulo): $n = $conteo[$estado] ?? 0; ?>
        <div class="col-6 col-lg"><div class="indicador<?= $estado === 'devuelto' && $n ? ' pendiente' : '' ?>">
            <span class="rotulo"><?= e($rotulo) ?></span><span class="valor"><?= $n ?></span>
            <a class="ver" href="<?= e(url('mod_empresa/proyectos.php?estado=' . $estado)) ?>">Ver </a>
        </div></div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h2 class="h3 mb-0">Requieren tu atención</h2>
                <?php if ($pendientes): ?><span class="estado estado-advertencia"><?= $pendientes ?> pendiente<?= $pendientes > 1 ? 's' : '' ?></span><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$pendientes): ?>
                    <div class="vacio">
                        <h3>Todo al día</h3>
                        <p><?= array_sum($conteo) ? 'No tienes proyectos devueltos ni candidatos por evaluar.' : 'Registra tu primer proyecto para empezar.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($devueltos as $d): $dev = ultima_devolucion($d['c_proyecto']); ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                    <div><strong><?= e($d['titulo']) ?></strong> <span class="folio"><?= e($d['c_proyecto']) ?></span>
                                        <div class="mt-1"><?= insignia('proyecto', 'devuelto') ?></div></div>
                                    <a class="btn btn-secundario btn-sm" href="<?= e(url('mod_empresa/proyecto_form.php?id=' . urlencode($d['c_proyecto']))) ?>">Corregir y reenviar</a>
                                </div>
                                <?php if ($dev): ?>
                                    <div class="justificacion advertencia"><span class="quien">Correcciones solicitadas · <?= e(fecha_corta($dev['fecha'])) ?></span><?= nl2br(e($dev['justificacion'])) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($por_evaluar as $c): ?>
                            <div class="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2 align-items-center">
                                <div><strong><?= (int) $c['sin_evaluar'] ?> candidato<?= $c['sin_evaluar'] > 1 ? 's' : '' ?> sin evaluar</strong><div class="small text-mute"><?= e($c['titulo']) ?></div></div>
                                <a class="btn btn-secundario btn-sm" href="<?= e(url('mod_empresa/candidatos.php?id=' . urlencode($c['c_proyecto']))) ?>">Evaluar candidatos</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h2 class="h3 mb-0">Actividad reciente</h2>
                <a href="<?= e(url('mod_comun/notificaciones.php')) ?>" class="small fw-semibold">Ver todo</a>
            </div>
            <div class="card-body">
                <?php if (!$actividad): ?>
                    <p class="text-mute mb-0">Sin actividad todavía.</p>
                <?php else: ?>
                    <ol class="linea-tiempo">
                        <?php foreach ($actividad as $a): ?>
                            <li><span class="hito <?= $clase_hito($a['mensaje']) ?>"><i class="bi <?= icono_notificacion($a['mensaje']) ?>" aria-hidden="true"></i></span>
                                <div class="evento"><?= e($a['mensaje']) ?></div><div class="cuando"><?= e(fecha_relativa($a['fecha'])) ?></div></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
