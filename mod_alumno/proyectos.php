<?php
/** Pantalla A3 · Proyectos disponibles (alumno). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('alumno');
$pdo = conexion();
$matricula = $usuario['clave'];

$filtros = filtros_proyectos();
$solo_cumplo = ($_GET['cumplo'] ?? '') === '1';

$perfil = perfil_alumno($matricula);
$vigentes = candidaturas_vigentes($matricula);

// Mis candidaturas activas por proyecto, para marcar "Ya te postulaste".
$s = $pdo->prepare("SELECT c_proyecto, estado FROM solicitud_candidatura WHERE matricula = ? AND estado IN ('pendiente', 'en_revision', 'aprobada')");
$s->execute([$matricula]);
$mias = $s->fetchAll(PDO::FETCH_KEY_PAIR);

$tarjetas = [];
foreach (proyectos_disponibles($filtros) as $p) {
    $c = coincidencia(requisitos_proyecto($p['c_proyecto']), $perfil);
    if ($solo_cumplo && $c['cumplidas'] < $c['obligatorias']) {
        continue;
    }
    $tarjetas[] = [$p, $c];
}

$titulo = 'Proyectos disponibles';
$menu_activo = 'mod_alumno/proyectos.php';
$migas = [['Panel', 'mod_alumno/index.php'], ['Proyectos disponibles', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$restan = MAX_CANDIDATURAS - $vigentes;
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Alumno</span><h1 class="titulo-izquierda">Proyectos disponibles</h1></div>
</div>
<div class="mb-3"><?= html_cupo("Candidaturas vigentes: $vigentes de " . MAX_CANDIDATURAS, $vigentes, MAX_CANDIDATURAS,
    $restan > 0 ? "Puedes postularte a $restan más" : 'Sin lugares disponibles') ?></div>
<?= html_filtros_proyectos($filtros, ['cumplo', 'Solo donde cumplo las habilidades obligatorias', $solo_cumplo]) ?>

<?php if (!$tarjetas): ?>
    <div class="card"><div class="card-body"><div class="vacio">
        <div class="vacio-icono"><i class="bi bi-search" aria-hidden="true"></i></div>
        <h2>No hay proyectos que coincidan</h2>
        <p><?= array_filter($filtros) || $solo_cumplo ? 'Prueba con otros filtros.' : 'Por ahora no hay proyectos publicados. Vuelve pronto.' ?></p>
    </div></div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($tarjetas as [$p, $c]):
            $ver = '<a href="' . e(url('mod_alumno/proyecto.php?id=' . urlencode($p['c_proyecto']))) . '" class="btn btn-secundario btn-sm">Ver proyecto</a>';
            $estado_mio = $mias[$p['c_proyecto']] ?? null;
            $izq = $estado_mio
                ? '<span class="ya-postulado"><i class="bi bi-check2" aria-hidden="true"></i>Ya te postulaste · ' . e(nombre_estado('candidatura', $estado_mio)) . '</span>'
                : html_coincidencia($c); ?>
            <div class="col-lg-6"><?= html_tarjeta_proyecto($p, $c['chips'], $izq . $ver) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
