<?php
/** Pantalla P1 · Inicio (pública). */
require_once __DIR__ . '/inc/inicio.php';

$usuario = usuario_actual();
$titulo = 'Inicio';
$indexar = true;
$descripcion = 'Banco de Proyectos Académicos del TecNM · ITVer: las empresas registran sus necesidades tecnológicas, los alumnos se postulan y los profesores dirigen los proyectos.';
require __DIR__ . '/menumain/publico_encabezado.php';

$icono_rol = 'width:48px;height:48px;font-size:1.4rem;border-radius:50%;display:grid;place-items:center;background:var(--primario-claro-bg);color:var(--navy)';
$roles = [
    ['bi-building', 'Empresas', ['Registrar proyectos y enviarlos a revisión', 'Revisar el perfil de los candidatos', 'Evaluarlos con criterios ponderados']],
    ['bi-mortarboard', 'Alumnos', ['Registrar sus habilidades y evidencias', 'Buscar proyectos acordes a su perfil', 'Postularse y seguir su solicitud']],
    ['bi-person-workspace', 'Profesores', ['Solicitar la dirección de proyectos', 'Consultar los proyectos que dirigen', 'Conocer a los alumnos asignados']],
];
?>
<section class="hero-publico">
    <div class="contenedor">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="etiqueta" style="color:var(--yel)">TecNM · Instituto Tecnológico de Veracruz</span>
                <h1>Banco de Proyectos Académicos</h1>
                <p>Las empresas proponen proyectos tecnológicos, los alumnos de Sistemas Computacionales se postulan y el Tec autoriza cada asignación.</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <?php if ($usuario): ?>
                        <a class="btn btn-principal" href="<?= e(url(ruta_panel($usuario))) ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i>Ir a mi panel</a>
                    <?php else: ?>
                        <a class="btn btn-principal" href="<?= e(url('login.php')) ?>"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Iniciar sesión</a>
                        <a class="btn btn-claro" href="<?= e(url('registro_empresa.php')) ?>"><i class="bi bi-building-add" aria-hidden="true"></i>Registrar mi empresa</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block">
                <div style="aspect-ratio:4/3;border-radius:10px;background:var(--deep);display:grid;place-items:center;color:#9fb3d3;font-size:.875rem;border:1px dashed rgba(255,255,255,.3)">
                    <span><i class="bi bi-image" aria-hidden="true"></i> Imagen institucional (opcional)</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="seccion-publica">
    <div class="contenedor">
        <h2 class="titulo-seccion">¿Cómo funciona?</h2>
        <div class="row g-4">
            <div class="col-md-4"><div class="paso"><div class="n">1</div><h3>Las empresas proponen</h3><p>Registran su necesidad tecnológica como proyecto y la envían a revisión del Tec.</p></div></div>
            <div class="col-md-4"><div class="paso"><div class="n">2</div><h3>Los alumnos se postulan</h3><p>Consultan los proyectos publicados y se postulan hasta a tres a la vez, según sus habilidades.</p></div></div>
            <div class="col-md-4"><div class="paso"><div class="n">3</div><h3>El Tec autoriza</h3><p>La administración aprueba cada proyecto y asigna a los alumnos y a un profesor director.</p></div></div>
        </div>
    </div>
</section>

<section class="seccion-publica alterna">
    <div class="contenedor">
        <h2 class="titulo-seccion">¿Qué puede hacer en el sistema?</h2>
        <div class="row g-4">
            <?php foreach ($roles as [$icono, $nombre, $acciones]): ?>
                <div class="col-md-4">
                    <div class="card h-100"><div class="card-body p-4">
                        <div class="cupo-icono mb-3" style="<?= $icono_rol ?>"><i class="bi <?= $icono ?>" aria-hidden="true"></i></div>
                        <h3 class="card-title"><?= e($nombre) ?></h3>
                        <ul class="verificaciones">
                            <?php foreach ($acciones as $a): ?><li><?= e($a) ?></li><?php endforeach; ?>
                        </ul>
                    </div></div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-center text-mute mt-4 mb-0">Alumnos y profesores: su cuenta la crea la administración del Tec.</p>
        <?php if (!$usuario): ?>
            <div class="d-flex justify-content-center mt-4">
                <a class="btn btn-principal" href="<?= e(url('registro_empresa.php')) ?>"><i class="bi bi-building-add" aria-hidden="true"></i>Registrar mi empresa</a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/menumain/publico_pie.php'; ?>
