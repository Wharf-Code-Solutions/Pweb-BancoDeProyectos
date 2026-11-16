<?php
/** Pantalla R1 · Mi perfil (profesor). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('profesor');
$pdo = conexion();

$s = $pdo->prepare('SELECT p.num_empleado, p.especialidad, p.experiencia, u.nombre, u.correo, u.telefono
    FROM profesor p JOIN usuario u ON u.c_usuario = p.c_usuario WHERE p.c_usuario = ?');
$s->execute([$usuario['c_usuario']]);
$d = array_map(fn ($v) => (string) $v, $s->fetch());
$errores = [];

if (es_post()) {
    verificar_csrf();
    $d['especialidad'] = limpiar($_POST['especialidad'] ?? '');
    $d['experiencia']  = trim(str_replace("\r\n", "\n", (string) ($_POST['experiencia'] ?? '')));
    $d['telefono']     = preg_replace('/[\s\-()]/', '', (string) ($_POST['telefono'] ?? ''));
    $errores = array_filter([
        'especialidad' => validar_requerido($d['especialidad'], 'La especialidad') ?? (mb_strlen($d['especialidad']) > 150 ? 'Máximo 150 caracteres.' : null),
        'experiencia'  => mb_strlen($d['experiencia']) > 3000 ? 'Máximo 3000 caracteres.' : null,
        'telefono'     => validar_telefono($d['telefono']),
    ]);
    if (!$errores) {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE profesor SET especialidad = ?, experiencia = ? WHERE c_usuario = ?')
            ->execute([$d['especialidad'], $d['experiencia'] ?: null, $usuario['c_usuario']]);
        $pdo->prepare('UPDATE usuario SET telefono = ? WHERE c_usuario = ?')->execute([$d['telefono'] ?: null, $usuario['c_usuario']]);
        $pdo->commit();
        $_SESSION['usuario']['subtitulo'] = $d['especialidad'];
        registrar_auditoria('actualizar_datos', 'profesor', $d['num_empleado']);
        aviso('exito', 'Perfil actualizado.');
        redirigir('mod_profesor/perfil.php');
    }
}

$titulo = 'Mi perfil';
$menu_activo = 'mod_profesor/perfil.php';
$migas = [['Panel', 'mod_profesor/index.php'], ['Mi perfil', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$err = fn (string $k) => $errores[$k] ?? null;
?>
<div class="encabezado-pagina">
    <div><h1 class="titulo-izquierda">Mi perfil</h1></div>
</div>
<?= alerta_errores($errores) ?>
<form method="post" style="max-width:860px" novalidate>
    <?= csrf_campo() ?>
    <section class="seccion-form" aria-labelledby="id-t">
        <h2 id="id-t">Datos de identificación</h2>
        <div class="row g-3">
            <?= campo('ne', 'Número de empleado', $d['num_empleado'], ['solo_lectura' => true, 'clase_contenedor' => 'col-md-4']) ?>
            <?= campo('no', 'Nombre completo', $d['nombre'], ['solo_lectura' => true, 'clase_contenedor' => 'col-md-8']) ?>
            <?= campo('co', 'Correo electrónico', $d['correo'], ['solo_lectura' => true, 'clase_contenedor' => 'col-md-8']) ?>
        </div>
    </section>
    <section class="seccion-form" aria-labelledby="doc-t">
        <h2 id="doc-t">Perfil docente</h2>
        <div class="row g-3">
            <?= campo('especialidad', 'Especialidad', $d['especialidad'], ['placeholder' => 'Ej. Ingeniería de software', 'requerido' => true, 'maxlength' => 150, 'error' => $err('especialidad'), 'clase_contenedor' => 'col-12']) ?>
            <?= campo_area('experiencia', 'Experiencia', $d['experiencia'], ['placeholder' => 'Proyectos dirigidos, líneas de investigación, experiencia profesional…', 'opcional' => true, 'filas' => 4, 'error' => $err('experiencia'), 'clase_contenedor' => 'col-12',
                'ayuda' => 'Se muestra al administrador cuando solicitas dirigir un proyecto.']) ?>
            <?= campo('telefono', 'Teléfono', $d['telefono'], ['placeholder' => 'Ej. 2291234567', 'pattern' => '[0-9]{10,15}', 'title' => 'Solo dígitos, de 10 a 15.', 'inputmode' => 'tel', 'autocomplete' => 'tel', 'tipo' => 'tel', 'opcional' => true, 'maxlength' => 15, 'ayuda' => '10 dígitos.', 'error' => $err('telefono'), 'clase_contenedor' => 'col-md-6']) ?>
        </div>
    </section>
    <div class="barra-acciones-form">
        <a class="btn btn-terciario" href="<?= e(url('mod_profesor/index.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-principal">Guardar</button>
    </div>
</form>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
