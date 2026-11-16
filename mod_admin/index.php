<?php
/** Pantalla D0 · Panel del administrador. */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('administrador');
$pdo = conexion();

$uno = fn (string $sql) => (int) $pdo->query($sql)->fetchColumn();

// Trabajo pendiente
$por_revisar   = $uno("SELECT COUNT(*) FROM proyecto WHERE estado = 'en_revision'");
$candidaturas  = $uno("SELECT COUNT(*) FROM solicitud_candidatura WHERE estado = 'en_revision'");
$direccion     = $uno("SELECT COUNT(*) FROM solicitud_direccion WHERE estado = 'pendiente'");
// Publicados que ya tienen director y al menos un alumno asignado: listos para pasar a "en curso".
$listos = $uno("SELECT COUNT(*) FROM v_proyecto_resumen
                 WHERE estado = 'publicado' AND num_empleado_director IS NOT NULL AND alumnos_asignados > 0");

// Proyectos por estado
$por_estado = $pdo->query('SELECT estado, COUNT(*) n FROM proyecto GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);
$max_estado = max([1, ...array_map('intval', $por_estado)]);

$alumnos_activos = $uno("SELECT COUNT(*) FROM asignacion_alumno WHERE estado = 'activa'");
$profes_total    = $uno('SELECT COUNT(*) FROM profesor');
$profes_llenos   = $uno('SELECT COUNT(*) FROM v_carga_profesor WHERE proyectos_disponibles <= 0');

// Actividad reciente (historial)
$actividad = $pdo->query("SELECT h.operacion, h.entidad_afectada, h.registro_afectado, h.resultado, h.fecha, u.nombre
                            FROM historial_auditoria h JOIN usuario u ON u.c_usuario = h.c_usuario
                           ORDER BY h.fecha DESC, h.c_historial DESC LIMIT 6")->fetchAll();

$titulo = 'Panel del administrador';
$menu_activo = 'mod_admin/index.php';
$migas = [['Panel', null]];
require __DIR__ . '/../menumain/app_encabezado.php';

$pendientes = [
    ['Proyectos por revisar', 'Aprobar, devolver o rechazar lo que enviaron las empresas', $por_revisar, 'mod_admin/revisar_proyectos.php', 'Revisar'],
    ['Candidaturas por decidir', 'Alumnos ya evaluados por la empresa', $candidaturas, 'mod_admin/candidaturas.php', 'Decidir'],
    ['Solicitudes de dirección', 'Profesores que quieren dirigir un proyecto', $direccion, 'mod_admin/direccion.php', 'Decidir'],
    ['Listos para iniciar', 'Con director y alumnos asignados', $listos, 'mod_admin/proyectos.php?listos=1', 'Ver'],
];
$total_pendiente = $por_revisar + $candidaturas + $direccion + $listos;
$cifras = [
    ['Publicados', (int) ($por_estado['publicado'] ?? 0), 'mod_admin/proyectos.php?estado=publicado'],
    ['En curso', (int) ($por_estado['en_curso'] ?? 0), 'mod_admin/proyectos.php?estado=en_curso'],
    ['Alumnos asignados', $alumnos_activos, 'mod_admin/asignaciones.php'],
];
?>
<div class="encabezado-pagina">
    <div>
        <h1 class="titulo-izquierda">Panel del administrador</h1>
        <p><?= $total_pendiente ? $total_pendiente . ' asunto' . ($total_pendiente === 1 ? '' : 's') . ' esperan una decisión de la coordinación.' : 'No hay nada esperando una decisión de la coordinación.' ?></p>
    </div>
</div>

<div class="row">
    <?php foreach ($cifras as [$rotulo, $n, $ruta]): ?>
        <div class="col-6 col-lg-3"><div class="indicador<?= $n ? '' : ' cero' ?>"><span class="rotulo"><?= e($rotulo) ?></span><span class="valor"><?= $n ?></span>
            <a class="ver" href="<?= e(url($ruta)) ?>">Ver<span class="visually-hidden"> <?= e(mb_strtolower($rotulo)) ?></span></a></div></div>
    <?php endforeach; ?>
    <div class="col-6 col-lg-3"><div class="indicador"><span class="rotulo">Profesores con carga completa</span><span class="valor"><?= $profes_llenos ?> <small>de <?= $profes_total ?></small></span>
        <a class="ver" href="<?= e(url('mod_admin/usuarios.php?rol=profesor')) ?>">Ver<span class="visually-hidden"> profesores</span></a></div></div>
</div>

<div class="tablero">
    <div>
        <section class="card" aria-labelledby="t-pendiente">
            <div class="card-header"><h2 id="t-pendiente">Trabajo pendiente</h2></div>
            <ul class="bandeja">
                <?php foreach ($pendientes as [$rotulo, $detalle, $n, $ruta, $accion]): ?>
                    <li<?= $n ? '' : ' class="cero"' ?>><span class="cifra"><?= $n ?></span>
                        <span class="que"><strong><?= e($rotulo) ?></strong><span><?= e($detalle) ?></span></span>
                        <?php if ($n): ?><a class="btn btn-secundario btn-sm" href="<?= e(url($ruta)) ?>"><?= e($accion) ?><span class="visually-hidden">: <?= e($rotulo) ?></span></a><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <section class="card" aria-labelledby="t-estado">
            <div class="card-header"><h2 id="t-estado">Proyectos por estado</h2><a href="<?= e(url('mod_admin/reportes.php')) ?>">Ver reportes</a></div>
            <dl class="conteo-estados">
                <?php foreach (array_keys(ESTADOS['proyecto']) as $est): $n = (int) ($por_estado[$est] ?? 0); ?>
                    <div<?= $n ? '' : ' class="cero"' ?>>
                        <dt class="d-flex align-items-center gap-2"><span class="punto estado-<?= ESTADOS['proyecto'][$est][1] ?> e-<?= $est ?>" aria-hidden="true"></span><?= e(nombre_estado('proyecto', $est)) ?></dt>
                        <dd><span class="barra-reporte" aria-hidden="true"><span style="width:<?= round($n / $max_estado * 100) ?>%"></span></span><strong><?= $n ?></strong></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    </div>
    <div>
        <section class="card" aria-labelledby="t-actividad">
            <div class="card-header"><h2 id="t-actividad">Actividad reciente</h2><a href="<?= e(url('mod_admin/historial.php')) ?>">Ver historial</a></div>
            <div class="card-body">
                <?php if (!$actividad): ?>
                    <p class="text-mute mb-0">Aún no hay actividad registrada.</p>
                <?php else: ?>
                    <ol class="linea-tiempo">
                        <?php foreach ($actividad as $a):
                            [$clase, $icono] = match ($a['resultado']) {
                                'denegado' => ['peligro', 'bi-shield-exclamation'],
                                'error'    => ['advertencia', 'bi-exclamation-triangle'],
                                default    => ['exito', 'bi-check-lg'],
                            }; ?>
                            <li>
                                <span class="hito <?= $clase ?>"><i class="bi <?= $icono ?>" aria-hidden="true"></i></span>
                                <div class="evento"><?= e(describir_operacion($a)) ?></div>
                                <div class="cuando"><?= e(fecha_relativa($a['fecha'])) ?> · <?= e($a['nombre']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
