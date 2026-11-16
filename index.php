<?php
/** Pantalla P1 · Inicio (pública). */
require_once __DIR__ . '/inc/inicio.php';

$usuario = usuario_actual();
$titulo = 'Inicio';
$indexar = true;
$descripcion = 'Banco de Proyectos Académicos del TecNM · ITVer: las empresas registran sus necesidades tecnológicas, los alumnos se postulan y los profesores dirigen los proyectos.';
require __DIR__ . '/menumain/publico_encabezado.php';

$perfiles = [
    'Empresas'   => 'Registran sus necesidades tecnológicas como proyectos, las envían a revisión, consultan el perfil de los candidatos y los evalúan con criterios ponderados.',
    'Alumnos'    => 'Registran sus habilidades y evidencias, buscan proyectos acordes a su perfil, se postulan hasta a tres a la vez y siguen el estado de cada solicitud.',
    'Profesores' => 'Solicitan la dirección de proyectos publicados, consultan los que dirigen y conocen a los alumnos asignados.',
];
?>
<section class="hero-publico">
    <div class="contenedor">
        <h1>Banco de Proyectos Académicos</h1>
        <p>Las empresas proponen proyectos tecnológicos, los alumnos de Sistemas Computacionales se postulan y el Tec autoriza cada asignación.</p>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <?php if ($usuario): ?>
                <a class="btn btn-principal" href="<?= e(url(ruta_panel($usuario))) ?>">Ir a mi panel</a>
            <?php else: ?>
                <a class="btn btn-principal" href="<?= e(url('login.php')) ?>">Iniciar sesión</a>
                <a class="btn btn-secundario" href="<?= e(url('registro_empresa.php')) ?>">Registrar mi empresa</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="seccion-publica" aria-labelledby="t-proceso">
    <div class="contenedor">
        <h2 id="t-proceso" class="titulo-seccion">Cómo funciona</h2>
        <ol class="pasos">
            <li><h3>Las empresas proponen</h3><p>Registran su necesidad tecnológica como proyecto y la envían a revisión del Tec.</p></li>
            <li><h3>Los alumnos se postulan</h3><p>Consultan los proyectos publicados y se postulan hasta a tres a la vez, según sus habilidades.</p></li>
            <li><h3>El Tec autoriza</h3><p>La administración aprueba cada proyecto y asigna a los alumnos y a un profesor director.</p></li>
        </ol>
    </div>
</section>

<section class="seccion-publica" aria-labelledby="t-perfiles">
    <div class="contenedor">
        <h2 id="t-perfiles" class="titulo-seccion">Quién usa el sistema</h2>
        <dl class="perfiles">
            <?php foreach ($perfiles as $nombre => $texto): ?>
                <div><dt><?= e($nombre) ?></dt><dd><?= e($texto) ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <p class="text-mute mt-3 mb-0">Las cuentas de alumnos y profesores las crea la administración del Tec.
            <?php if (!$usuario): ?>Las empresas pueden <a href="<?= e(url('registro_empresa.php')) ?>">registrarse aquí</a>.<?php endif; ?></p>
    </div>
</section>
<?php require __DIR__ . '/menumain/publico_pie.php'; ?>
