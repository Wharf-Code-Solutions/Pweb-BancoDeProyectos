<?php
/** Pantalla P3 · Registro de empresa (pública). */
require_once __DIR__ . '/inc/inicio.php';

if (usuario_actual()) {
    redirigir(ruta_panel());
}

$d = ['rfc' => '', 'razon_social' => '', 'sector' => '', 'nombre' => '', 'correo' => '', 'telefono' => ''];
$errores = [];

if (es_post()) {
    verificar_csrf();
    $d['rfc']          = mb_strtoupper(str_replace([' ', '-'], '', limpiar($_POST['rfc'] ?? '')));
    $d['razon_social'] = limpiar($_POST['razon_social'] ?? '');
    $d['sector']       = limpiar($_POST['sector'] ?? '');
    $d['nombre']       = limpiar($_POST['nombre'] ?? '');
    $d['correo']       = mb_strtolower(limpiar($_POST['correo'] ?? ''));
    $d['telefono']     = preg_replace('/[\s\-()]/', '', (string) ($_POST['telefono'] ?? ''));
    $pw  = (string) ($_POST['contrasena'] ?? '');
    $pw2 = (string) ($_POST['confirmacion'] ?? '');

    $errores = array_filter([
        'rfc'          => validar_rfc($d['rfc']),
        'razon_social' => validar_requerido($d['razon_social'], 'La razón social'),
        'sector'       => in_array($d['sector'], SECTORES_EMPRESA, true) ? null : 'Elige un sector.',
        'nombre'       => validar_requerido($d['nombre'], 'El nombre'),
        'correo'       => validar_correo($d['correo']),
        'telefono'     => validar_telefono($d['telefono']),
        'contrasena'   => validar_contrasena($pw, $pw2),
    ]);

    if (!isset($errores['razon_social']) && mb_strlen($d['razon_social']) > 200) {
        $errores['razon_social'] = 'La razón social no puede pasar de 200 caracteres.';
    }
    if (!isset($errores['nombre']) && mb_strlen($d['nombre']) > 120) {
        $errores['nombre'] = 'El nombre no puede pasar de 120 caracteres.';
    }
    if (!isset($errores['correo']) && correo_registrado($d['correo'])) {
        $errores['correo'] = 'Ya existe una cuenta con este correo.';
    }
    if (!isset($errores['rfc'])) {
        $s = conexion()->prepare('SELECT COUNT(*) FROM empresa WHERE rfc_empresa = ?');
        $s->execute([$d['rfc']]);
        if ($s->fetchColumn() > 0) {
            $errores['rfc'] = 'Ya hay una empresa registrada con este RFC.';
        }
    }

    if (!$errores) {
        $pdo = conexion();
        try {
            $pdo->beginTransaction();
            $c_usuario = siguiente_folio('USR');
            $pdo->prepare('INSERT INTO usuario (c_usuario, nombre, correo, telefono, contrasena_hash, rol)
                           VALUES (?, ?, ?, ?, ?, \'empresa\')')
                ->execute([$c_usuario, $d['nombre'], $d['correo'], $d['telefono'] ?: null, password_hash($pw, PASSWORD_BCRYPT)]);
            $pdo->prepare('INSERT INTO empresa (rfc_empresa, c_usuario, razon_social, sector) VALUES (?, ?, ?, ?)')
                ->execute([$d['rfc'], $c_usuario, $d['razon_social'], $d['sector']]);
            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log((string) $ex);
            $errores['_general'] = mensaje_error_bd($ex);
        }

        if (!$errores) {
            registrar_auditoria('registrar', 'empresa', $d['rfc'], 'exito', $c_usuario);
            notificar($c_usuario, 'Bienvenido al Banco de Proyectos. Ya puedes registrar los proyectos de ' . rtrim($d['razon_social'], '.') . '.');
            notificar_administradores('Se registró la empresa ' . $d['razon_social'] . ' (' . $d['rfc'] . ').');
            abrir_sesion_usuario(autenticar($d['correo'], $pw));
            aviso('exito', 'Cuenta creada. Ya puedes registrar tu primer proyecto.');
            redirigir(ruta_panel());
        }
    }
}

$titulo = 'Registrar mi empresa';
$indexar = true;
$descripcion = 'Registra tu empresa en el Banco de Proyectos Académicos del TecNM · ITVer para proponer proyectos a alumnos y profesores.';
require __DIR__ . '/menumain/publico_encabezado.php';
?>
<section class="seccion-publica alterna" style="padding:3rem 0 4rem">
    <div class="contenedor">
        <div class="caja-formulario" style="max-width:640px">
            <h1 class="titulo-izquierda h2 mb-2">Registrar mi empresa</h1>
            <p class="text-mute">Cree la cuenta de su empresa para proponer proyectos al Tec. Los campos con <span class="text-danger fw-bold">*</span> son obligatorios.</p>
            <?= alerta_errores($errores) ?>
            <form method="post" class="d-grid gap-4" novalidate>
                <?= csrf_campo() ?>
                <fieldset class="d-grid gap-3">
                    <legend class="h3 mb-0">Empresa</legend>
                    <?= campo('rfc', 'RFC', $d['rfc'], ['placeholder' => 'Ej. ABC123456XY9', 'pattern' => '[A-Za-zÑñ&]{3,4}[0-9]{6}[A-Za-z0-9]{3}', 'title' => '3 o 4 letras, 6 dígitos de fecha y 3 caracteres de homoclave.', 'autocomplete' => 'off', 'requerido' => true, 'maxlength' => 13, 'error' => $errores['rfc'] ?? null,
                        'ayuda' => 'Persona moral 12 caracteres, persona física 13. Se guarda en mayúsculas.']) ?>
                    <?= campo('razon_social', 'Razón social', $d['razon_social'], ['placeholder' => 'Ej. Soluciones Digitales del Golfo S.A. de C.V.', 'autocomplete' => 'organization', 'requerido' => true, 'maxlength' => 200, 'error' => $errores['razon_social'] ?? null]) ?>
                    <?= campo_select('sector', 'Sector', $d['sector'], SECTORES_EMPRESA, ['requerido' => true, 'error' => $errores['sector'] ?? null]) ?>
                </fieldset>
                <fieldset class="d-grid gap-3">
                    <legend class="h3 mb-0">Contacto responsable</legend>
                    <?= campo('nombre', 'Nombre completo', $d['nombre'], ['placeholder' => 'Ej. María Fernanda López García', 'requerido' => true, 'maxlength' => 120, 'autocomplete' => 'name', 'error' => $errores['nombre'] ?? null]) ?>
                    <div class="row g-3">
                        <?= campo('correo', 'Correo electrónico', $d['correo'], ['placeholder' => 'contacto@empresa.com', 'tipo' => 'email', 'requerido' => true, 'autocomplete' => 'email',
                            'ayuda' => 'Con este correo iniciará sesión.', 'error' => $errores['correo'] ?? null, 'clase_contenedor' => 'col-md-6']) ?>
                        <?= campo('telefono', 'Teléfono', $d['telefono'], ['placeholder' => 'Ej. 2291234567', 'pattern' => '[0-9]{10,15}', 'title' => 'Solo dígitos, de 10 a 15.', 'inputmode' => 'tel', 'tipo' => 'tel', 'opcional' => true, 'maxlength' => 15, 'autocomplete' => 'tel',
                            'ayuda' => '10 dígitos.', 'error' => $errores['telefono'] ?? null, 'clase_contenedor' => 'col-md-6']) ?>
                        <?= campo('contrasena', 'Contraseña', '', ['placeholder' => 'Mínimo 8 caracteres', 'minlength' => 8, 'tipo' => 'password', 'requerido' => true, 'autocomplete' => 'new-password',
                            'error' => $errores['contrasena'] ?? null, 'clase_contenedor' => 'col-md-6']) ?>
                        <?= campo('confirmacion', 'Confirmar contraseña', '', ['placeholder' => 'Repite la contraseña', 'tipo' => 'password', 'requerido' => true, 'autocomplete' => 'new-password',
                            'clase_contenedor' => 'col-md-6']) ?>
                    </div>
                    <div class="leyenda-req">
                        <strong class="d-block mb-1">La contraseña debe tener:</strong>
                        <ul class="mb-0" style="padding-left:1.25rem"><li>Al menos 8 caracteres</li><li>Una letra mayúscula y una minúscula</li><li>Un número</li></ul>
                    </div>
                </fieldset>
                <div class="barra-acciones-form pt-0">
                    <a class="btn btn-terciario" href="<?= e(url('login.php')) ?>">Ya tengo cuenta</a>
                    <button type="submit" class="btn btn-principal">Crear cuenta</button>
                </div>
            </form>
        </div>
    </div>
</section>
<?php require __DIR__ . '/menumain/publico_pie.php'; ?>
