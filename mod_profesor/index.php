<?php
/** Pantalla R0 · Panel del profesor. */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('profesor');
$pdo = conexion();
$num = $usuario['clave'];

$activos = direcciones_activas($num);

$s = $pdo->prepare("SELECT p.c_proyecto, p.titulo, p.estado, r.razon_social, r.alumnos_asignados
    FROM asignacion_profesor ap JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion
    JOIN proyecto p ON p.c_proyecto = sd.c_proyecto JOIN v_proyecto_resumen r ON r.c_proyecto = p.c_proyecto
    WHERE sd.num_empleado = ? AND ap.estado = 'activa' ORDER BY p.fecha_inicio");
$s->execute([$num]);
$dirijo = $s->fetchAll();
$alumnos = array_sum(array_map(fn ($d) => (int) $d['alumnos_asignados'], $dirijo));

$s = $pdo->prepare("SELECT sd.c_solicitud_direccion, sd.fecha, sd.estado, p.titulo, a.fecha AS fecha_decision
    FROM solicitud_direccion sd JOIN proyecto p ON p.c_proyecto = sd.c_proyecto
    LEFT JOIN autorizacion a ON a.c_solicitud_direccion = sd.c_solicitud_direccion
    WHERE sd.num_empleado = ? ORDER BY sd.fecha DESC LIMIT 4");
$s->execute([$num]);
$solicitudes = $s->fetchAll();
$s = $pdo->prepare("SELECT COUNT(*) FROM solicitud_direccion WHERE num_empleado = ? AND estado = 'pendiente'");
$s->execute([$num]);
$pendientes = (int) $s->fetchColumn();

$titulo = 'Panel';
$menu_activo = 'mod_profesor/index.php';
$migas = [['Panel', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
// "Hola, Mtra. Laura": conserva el título académico si lo hay.
$partes = explode(' ', $usuario['nombre']);
$saludo = preg_match('/^(Mtr[oa]|Dr[a]?|Ing|Lic)\.?$/i', $partes[0] ?? '') && isset($partes[1]) ? $partes[0] . ' ' . $partes[1] : $partes[0];
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Profesor</span><h1 class="titulo-izquierda">Hola, <?= e($saludo) ?></h1></div>
    <div class="acciones"><a class="btn btn-principal" href="<?= e(url('mod_profesor/proyectos.php')) ?>"><i class="bi bi-search" aria-hidden="true"></i>Ver proyectos disponibles</a></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="indicador"><span class="rotulo">Proyectos que dirige</span><span class="valor"><?= $activos ?> <small>de <?= MAX_DIRECCIONES ?></small></span>
        <div class="progress" role="progressbar" aria-label="Proyectos que dirige" aria-valuenow="<?= $activos ?>" aria-valuemin="0" aria-valuemax="<?= MAX_DIRECCIONES ?>">
            <div class="progress-bar" style="width:<?= round($activos / MAX_DIRECCIONES * 100) ?>%"></div></div>
        <a class="ver" href="<?= e(url('mod_profesor/dirijo.php')) ?>">Ver proyectos que dirijo <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></div>
    <div class="col-md-4"><div class="indicador"><span class="rotulo">Solicitudes de dirección pendientes</span><span class="valor"><?= $pendientes ?></span>
        <a class="ver" href="<?= e(url('mod_profesor/solicitudes.php?estado=pendiente')) ?>">Ver solicitudes <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></div>
    <div class="col-md-4"><div class="indicador"><span class="rotulo">Alumnos a su cargo</span><span class="valor"><?= $alumnos ?></span>
        <a class="ver" href="<?= e(url('mod_profesor/dirijo.php')) ?>">Ver <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7"><div class="card">
        <div class="card-header d-flex justify-content-between align-items-center gap-2"><h2 class="h3 mb-0">Proyectos que dirijo</h2>
            <a href="<?= e(url('mod_profesor/dirijo.php')) ?>" class="small fw-semibold">Ver todos</a></div>
        <div class="card-body">
            <?php if (!$dirijo): ?>
                <div class="vacio"><div class="vacio-icono"><i class="bi bi-briefcase" aria-hidden="true"></i></div>
                    <h3>Aún no diriges proyectos</h3><p>Solicita la dirección de un proyecto publicado; la administración la autorizará.</p></div>
            <?php else: ?>
                <div class="list-group list-group-flush"><?php foreach ($dirijo as $d): ?>
                    <div class="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2 align-items-center">
                        <div><strong class="d-block"><?= e($d['titulo']) ?></strong>
                            <span class="small text-mute"><?= e($d['razon_social']) ?> · <?= (int) $d['alumnos_asignados'] ?> alumno<?= (int) $d['alumnos_asignados'] === 1 ? '' : 's' ?></span></div>
                        <div class="d-flex gap-2 align-items-center"><?= insignia('proyecto', $d['estado']) ?>
                            <a class="btn btn-secundario btn-sm" href="<?= e(url('mod_profesor/proyecto.php?id=' . urlencode($d['c_proyecto']))) ?>">Ver</a></div>
                    </div>
                <?php endforeach; ?></div>
            <?php endif; ?>
        </div></div></div>
    <div class="col-lg-5"><div class="card">
        <div class="card-header"><h2 class="h3 mb-0">Solicitudes de dirección recientes</h2></div>
        <div class="card-body">
            <?php if (!$solicitudes): ?>
                <p class="text-mute mb-0">Aún no has solicitado dirigir proyectos.</p>
            <?php else: ?>
                <div class="list-group list-group-flush"><?php foreach ($solicitudes as $sd):
                    $cuando = match ($sd['estado']) {
                        'aprobada' => 'Aprobada el ', 'rechazada' => 'Rechazada el ', default => 'Enviada el ',
                    } . fecha_corta($sd['fecha_decision'] ?? $sd['fecha']); ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between gap-2"><strong><?= e($sd['titulo']) ?></strong><?= insignia('direccion', $sd['estado']) ?></div>
                        <span class="small text-mute"><?= e($cuando) ?></span>
                    </div>
                <?php endforeach; ?></div>
            <?php endif; ?>
        </div></div></div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
