<?php
/** Pantalla A1 · Mi perfil académico (alumno). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('alumno');
$pdo = conexion();

const CARRERAS_ALUMNO = ['Ing. en Sistemas Computacionales', 'Ing. en Tecnologías de la Información y Comunicaciones', 'Ing. Informática'];

$s = $pdo->prepare('SELECT a.matricula, a.carrera, a.semestre, a.datos_academicos, u.nombre, u.correo, u.telefono
    FROM alumno a JOIN usuario u ON u.c_usuario = a.c_usuario WHERE a.c_usuario = ?');
$s->execute([$usuario['c_usuario']]);
$d = array_map(fn ($v) => (string) $v, $s->fetch());
$carreras = array_unique([...CARRERAS_ALUMNO, $d['carrera']]);
$errores = [];

if (es_post()) {
    verificar_csrf();
    $d['carrera']          = limpiar($_POST['carrera'] ?? '');
    $d['semestre']         = (string) (int) ($_POST['semestre'] ?? 0);
    $d['datos_academicos'] = trim(str_replace("\r\n", "\n", (string) ($_POST['datos_academicos'] ?? '')));
    $d['telefono']         = preg_replace('/[\s\-()]/', '', (string) ($_POST['telefono'] ?? ''));
    $errores = array_filter([
        'carrera'          => in_array($d['carrera'], $carreras, true) ? null : 'Elige tu carrera.',
        'semestre'         => ($d['semestre'] >= 1 && $d['semestre'] <= 12) ? null : 'Elige un semestre del 1 al 12.',
        'datos_academicos' => mb_strlen($d['datos_academicos']) > 2000 ? 'Máximo 2000 caracteres.' : null,
        'telefono'         => validar_telefono($d['telefono']),
    ]);
    if (!$errores) {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE alumno SET carrera = ?, semestre = ?, datos_academicos = ? WHERE c_usuario = ?')
            ->execute([$d['carrera'], (int) $d['semestre'], $d['datos_academicos'] ?: null, $usuario['c_usuario']]);
        $pdo->prepare('UPDATE usuario SET telefono = ? WHERE c_usuario = ?')->execute([$d['telefono'] ?: null, $usuario['c_usuario']]);
        $pdo->commit();
        $_SESSION['usuario']['subtitulo'] = $d['carrera'] . ' · ' . $d['semestre'] . '.° semestre';
        registrar_auditoria('actualizar_datos', 'alumno', $d['matricula']);
        aviso('exito', 'Perfil actualizado.');
        redirigir('mod_alumno/perfil.php');
    }
}

$titulo = 'Mi perfil académico';
$menu_activo = 'mod_alumno/perfil.php';
$migas = [['Panel', 'mod_alumno/index.php'], ['Mi perfil', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$err = fn (string $k) => $errores[$k] ?? null;
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Alumno</span><h1 class="titulo-izquierda">Mi perfil académico</h1></div>
</div>
<?= alerta_errores($errores) ?>
<form method="post" style="max-width:860px" novalidate>
    <?= csrf_campo() ?>
    <section class="seccion-form" aria-labelledby="id-t">
        <h2 id="id-t">Datos de identificación</h2>
        <div class="row g-3">
            <?= campo('ma', 'Matrícula', $d['matricula'], ['solo_lectura' => true, 'clase_contenedor' => 'col-md-4']) ?>
            <?= campo('no', 'Nombre completo', $d['nombre'], ['solo_lectura' => true, 'clase_contenedor' => 'col-md-8']) ?>
            <?= campo('co', 'Correo electrónico', $d['correo'], ['solo_lectura' => true, 'clase_contenedor' => 'col-md-8']) ?>
        </div>
        <p class="form-text mb-0 mt-2"><i class="bi bi-info-circle" aria-hidden="true"></i> Estos datos los registra la administración del Tec.</p>
    </section>
    <section class="seccion-form" aria-labelledby="aca-t">
        <h2 id="aca-t">Datos académicos</h2>
        <div class="row g-3">
            <?= campo_select('carrera', 'Carrera', $d['carrera'], $carreras, ['requerido' => true, 'error' => $err('carrera'), 'clase_contenedor' => 'col-md-8']) ?>
            <?= campo_select('semestre', 'Semestre', $d['semestre'], array_combine(range(1, 12), range(1, 12)), ['requerido' => true, 'error' => $err('semestre'), 'clase_contenedor' => 'col-md-4']) ?>
            <?= campo_area('datos_academicos', 'Datos académicos', $d['datos_academicos'], ['placeholder' => 'Ej. Promedio 9.1, inglés B2, especialidad en desarrollo web', 'opcional' => true, 'filas' => 3, 'error' => $err('datos_academicos'), 'clase_contenedor' => 'col-12',
                'ayuda' => 'Promedio, especialidad, idiomas u otros datos que ayuden a las empresas a conocerte.']) ?>
            <?= campo('telefono', 'Teléfono', $d['telefono'], ['placeholder' => 'Ej. 2291234567', 'pattern' => '[0-9]{10,15}', 'title' => 'Solo dígitos, de 10 a 15.', 'inputmode' => 'tel', 'autocomplete' => 'tel', 'tipo' => 'tel', 'opcional' => true, 'maxlength' => 15, 'ayuda' => '10 dígitos.', 'error' => $err('telefono'), 'clase_contenedor' => 'col-md-6']) ?>
        </div>
    </section>
    <div class="barra-acciones-form">
        <a class="btn btn-terciario" href="<?= e(url('mod_alumno/index.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-principal">Guardar</button>
    </div>
</form>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
