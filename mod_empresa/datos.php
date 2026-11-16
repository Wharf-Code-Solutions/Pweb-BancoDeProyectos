<?php
/** Pantalla E6 · Datos de la empresa. */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('empresa');
$pdo = conexion();

$s = $pdo->prepare('SELECT e.rfc_empresa, e.razon_social, e.sector, u.nombre, u.telefono, u.correo
    FROM empresa e JOIN usuario u ON u.c_usuario = e.c_usuario WHERE e.c_usuario = ?');
$s->execute([$usuario['c_usuario']]);
$d = array_map('strval', $s->fetch());
$errores = [];

if (es_post()) {
    verificar_csrf();
    $d['razon_social'] = limpiar($_POST['razon_social'] ?? '');
    $d['sector']       = limpiar($_POST['sector'] ?? '');
    $d['nombre']       = limpiar($_POST['nombre'] ?? '');
    $d['telefono']     = preg_replace('/[\s\-()]/', '', (string) ($_POST['telefono'] ?? ''));
    $errores = array_filter([
        'razon_social' => validar_requerido($d['razon_social'], 'La razón social') ?? (mb_strlen($d['razon_social']) > 200 ? 'Máximo 200 caracteres.' : null),
        'sector'       => in_array($d['sector'], SECTORES_EMPRESA, true) ? null : 'Elige un sector.',
        'nombre'       => validar_requerido($d['nombre'], 'El nombre') ?? (mb_strlen($d['nombre']) > 120 ? 'Máximo 120 caracteres.' : null),
        'telefono'     => validar_telefono($d['telefono']),
    ]);
    if (!$errores) {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE empresa SET razon_social = ?, sector = ? WHERE c_usuario = ?')
            ->execute([$d['razon_social'], $d['sector'], $usuario['c_usuario']]);
        $pdo->prepare('UPDATE usuario SET nombre = ?, telefono = ? WHERE c_usuario = ?')
            ->execute([$d['nombre'], $d['telefono'] ?: null, $usuario['c_usuario']]);
        $pdo->commit();
        $_SESSION['usuario']['nombre'] = $d['nombre'];
        $_SESSION['usuario']['subtitulo'] = $d['razon_social'];
        registrar_auditoria('actualizar_datos', 'empresa', $d['rfc_empresa']);
        aviso('exito', 'Datos de la empresa actualizados.');
        redirigir('mod_empresa/datos.php');
    }
}

$titulo = 'Datos de la empresa';
$menu_activo = 'mod_empresa/datos.php';
$migas = [['Panel', 'mod_empresa/index.php'], ['Datos de la empresa', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$err = fn (string $k) => $errores[$k] ?? null;
?>
<div class="encabezado-pagina">
    <div><h1 class="titulo-izquierda">Datos de la empresa</h1></div>
</div>
<?= alerta_errores($errores) ?>
<form method="post" style="max-width:860px" novalidate>
    <?= csrf_campo() ?>
    <section class="seccion-form" aria-labelledby="emp-t">
        <h2 id="emp-t">Empresa</h2>
        <div class="row g-3">
            <?= campo('rfc', 'RFC', $d['rfc_empresa'], ['solo_lectura' => true, 'ayuda' => 'No se puede cambiar.', 'clase_contenedor' => 'col-md-4']) ?>
            <?= campo('razon_social', 'Razón social', $d['razon_social'], ['placeholder' => 'Ej. Soluciones Digitales del Golfo S.A. de C.V.', 'autocomplete' => 'organization', 'requerido' => true, 'maxlength' => 200, 'error' => $err('razon_social'), 'clase_contenedor' => 'col-md-8']) ?>
            <?= campo_select('sector', 'Sector', $d['sector'], SECTORES_EMPRESA, ['requerido' => true, 'error' => $err('sector'), 'clase_contenedor' => 'col-md-6']) ?>
        </div>
    </section>
    <section class="seccion-form" aria-labelledby="con-t">
        <h2 id="con-t">Contacto responsable</h2>
        <div class="row g-3">
            <?= campo('nombre', 'Nombre completo', $d['nombre'], ['placeholder' => 'Ej. María Fernanda López García', 'autocomplete' => 'name', 'requerido' => true, 'maxlength' => 120, 'error' => $err('nombre'), 'clase_contenedor' => 'col-md-6']) ?>
            <?= campo('telefono', 'Teléfono', $d['telefono'], ['placeholder' => 'Ej. 2291234567', 'pattern' => '[0-9]{10,15}', 'title' => 'Solo dígitos, de 10 a 15.', 'inputmode' => 'tel', 'autocomplete' => 'tel', 'tipo' => 'tel', 'opcional' => true, 'maxlength' => 15, 'ayuda' => '10 dígitos.', 'error' => $err('telefono'), 'clase_contenedor' => 'col-md-6']) ?>
            <?= campo('correo', 'Correo electrónico', $d['correo'], ['solo_lectura' => true, 'ayuda' => 'Para cambiarlo, contacta a la administración.', 'clase_contenedor' => 'col-md-6']) ?>
        </div>
    </section>
    <div class="barra-acciones-form">
        <a class="btn btn-terciario" href="<?= e(url('mod_empresa/index.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-principal">Guardar cambios</button>
    </div>
</form>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
