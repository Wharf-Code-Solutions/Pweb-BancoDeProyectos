<?php
/** Pantalla P2 · Iniciar sesión. */
require_once __DIR__ . '/inc/inicio.php';

if (usuario_actual()) {
    redirigir(ruta_panel());
}

$correo = '';
$error = '';

if (es_post()) {
    verificar_csrf();
    $correo = trim((string) ($_POST['correo'] ?? ''));
    $contrasena = (string) ($_POST['contrasena'] ?? '');

    // Freno simple contra fuerza bruta: 5 intentos fallidos → espera de 5 minutos.
    $intentos = $_SESSION['intentos_login'] ?? ['n' => 0, 'desde' => time()];
    if (time() - $intentos['desde'] > 300) {
        $intentos = ['n' => 0, 'desde' => time()];
    }

    // Segundo freno, por cuenta y guardado en la base (no se salta borrando cookies):
    // 5 intentos fallidos sobre la misma cuenta en 15 minutos la bloquean ese tiempo.
    $s = conexion()->prepare('SELECT c_usuario FROM usuario WHERE correo = ?');
    $s->execute([$correo]);
    $cuenta = $s->fetchColumn() ?: null;
    $fallos_cuenta = 0;
    if ($cuenta) {
        $s = conexion()->prepare("SELECT COUNT(*) FROM historial_auditoria
            WHERE c_usuario = ? AND operacion = 'iniciar_sesion' AND resultado = 'error' AND fecha > NOW() - INTERVAL 15 MINUTE
              AND fecha > COALESCE((SELECT MAX(fecha) FROM historial_auditoria WHERE c_usuario = ? AND operacion = 'iniciar_sesion' AND resultado = 'exito'), '1970-01-01')");
        $s->execute([$cuenta, $cuenta]);
        $fallos_cuenta = (int) $s->fetchColumn();
    }

    if ($intentos['n'] >= 5 || $fallos_cuenta >= 5) {
        $error = 'Demasiados intentos. Espera unos minutos y vuelve a intentarlo.';
    } elseif ($correo === '' || $contrasena === '') {
        $error = 'Escribe tu correo y tu contraseña.';
    } elseif ($u = autenticar($correo, $contrasena)) {
        unset($_SESSION['intentos_login']);
        $volver = $_SESSION['volver_a'] ?? '';
        abrir_sesion_usuario($u);
        registrar_auditoria('iniciar_sesion', 'usuario', $u['c_usuario'], 'exito');
        // Solo se vuelve a rutas internas del propio sitio.
        if (is_string($volver) && str_starts_with($volver, base_url() . '/') && !str_contains($volver, '//')) {
            header('Location: ' . $volver);
            exit;
        }
        redirigir(ruta_panel($u));
    } else {
        $intentos['n']++;
        $_SESSION['intentos_login'] = $intentos;
        if ($cuenta) {
            registrar_auditoria('iniciar_sesion', 'usuario', $cuenta, 'error', $cuenta);
        }
        $error = 'Correo o contraseña incorrectos.';
    }
}

$titulo = 'Iniciar sesión';
require __DIR__ . '/menumain/publico_encabezado.php';
?>
<section class="seccion-publica alterna" style="padding:3rem 0 4rem">
    <div class="contenedor">
        <div class="caja-formulario">
            <span class="etiqueta">Acceso</span>
            <h1 class="titulo-izquierda h2 mb-4">Iniciar sesión</h1>
            <?= mostrar_avisos() ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><i class="bi bi-x-octagon-fill" aria-hidden="true"></i><div class="alert-cuerpo"><?= e($error) ?></div></div>
            <?php endif; ?>
            <form method="post" class="d-grid gap-3" novalidate>
                <?= csrf_campo() ?>
                <div>
                    <label class="form-label" for="correo">Correo electrónico</label>
                    <input class="form-control" id="correo" name="correo" type="email" autocomplete="username" required value="<?= e($correo) ?>">
                </div>
                <div>
                    <label class="form-label" for="contrasena">Contraseña</label>
                    <input class="form-control" id="contrasena" name="contrasena" type="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-principal w-100"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Iniciar sesión</button>
            </form>
            <hr class="my-4">
            <p class="mb-2 text-center">¿Su empresa aún no tiene cuenta? <a href="<?= e(url('registro_empresa.php')) ?>" class="fw-bold">Registrar mi empresa</a></p>
            <p class="small text-mute text-center mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Alumnos y profesores: tu cuenta la crea la administración del Tec.</p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/menumain/publico_pie.php'; ?>
