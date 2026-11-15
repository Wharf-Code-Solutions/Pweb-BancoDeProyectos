<?php
/** Pantalla R2 · Proyectos disponibles para dirección (profesor). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('profesor');
$pdo = conexion();
$num = $usuario['clave'];

$filtros = filtros_proyectos();
// "Solo sin director" viene marcada la primera vez; después respeta lo que elija el profesor.
$sin_director = isset($_GET['filtrado']) ? (($_GET['sin_director'] ?? '') === '1') : true;
$extra = $sin_director ? ['r.num_empleado_director IS NULL'] : [];

$activos = direcciones_activas($num);
$s = $pdo->prepare("SELECT c_proyecto, estado FROM solicitud_direccion WHERE num_empleado = ? AND estado IN ('pendiente', 'aprobada')");
$s->execute([$num]);
$mias = $s->fetchAll(PDO::FETCH_KEY_PAIR);
$lista = proyectos_disponibles($filtros, $extra);

$titulo = 'Proyectos disponibles para dirección';
$menu_activo = 'mod_profesor/proyectos.php';
$migas = [['Panel', 'mod_profesor/index.php'], ['Proyectos disponibles', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$restan = MAX_DIRECCIONES - $activos;
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Profesor</span><h1 class="titulo-izquierda">Proyectos disponibles para dirección</h1></div>
</div>
<div class="mb-3"><?= html_cupo("Proyectos que diriges: $activos de " . MAX_DIRECCIONES, $activos, MAX_DIRECCIONES,
    $restan > 0 ? "Puedes dirigir $restan más" : 'Ya diriges el máximo de proyectos', 'bi-briefcase') ?></div>
<?= html_filtros_proyectos($filtros, ['sin_director', 'Solo proyectos sin director', $sin_director]) ?>

<?php if (!$lista): ?>
    <div class="card"><div class="card-body"><div class="vacio">
        <div class="vacio-icono"><i class="bi bi-search" aria-hidden="true"></i></div>
        <h3>No hay proyectos que coincidan</h3>
        <p><?= $sin_director ? 'Todos los proyectos publicados ya tienen director, o prueba con otros filtros.' : 'Prueba con otros filtros.' ?></p>
    </div></div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($lista as $p):
            $ver = '<a href="' . e(url('mod_profesor/proyecto.php?id=' . urlencode($p['c_proyecto']))) . '" class="btn btn-secundario btn-sm">Ver proyecto</a>';
            $izq = match (true) {
                isset($mias[$p['c_proyecto']]) => '<span class="ya-postulado"><i class="bi bi-check2" aria-hidden="true"></i>Solicitud ' . e(mb_strtolower(nombre_estado('direccion', $mias[$p['c_proyecto']]))) . '</span>',
                (bool) $p['director']          => '<span class="small text-mute"><i class="bi bi-person-badge" aria-hidden="true"></i> Director: ' . e($p['director']) . '</span>',
                default                        => '<span class="coincidencia ok"><i class="bi bi-person-check" aria-hidden="true"></i>Sin director</span>',
            }; ?>
            <div class="col-lg-6"><?= html_tarjeta_proyecto($p, html_chips_requisitos(requisitos_proyecto($p['c_proyecto'])), $izq . $ver) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
