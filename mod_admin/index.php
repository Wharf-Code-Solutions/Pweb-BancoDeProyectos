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

$indicador = function (string $rotulo, int $valor, string $ruta, string $accion, bool $pendiente = false): string {
    return '<div class="col-6 col-lg-3"><div class="indicador' . ($pendiente && $valor > 0 ? ' pendiente' : '') . '">'
         . '<span class="rotulo">' . e($rotulo) . '</span><span class="valor">' . $valor . '</span>'
         . '<a class="ver" href="' . e(url($ruta)) . '">' . e($accion) . ' <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></div>';
};
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Administración</span><h1 class="titulo-izquierda">Panel del administrador</h1></div>
</div>

<h2 class="h3 mb-3">Trabajo pendiente</h2>
<div class="row g-3 mb-4">
    <?= $indicador('Proyectos por revisar', $por_revisar, 'mod_admin/revisar_proyectos.php', 'Revisar', true) ?>
    <?= $indicador('Candidaturas por decidir', $candidaturas, 'mod_admin/candidaturas.php', 'Decidir', true) ?>
    <?= $indicador('Solicitudes de dirección', $direccion, 'mod_admin/direccion.php', 'Decidir', true) ?>
    <?= $indicador('Listos para iniciar', $listos, 'mod_admin/proyectos.php?estado=publicado', 'Ver') ?>
</div>

<div class="row g-4">
    <div class="col-lg-7 d-grid gap-4 align-self-start">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h2 class="h3 mb-0">Proyectos por estado</h2>
                <a href="<?= e(url('mod_admin/reportes.php')) ?>" class="small fw-semibold">Ver reportes</a>
            </div>
            <div class="card-body">
                <?php foreach (array_keys(ESTADOS['proyecto']) as $est): $n = (int) ($por_estado[$est] ?? 0); ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1"><span><?= insignia('proyecto', $est) ?></span><strong><?= $n ?></strong></div>
                        <div class="barra-reporte"><span style="width:<?= round($n / $max_estado * 100) ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><div class="indicador"><span class="rotulo">Alumnos asignados activos</span><span class="valor"><?= $alumnos_activos ?></span>
                <a class="ver" href="<?= e(url('mod_admin/asignaciones.php')) ?>">Ver asignaciones <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></div>
            <div class="col-md-6"><div class="indicador"><span class="rotulo">Profesores con carga completa</span><span class="valor"><?= $profes_llenos ?> <small>de <?= $profes_total ?></small></span>
                <a class="ver" href="<?= e(url('mod_admin/usuarios.php?rol=profesor')) ?>">Ver <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h2 class="h3 mb-0">Actividad reciente</h2>
                <a href="<?= e(url('mod_admin/historial.php')) ?>" class="small fw-semibold">Ver historial</a>
            </div>
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
        </div>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
