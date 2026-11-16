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
    ['Proyectos por revisar', $por_revisar, 'mod_admin/revisar_proyectos.php', 'Revisar'],
    ['Candidaturas por decidir', $candidaturas, 'mod_admin/candidaturas.php', 'Decidir'],
    ['Solicitudes de dirección', $direccion, 'mod_admin/direccion.php', 'Decidir'],
    ['Proyectos listos para iniciar', $listos, 'mod_admin/proyectos.php?listos=1', 'Ver'],
];
$con_trabajo = array_filter($pendientes, fn ($p) => $p[1] > 0);
$sin_trabajo = array_filter($pendientes, fn ($p) => $p[1] === 0);
?>
<div class="encabezado-pagina">
    <div><h1 class="titulo-izquierda">Panel del administrador</h1></div>
</div>

<section class="bloque" aria-labelledby="t-pendiente">
    <h2 id="t-pendiente">Trabajo pendiente</h2>
    <?php if ($con_trabajo): ?>
        <ul class="lista-pendientes">
            <?php foreach ($con_trabajo as [$rotulo, $n, $ruta, $accion]): ?>
                <li><span class="cifra"><?= $n ?></span><span class="que"><?= e($rotulo) ?></span>
                    <a class="btn btn-secundario btn-sm" href="<?= e(url($ruta)) ?>"><?= e($accion) ?><span class="visually-hidden">: <?= e($rotulo) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="sin-pendientes">No hay nada esperando una decisión.</p>
    <?php endif; ?>
    <?php if ($sin_trabajo && $con_trabajo): ?>
        <p class="text-mute small mt-2 mb-0">Sin pendientes: <?= e(implode(', ', array_map(fn ($p) => mb_strtolower($p[0]), $sin_trabajo))) ?>.</p>
    <?php endif; ?>
</section>

<div class="row g-5">
    <div class="col-lg-7">
        <section class="bloque" aria-labelledby="t-estado">
            <div class="bloque-cab">
                <h2 id="t-estado">Proyectos por estado</h2>
                <a href="<?= e(url('mod_admin/reportes.php')) ?>">Ver reportes</a>
            </div>
            <dl class="conteo-estados">
                <?php foreach (array_keys(ESTADOS['proyecto']) as $est): $n = (int) ($por_estado[$est] ?? 0); ?>
                    <div<?= $n ? '' : ' class="cero"' ?>>
                        <dt><span class="punto estado-<?= ESTADOS['proyecto'][$est][1] ?>" aria-hidden="true"></span><?= e(nombre_estado('proyecto', $est)) ?></dt>
                        <dd><span class="barra-reporte" aria-hidden="true"><span style="width:<?= round($n / $max_estado * 100) ?>%"></span></span><strong><?= $n ?></strong></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
        <section class="bloque" aria-labelledby="t-carga">
            <h2 id="t-carga">Asignaciones</h2>
            <dl class="conteo-estados">
                <div><dt><a href="<?= e(url('mod_admin/asignaciones.php')) ?>">Alumnos asignados activos</a></dt><dd><strong><?= $alumnos_activos ?></strong></dd></div>
                <div><dt><a href="<?= e(url('mod_admin/usuarios.php?rol=profesor')) ?>">Profesores con carga completa</a></dt><dd><strong><?= $profes_llenos ?></strong>&nbsp;<span class="text-mute">de <?= $profes_total ?></span></dd></div>
            </dl>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="bloque" aria-labelledby="t-actividad">
            <div class="bloque-cab">
                <h2 id="t-actividad">Actividad reciente</h2>
                <a href="<?= e(url('mod_admin/historial.php')) ?>">Ver historial</a>
            </div>
            <?php if (!$actividad): ?>
                <p class="text-mute mb-0">Aún no hay actividad registrada.</p>
            <?php else: ?>
                <ol class="linea-tiempo">
                    <?php foreach ($actividad as $a):
                        $clase = match ($a['resultado']) { 'denegado' => 'peligro', 'error' => 'advertencia', default => 'exito' }; ?>
                        <li>
                            <span class="hito <?= $clase ?>"></span>
                            <div class="evento"><?= e(describir_operacion($a)) ?></div>
                            <div class="cuando"><?= e(fecha_relativa($a['fecha'])) ?> · <?= e($a['nombre']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
