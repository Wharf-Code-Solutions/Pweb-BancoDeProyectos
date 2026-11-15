<?php
/** Pantalla C3 · Mi cuenta (todos los roles): datos de contacto y contraseña. */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_sesion();
$pdo = conexion();

$s = $pdo->prepare('SELECT nombre, correo, telefono, contrasena_hash FROM usuario WHERE c_usuario = ?');
$s->execute([$usuario['c_usuario']]);
$cuenta = $s->fetch();

$d = ['nombre' => $cuenta['nombre'], 'telefono' => (string) $cuenta['telefono']];
$errores = [];      // formulario de datos
$errores_pw = [];   // formulario de contraseña

if (es_post()) {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'datos') {
        $d['nombre']   = limpiar($_POST['nombre'] ?? '');
        $d['telefono'] = preg_replace('/[\s\-()]/', '', (string) ($_POST['telefono'] ?? ''));
        $errores = array_filter([
            'nombre'   => validar_requerido($d['nombre'], 'El nombre') ?? (mb_strlen($d['nombre']) > 120 ? 'El nombre no puede pasar de 120 caracteres.' : null),
            'telefono' => validar_telefono($d['telefono']),
        ]);
        if (!$errores) {
            $pdo->prepare('UPDATE usuario SET nombre = ?, telefono = ? WHERE c_usuario = ?')
                ->execute([$d['nombre'], $d['telefono'] ?: null, $usuario['c_usuario']]);
            $_SESSION['usuario']['nombre'] = $d['nombre'];
            registrar_auditoria('actualizar_datos', 'usuario', $usuario['c_usuario']);
            aviso('exito', 'Datos actualizados.');
            redirigir('mod_comun/cuenta.php');
        }
    } elseif ($accion === 'contrasena') {
        $actual = (string) ($_POST['actual'] ?? '');
        $nueva  = (string) ($_POST['nueva'] ?? '');
        $conf   = (string) ($_POST['confirmacion'] ?? '');
        if (!password_verify($actual, $cuenta['contrasena_hash'])) {
            $errores_pw['actual'] = 'La contraseña actual no es correcta.';
            registrar_auditoria('cambiar_contrasena', 'usuario', $usuario['c_usuario'], 'error');
        } elseif ($e = validar_contrasena($nueva, $conf)) {
            $errores_pw['nueva'] = $e;
        } elseif ($actual === $nueva) {
            $errores_pw['nueva'] = 'La nueva contraseña debe ser distinta de la actual.';
        }
        if (!$errores_pw) {
            $pdo->prepare('UPDATE usuario SET contrasena_hash = ? WHERE c_usuario = ?')
                ->execute([password_hash($nueva, PASSWORD_BCRYPT), $usuario['c_usuario']]);
            session_regenerate_id(true);
            registrar_auditoria('cambiar_contrasena', 'usuario', $usuario['c_usuario']);
            aviso('exito', 'Contraseña actualizada.');
            redirigir('mod_comun/cuenta.php');
        }
    }
}

$titulo = 'Mi cuenta';
$menu_activo = '';
$migas = [['Panel', ruta_panel($usuario) . 'index.php'], ['Mi cuenta', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Mi cuenta</span><h1 class="titulo-izquierda">Mi cuenta</h1></div>
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <section class="seccion-form" id="cuenta" aria-labelledby="cuenta-t">
            <h2 id="cuenta-t">Datos de la cuenta</h2>
            <?= alerta_errores($errores) ?>
            <form method="post" class="d-grid gap-3" novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="datos">
                <?= campo('nombre', 'Nombre completo', $d['nombre'], ['requerido' => true, 'maxlength' => 120, 'autocomplete' => 'name', 'error' => $errores['nombre'] ?? null]) ?>
                <?= campo('correo', 'Correo electrónico', $cuenta['correo'], ['id' => 'co', 'solo_lectura' => true,
                    'ayuda' => 'El correo no se puede cambiar. Si es incorrecto, contacta a la administración.']) ?>
                <div class="row g-3">
                    <?= campo('telefono', 'Teléfono', $d['telefono'], ['tipo' => 'tel', 'opcional' => true, 'maxlength' => 15, 'autocomplete' => 'tel',
                        'ayuda' => '10 dígitos.', 'error' => $errores['telefono'] ?? null, 'clase_contenedor' => 'col-md-6']) ?>
                    <?= campo('rol', 'Rol', nombre_rol($usuario['rol']), ['solo_lectura' => true, 'clase_contenedor' => 'col-md-6']) ?>
                </div>
                <div class="barra-acciones-form pt-0"><button type="submit" class="btn btn-principal">Guardar cambios</button></div>
            </form>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="seccion-form" id="pass" aria-labelledby="pass-t">
            <h2 id="pass-t">Cambiar contraseña</h2>
            <form method="post" class="d-grid gap-3" novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="contrasena">
                <?= campo('actual', 'Contraseña actual', '', ['tipo' => 'password', 'requerido' => true, 'autocomplete' => 'current-password', 'error' => $errores_pw['actual'] ?? null]) ?>
                <?= campo('nueva', 'Nueva contraseña', '', ['tipo' => 'password', 'requerido' => true, 'autocomplete' => 'new-password',
                    'ayuda' => 'Al menos 8 caracteres, con mayúscula, minúscula y número.', 'error' => $errores_pw['nueva'] ?? null]) ?>
                <?= campo('confirmacion', 'Confirmar nueva contraseña', '', ['tipo' => 'password', 'requerido' => true, 'autocomplete' => 'new-password']) ?>
                <div class="barra-acciones-form pt-0"><button type="submit" class="btn btn-secundario">Cambiar contraseña</button></div>
            </form>
        </section>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
