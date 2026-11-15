<?php
/** Panel del rol alumno (se completa en su fase del plan). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('alumno');

$titulo = 'Panel';
$menu_activo = 'mod_alumno/index.php';
$migas = [['Panel', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div>
        <span class="etiqueta">Alumno</span>
        <h1 class="titulo-izquierda">Hola, <?= e(explode(' ', $usuario['nombre'])[0]) ?></h1>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="vacio">
            <div class="vacio-icono"><i class="bi bi-tools" aria-hidden="true"></i></div>
            <h3>Panel en construcción</h3>
            <p>Esta sección se completa en una fase posterior del plan de trabajo.</p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
