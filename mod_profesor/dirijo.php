<?php
/** Pantalla R5 · Proyectos que dirijo (profesor). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('profesor');
$pdo = conexion();
$num = $usuario['clave'];

$s = $pdo->prepare("SELECT ap.estado AS estado_asignacion, ap.fecha_cierre, p.c_proyecto, p.titulo, p.estado, p.fecha_inicio, p.fecha_fin, e.razon_social
    FROM asignacion_profesor ap JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion
    JOIN proyecto p ON p.c_proyecto = sd.c_proyecto JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
    WHERE sd.num_empleado = ? ORDER BY ap.estado = 'activa' DESC, p.fecha_inicio DESC");
$s->execute([$num]);
$todos = $s->fetchAll();
$activos = array_values(array_filter($todos, fn ($x) => $x['estado_asignacion'] === 'activa'));
$historial = array_values(array_filter($todos, fn ($x) => $x['estado_asignacion'] !== 'activa'));
$pestana = ($_GET['tab'] ?? '') === 'historial' ? 'historial' : 'activos';
$lista = $pestana === 'activos' ? $activos : $historial;

$alumnos = $pdo->prepare("SELECT u.nombre, al.matricula, al.carrera, al.semestre, aa.estado
    FROM asignacion_alumno aa JOIN solicitud_candidatura sc ON sc.c_candidatura = aa.c_candidatura
    JOIN alumno al ON al.matricula = sc.matricula JOIN usuario u ON u.c_usuario = al.c_usuario
    WHERE sc.c_proyecto = ? ORDER BY aa.estado = 'activa' DESC, u.nombre");

$titulo = 'Proyectos que dirijo';
$menu_activo = 'mod_profesor/dirijo.php';
$migas = [['Panel', 'mod_profesor/index.php'], ['Proyectos que dirijo', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$n = count($activos);
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Profesor</span><h1 class="titulo-izquierda">Proyectos que dirijo</h1></div>
</div>
<div class="mb-3"><?= html_cupo("Proyectos activos: $n de " . MAX_DIRECCIONES, $n, MAX_DIRECCIONES,
    $n >= MAX_DIRECCIONES ? 'Sin lugares disponibles' : (MAX_DIRECCIONES - $n === 1 ? 'Te queda 1' : 'Te quedan ' . (MAX_DIRECCIONES - $n)), 'bi-briefcase') ?></div>
<ul class="nav nav-tabs mb-0">
    <li class="nav-item"><a class="nav-link<?= $pestana === 'activos' ? ' active" aria-current="page' : '' ?>" href="<?= e(url('mod_profesor/dirijo.php')) ?>">Activos
        <?php if ($activos): ?><span class="badge rounded-pill text-bg-primary"><?= count($activos) ?></span><?php endif; ?></a></li>
    <li class="nav-item"><a class="nav-link<?= $pestana === 'historial' ? ' active" aria-current="page' : '' ?>" href="<?= e(url('mod_profesor/dirijo.php?tab=historial')) ?>">Historial
        <?php if ($historial): ?><span class="badge rounded-pill text-bg-primary"><?= count($historial) ?></span><?php endif; ?></a></li>
</ul>
<div class="bg-white border border-top-0 p-3 d-grid gap-3" style="border-radius:0 0 8px 8px">
    <?php if (!$lista): ?>
        <div class="vacio"><div class="vacio-icono"><i class="bi bi-briefcase" aria-hidden="true"></i></div>
            <h3><?= $pestana === 'activos' ? 'No diriges proyectos activos' : 'Tu historial está vacío' ?></h3>
            <p><?= $pestana === 'activos' ? 'Solicita la dirección de un proyecto publicado; la administración la autorizará.' : 'Aquí verás los proyectos que ya terminaste de dirigir.' ?></p></div>
    <?php endif; ?>
    <?php foreach ($lista as $d): $alumnos->execute([$d['c_proyecto']]); $als = $alumnos->fetchAll(); ?>
        <div class="card"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                <div><h3 class="h4 mb-1"><a class="text-decoration-none" href="<?= e(url('mod_profesor/proyecto.php?id=' . urlencode($d['c_proyecto']))) ?>"><?= e($d['titulo']) ?></a></h3>
                    <span class="small text-mute"><?= e($d['razon_social']) ?> · <?= e(rango_fechas($d['fecha_inicio'], $d['fecha_fin'])) ?></span></div>
                <div class="d-flex gap-2 align-items-start"><?= insignia('proyecto', $d['estado']) ?>
                    <?= $d['estado_asignacion'] !== 'activa' ? insignia('asignacion', $d['estado_asignacion']) : '' ?></div>
            </div>
            <?php if (!$als): ?>
                <p class="text-mute small mb-0">Aún no hay alumnos asignados a este proyecto.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table tabla-datos mb-0">
                    <thead><tr><th scope="col">Alumno asignado</th><th scope="col">Carrera y semestre</th><th scope="col">Asignación</th></tr></thead>
                    <tbody><?php foreach ($als as $a): ?>
                        <tr><td><strong><?= e($a['nombre']) ?></strong><br><span class="folio"><?= e($a['matricula']) ?></span></td>
                            <td><?= e($a['carrera']) ?> · <?= (int) $a['semestre'] ?>.° semestre</td><td><?= insignia('asignacion', $a['estado']) ?></td></tr>
                    <?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div></div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
