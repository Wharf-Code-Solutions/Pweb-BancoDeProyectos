<?php
/**
 * Sesión y control de acceso por rol.
 *
 * En cada página protegida:
 *   require_once __DIR__ . '/../inc/inicio.php';
 *   $usuario = requiere_rol('alumno');            // uno o varios roles
 */

const ROLES = ['empresa', 'alumno', 'profesor', 'administrador'];

/** Carpeta del panel de cada rol. */
const PANEL_POR_ROL = [
    'alumno'        => 'mod_alumno/',
    'empresa'       => 'mod_empresa/',
    'profesor'      => 'mod_profesor/',
    'administrador' => 'mod_admin/',
];

function iniciar_sesion_php(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('banco_proyectos');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_url() . '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Cierre por inactividad (2 horas).
    $limite = 2 * 60 * 60;
    if (isset($_SESSION['ultima_actividad']) && time() - $_SESSION['ultima_actividad'] > $limite) {
        reiniciar_sesion();
        aviso('advertencia', 'Tu sesión se cerró por inactividad. Vuelve a iniciar sesión.');
    }
    $_SESSION['ultima_actividad'] = time();
}

/**
 * Usuario en sesión, o null.
 * Claves: c_usuario, nombre, correo, rol, y la clave del subtipo
 * (matricula, rfc_empresa o num_empleado) más un subtítulo para el menú.
 */
function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/**
 * Busca al usuario por correo y valida la contraseña.
 * Devuelve el arreglo de sesión o null si no coincide o la cuenta está inactiva.
 */
function autenticar(string $correo, string $contrasena): ?array
{
    $pdo = conexion();
    $stmt = $pdo->prepare('SELECT c_usuario, nombre, correo, contrasena_hash, rol, estado
                             FROM usuario WHERE correo = ?');
    $stmt->execute([trim($correo)]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($contrasena, $u['contrasena_hash'])) {
        return null;
    }
    if ($u['estado'] !== 'activo') {
        return null;
    }

    $sesion = [
        'c_usuario' => $u['c_usuario'],
        'nombre'    => $u['nombre'],
        'correo'    => $u['correo'],
        'rol'       => $u['rol'],
        'clave'     => null,   // matrícula, RFC o número de empleado
        'subtitulo' => '',
    ];

    switch ($u['rol']) {
        case 'alumno':
            $s = $pdo->prepare('SELECT matricula, carrera, semestre FROM alumno WHERE c_usuario = ?');
            $s->execute([$u['c_usuario']]);
            if ($d = $s->fetch()) {
                $sesion['clave'] = $d['matricula'];
                $sesion['subtitulo'] = $d['carrera'] . ' · ' . $d['semestre'] . '.° semestre';
            }
            break;
        case 'empresa':
            $s = $pdo->prepare('SELECT rfc_empresa, razon_social FROM empresa WHERE c_usuario = ?');
            $s->execute([$u['c_usuario']]);
            if ($d = $s->fetch()) {
                $sesion['clave'] = $d['rfc_empresa'];
                $sesion['subtitulo'] = $d['razon_social'];
            }
            break;
        case 'profesor':
            $s = $pdo->prepare('SELECT num_empleado, especialidad FROM profesor WHERE c_usuario = ?');
            $s->execute([$u['c_usuario']]);
            if ($d = $s->fetch()) {
                $sesion['clave'] = $d['num_empleado'];
                $sesion['subtitulo'] = $d['especialidad'];
            }
            break;
        case 'administrador':
            $s = $pdo->prepare('SELECT num_empleado, cargo FROM administrador WHERE c_usuario = ?');
            $s->execute([$u['c_usuario']]);
            if ($d = $s->fetch()) {
                $sesion['clave'] = $d['num_empleado'];
                $sesion['subtitulo'] = $d['cargo'];
            }
            break;
    }

    // Si password_hash cambió de algoritmo o costo, se actualiza el hash.
    if (password_needs_rehash($u['contrasena_hash'], PASSWORD_BCRYPT)) {
        $pdo->prepare('UPDATE usuario SET contrasena_hash = ? WHERE c_usuario = ?')
            ->execute([password_hash($contrasena, PASSWORD_BCRYPT), $u['c_usuario']]);
    }

    return $sesion;
}

function abrir_sesion_usuario(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = $usuario;
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Cierra la sesión actual y abre una nueva y vacía (con otro id) para poder dejar avisos. */
function reiniciar_sesion(): void
{
    cerrar_sesion();
    session_id(session_create_id());
    session_start();
}

/** Ruta del panel del usuario en sesión (o del login). */
function ruta_panel(?array $usuario = null): string
{
    $usuario ??= usuario_actual();
    return $usuario ? (PANEL_POR_ROL[$usuario['rol']] ?? '') : 'login.php';
}

/** Exige sesión iniciada; si no la hay, manda al login y recuerda a dónde iba. */
function requiere_sesion(): array
{
    $u = usuario_actual();
    if ($u === null) {
        $_SESSION['volver_a'] = $_SERVER['REQUEST_URI'] ?? '';
        aviso('info', 'Inicia sesión para continuar.');
        redirigir('login.php');
    }

    // Si la administración desactivó la cuenta, la sesión abierta termina aquí.
    $s = conexion()->prepare('SELECT estado FROM usuario WHERE c_usuario = ?');
    $s->execute([$u['c_usuario']]);
    if ($s->fetchColumn() !== 'activo') {
        reiniciar_sesion();
        aviso('advertencia', 'Tu cuenta está desactivada. Contacta a la administración del Tec.');
        redirigir('login.php');
    }
    return $u;
}

/**
 * Exige sesión con uno de los roles indicados.
 * Rol equivocado: registra el intento en la auditoría y responde 403.
 */
function requiere_rol(string ...$roles): array
{
    $u = requiere_sesion();
    if (!in_array($u['rol'], $roles, true)) {
        registrar_auditoria('acceso', 'pagina', substr($_SERVER['SCRIPT_NAME'] ?? '', -100), 'denegado');
        error_http(403);
    }
    return $u;
}
