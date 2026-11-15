<?php
/**
 * Pruebas de validación del flujo completo (integridad de datos).
 *
 * Uso (desde la raíz del proyecto, con XAMPP encendido):
 *   C:\xampp\php\php.exe pruebas\flujo.php
 *
 * Qué hace:
 *   1. Crea la base temporal banco_proyectos_prueba con sql/banco_proyectos.sql
 *      y sql/datos_prueba.sql.
 *   2. Apunta la aplicación a esa base mientras corren las pruebas (cambia
 *      db_name en bd/config.local.php y lo regresa al terminar, pase lo que pase).
 *   3. Recorre el sitio por HTTP como lo haría cada rol, con datos válidos e
 *      inválidos, y revisa tanto la respuesta como lo que quedó en la base.
 *   4. Borra la base temporal.
 *
 * Opciones: --conservar (no borra la base temporal al final),
 *           --url=http://localhost/Banco_Proyectos (si la carpeta es otra).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
$opciones = getopt('', ['conservar', 'url:', 'mysql:']);
$BASE = rtrim($opciones['url'] ?? 'http://localhost/Banco_Proyectos', '/');
$MYSQL = $opciones['mysql'] ?? 'C:\\xampp\\mysql\\bin\\mysql.exe';
$BD = 'banco_proyectos_prueba';
$CONFIG = "$raiz/bd/config.local.php";
$PW = 'Prueba2026!';

// ---------------------------------------------------------------------
// Preparación: base temporal y configuración
// ---------------------------------------------------------------------
$config_original = is_file($CONFIG) ? file_get_contents($CONFIG) : null;
$config_base = require (is_file($CONFIG) ? $CONFIG : "$raiz/bd/config.ejemplo.php");

$restaurar = function () use ($CONFIG, $config_original, $raiz) {
    if ($config_original === null) {
        @unlink($CONFIG);
    } else {
        file_put_contents($CONFIG, $config_original);
    }
};
register_shutdown_function($restaurar);

$cred = sprintf('-h%s -u%s %s', escapeshellarg($config_base['db_host']), escapeshellarg($config_base['db_user']),
    $config_base['db_pass'] !== '' ? '-p' . escapeshellarg($config_base['db_pass']) : '');
$mysql = fn (string $args) => '"' . $MYSQL . '" ' . $cred . ' ' . $args;

echo "Preparando la base temporal $BD...\n";
exec($mysql("-e \"DROP DATABASE IF EXISTS $BD; CREATE DATABASE $BD CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""), $o, $rc);
if ($rc !== 0) {
    exit("No se pudo crear la base temporal. ¿Está encendido MySQL?\n");
}
$script = file_get_contents("$raiz/sql/banco_proyectos.sql");
$script = preg_replace('/^CREATE DATABASE IF NOT EXISTS banco_proyectos.*?;\s*$/ms', '', $script, 1);
$script = preg_replace('/^USE banco_proyectos;\s*$/m', '', $script);
$tmp = tempnam(sys_get_temp_dir(), 'bp');
file_put_contents($tmp, $script);
exec($mysql("$BD < " . escapeshellarg($tmp)), $o, $rc1);
exec($mysql("$BD < " . escapeshellarg("$raiz/sql/datos_prueba.sql")), $o, $rc2);
unlink($tmp);
if ($rc1 || $rc2) {
    exit("No se pudo importar el script de la base.\n");
}

$nueva = var_export(['db_name' => $BD, 'debug' => false] + $config_base, true);
file_put_contents($CONFIG, "<?php\n// Archivo temporal de las pruebas: se restaura solo al terminar.\nreturn $nueva;\n");

$db = new PDO("mysql:host={$config_base['db_host']};dbname=$BD;charset=utf8mb4", $config_base['db_user'], $config_base['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec("SET time_zone = '-06:00'");
$uno = function (string $sql, array $p = []) use ($db) {
    $s = $db->prepare($sql);
    $s->execute($p);
    return $s->fetchColumn();
};

// ---------------------------------------------------------------------
// Cliente HTTP con sesión propia (una "pestaña" por usuario)
// ---------------------------------------------------------------------
final class Cliente
{
    private string $jar;
    public int $codigo = 0;
    public string $html = '';
    public ?string $destino = null;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'cj');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    public function get(string $ruta): static
    {
        return $this->pedir($ruta);
    }

    /** POST; si no se da 'csrf', lo toma de la página (GET a $de, o a la misma ruta). */
    public function post(string $ruta, array $datos = [], ?string $de = null): static
    {
        if (!array_key_exists('csrf', $datos)) {
            $datos['csrf'] = $this->get($de ?? $ruta)->csrf();
        }
        return $this->pedir($ruta, $datos);
    }

    public function csrf(): string
    {
        return preg_match('/name="csrf" value="([^"]+)"/', $this->html, $m) ? html_entity_decode($m[1]) : '';
    }

    public function entrar(string $correo, string $pw): static
    {
        $this->post('login.php', ['correo' => $correo, 'contrasena' => $pw]);
        return $this;
    }

    public function salir(): void
    {
        $this->post('logout.php', [], 'mod_comun/cuenta.php');
    }

    /** ¿Aparece el texto en la página (ya sin entidades HTML)? */
    public function tiene(string $texto): bool
    {
        return str_contains(html_entity_decode(strip_tags($this->html), ENT_QUOTES, 'UTF-8'), $texto);
    }

    private function pedir(string $ruta, ?array $post = null): static
    {
        $c = curl_init($this->base . '/' . ltrim($ruta, '/'));
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30,
        ]);
        if ($post !== null) {
            curl_setopt($c, CURLOPT_POST, true);
            curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $r = curl_exec($c);
        $this->codigo = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        $tam = curl_getinfo($c, CURLINFO_HEADER_SIZE);
        $cab = substr((string) $r, 0, $tam);
        $this->html = substr((string) $r, $tam);
        $this->destino = preg_match('/^Location:\s*(\S+)/mi', $cab, $m) ? $m[1] : null;
        curl_close($c);
        return $this;
    }
}

// ---------------------------------------------------------------------
// Aserciones
// ---------------------------------------------------------------------
$total = 0;
$fallos = [];
$seccion = '';
function seccion(string $t): void
{
    global $seccion;
    $seccion = $t;
    echo "\n== $t ==\n";
}
function ok(bool $condicion, string $nombre): void
{
    global $total, $fallos, $seccion;
    $total++;
    echo ($condicion ? '  ✔ ' : '  ✘ ') . $nombre . "\n";
    if (!$condicion) {
        $fallos[] = "[$seccion] $nombre";
    }
}
/** La sentencia directa a la base debe ser rechazada (trigger o restricción). */
function rechaza(PDO $db, string $sql, string $nombre, string $contiene = ''): void
{
    try {
        $db->exec($sql);
        ok(false, $nombre . ' (la base lo aceptó)');
    } catch (PDOException $e) {
        ok($contiene === '' || str_contains($e->getMessage(), $contiene), $nombre);
    }
}

$admin = (new Cliente($BASE))->entrar('admin@banco.test', $PW);
$empresa = (new Cliente($BASE))->entrar('empresa@banco.test', $PW);
$alumno = (new Cliente($BASE))->entrar('alumno@banco.test', $PW);
$profesor = (new Cliente($BASE))->entrar('profesor@banco.test', $PW);
$anonimo = new Cliente($BASE);

// =====================================================================
seccion('1. Acceso, roles y sesión');
// =====================================================================
foreach (['mod_admin/' => $anonimo, 'mod_empresa/proyectos.php' => $anonimo, 'mod_comun/cuenta.php' => $anonimo] as $ruta => $c) {
    $c->get($ruta);
    ok($c->codigo === 302 && str_contains((string) $c->destino, 'login.php'), "Sin sesión, $ruta manda al inicio de sesión");
}
$cruces = [
    [$alumno, 'mod_admin/usuarios.php'], [$alumno, 'mod_empresa/proyecto_form.php'], [$alumno, 'mod_profesor/'],
    [$empresa, 'mod_admin/'], [$empresa, 'mod_alumno/'], [$profesor, 'mod_empresa/'], [$profesor, 'mod_admin/reportes.php'],
    [$admin, 'mod_alumno/proyectos.php'],
];
foreach ($cruces as [$c, $ruta]) {
    $c->get($ruta);
    ok($c->codigo === 403, "Rol equivocado en $ruta recibe 403");
}
ok((int) $uno("SELECT COUNT(*) FROM historial_auditoria WHERE operacion = 'acceso' AND resultado = 'denegado'") >= count($cruces),
    'Cada acceso denegado queda en el historial');

$admin->post('mod_admin/usuarios.php', ['csrf' => 'falso', 'c_usuario' => 'USR-00000003', 'accion' => 'desactivar']);
ok($admin->codigo === 403 && $uno("SELECT estado FROM usuario WHERE c_usuario = 'USR-00000003'") === 'activo', 'POST con token CSRF falso se rechaza y no cambia nada');
$admin->post('mod_admin/habilidades.php', ['csrf' => '', 'nombre' => 'Hackeo', 'tipo' => 'dura']);
ok($admin->codigo === 403 && !$uno("SELECT COUNT(*) FROM habilidad WHERE nombre_habilidad = 'Hackeo'"), 'POST sin token CSRF se rechaza');
$anonimo->get('logout.php');
ok($anonimo->codigo === 302, 'Cerrar sesión por GET no hace nada (solo redirige)');

foreach (['inc/sesion.php', 'bd/config.local.php', 'sql/datos_prueba.sql', 'menumain/app_pie.php', 'inputs/campos.php', 'pruebas/flujo.php', 'errores/plantilla_error.php'] as $ruta) {
    $anonimo->get($ruta);
    ok(in_array($anonimo->codigo, [403, 404], true), "No se puede abrir $ruta desde el navegador");
}
$anonimo->get('verificar.php');
ok($anonimo->codigo === 404, 'verificar.php no responde con debug apagado');

$x = (new Cliente($BASE))->entrar('alumno@banco.test', 'incorrecta');
ok($x->tiene('Correo o contraseña incorrectos'), 'Contraseña incorrecta: mensaje genérico');
$x = (new Cliente($BASE))->entrar('noexiste@banco.test', $PW);
ok($x->tiene('Correo o contraseña incorrectos'), 'Correo inexistente: el mismo mensaje (no revela si existe)');
$x = (new Cliente($BASE))->entrar('ALUMNO@BANCO.TEST', $PW);
ok($x->codigo === 302 && str_contains((string) $x->destino, 'mod_alumno'), 'El correo no distingue mayúsculas al iniciar sesión');

// =====================================================================
seccion('2. Registro de empresa');
// =====================================================================
$r = new Cliente($BASE);
$validos = ['rfc' => 'SPG150312AB1', 'razon_social' => 'Soluciones Portuarias del Golfo', 'sector' => 'Logística',
            'nombre' => 'Ricardo Herrera', 'correo' => 'contacto@portuarias.mx', 'telefono' => '2291234567',
            'contrasena' => 'Abcdef12', 'confirmacion' => 'Abcdef12'];
$casos = [
    'RFC corto'                       => ['rfc' => 'SPG1503'],
    'RFC con letras en la fecha'      => ['rfc' => 'SPGAB0312AB1'],
    'RFC con símbolos'                => ['rfc' => 'SP$150312AB1'],
    'RFC con mes 13'                  => ['rfc' => 'SPG151312AB1'],
    'RFC con 31 de abril'             => ['rfc' => 'SPG150431AB1'],
    'RFC repetido'                    => ['rfc' => 'TDE200101AB1'],
    'Correo repetido (otra caja)'     => ['correo' => 'EMPRESA@banco.test'],
    'Correo mal formado'              => ['correo' => 'no-es-correo'],
    'Teléfono con letras'             => ['telefono' => '22912abc67'],
    'Teléfono corto'                  => ['telefono' => '12345'],
    'Contraseña sin número'           => ['contrasena' => 'Abcdefgh', 'confirmacion' => 'Abcdefgh'],
    'Contraseña sin mayúscula'        => ['contrasena' => 'abcdef12', 'confirmacion' => 'abcdef12'],
    'Contraseña corta'                => ['contrasena' => 'Ab1', 'confirmacion' => 'Ab1'],
    'Confirmación distinta'           => ['confirmacion' => 'Abcdef13'],
    'Sector fuera de la lista'        => ['sector' => 'Piratería'],
    'Razón social vacía'              => ['razon_social' => '   '],
    'Razón social de 201 caracteres'  => ['razon_social' => str_repeat('a', 201)],
    'Nombre vacío'                    => ['nombre' => ''],
];
$antes = (int) $uno('SELECT COUNT(*) FROM usuario');
foreach ($casos as $nombre => $cambio) {
    $r->post('registro_empresa.php', $cambio + $validos);
    ok($r->codigo === 200 && $r->tiene('Revisa los campos marcados') || $r->tiene('no tiene el formato correcto'), "Rechaza: $nombre");
}
ok((int) $uno('SELECT COUNT(*) FROM usuario') === $antes, 'Ningún registro inválido creó usuarios');

$r->post('registro_empresa.php', ['rfc' => 'xss 150312 ab1', 'nombre' => '<script>alert(1)</script>'] + $validos);
ok($r->codigo === 302, 'Registro válido (RFC con espacios y minúsculas) entra al panel');
ok($uno("SELECT rfc_empresa FROM empresa WHERE razon_social = 'Soluciones Portuarias del Golfo'") === 'XSS150312AB1', 'El RFC se guarda limpio y en mayúsculas');
$r->get('mod_empresa/');
ok(!str_contains($r->html, '<script>alert(1)</script>') && str_contains($r->html, '&lt;script&gt;'), 'Un nombre con <script> se muestra escapado (sin XSS)');
ok($uno("SELECT correo FROM usuario WHERE nombre LIKE '%script%'") === 'contacto@portuarias.mx', 'El correo se guarda en minúsculas');
$empresa2 = $r;

// =====================================================================
seccion('3. Administración: usuarios y catálogos');
// =====================================================================
$base_alumno = ['rol' => 'alumno', 'nombre' => 'Ana Karen Hernández', 'correo' => 'ana@banco.test', 'telefono' => '',
                'contrasena' => 'Inicial2026', 'matricula' => '22020198', 'carrera' => 'Ing. en Sistemas Computacionales', 'semestre' => '6'];
foreach ([
    'Matrícula de 7 dígitos'    => ['matricula' => '2202019'],
    'Matrícula con letras'      => ['matricula' => '2202019A'],
    'Matrícula repetida'        => ['matricula' => '20260001'],
    'Semestre 13'               => ['semestre' => '13'],
    'Semestre 0'                => ['semestre' => '0'],
    'Carrera inventada'         => ['carrera' => 'Medicina'],
    'Rol inventado'             => ['rol' => 'superusuario'],
    'Contraseña débil'          => ['contrasena' => '12345678'],
] as $nombre => $cambio) {
    $admin->post('mod_admin/usuario_form.php', $cambio + $base_alumno);
    ok($admin->codigo === 200 && !$uno("SELECT COUNT(*) FROM usuario WHERE correo = 'ana@banco.test'"), "Alta de alumno rechaza: $nombre");
}
$admin->post('mod_admin/usuario_form.php', $base_alumno);
ok($admin->codigo === 302 && $uno("SELECT matricula FROM alumno a JOIN usuario u ON u.c_usuario = a.c_usuario WHERE u.correo = 'ana@banco.test'") === '22020198', 'Alta de alumno válida');
$id_ana = $uno("SELECT c_usuario FROM usuario WHERE correo = 'ana@banco.test'");

$base_prof = ['rol' => 'profesor', 'nombre' => 'Laura Gómez', 'correo' => 'laura@banco.test', 'contrasena' => 'Inicial2026',
              'num_empleado' => 'E1045', 'especialidad' => 'Bases de datos'];
$admin->post('mod_admin/usuario_form.php', ['num_empleado' => 'PRF001'] + $base_prof);
ok($admin->codigo === 200 && $admin->tiene('ya está registrado'), 'Número de empleado de profesor repetido se rechaza');
$admin->post('mod_admin/usuario_form.php', ['num_empleado' => 'ABCDEFGHIJK'] + $base_prof);
ok($admin->codigo === 200, 'Número de empleado de 11 caracteres se rechaza');
$admin->post('mod_admin/usuario_form.php', $base_prof);
ok($admin->codigo === 302, 'Alta de profesor válida');

// Editar: el rol no se puede cambiar aunque se mande en el formulario.
$admin->post("mod_admin/usuario_form.php?id=$id_ana", ['rol' => 'administrador', 'num_empleado' => 'X1', 'cargo' => 'Jefe'] + $base_alumno);
ok($uno("SELECT rol FROM usuario WHERE c_usuario = ?", [$id_ana]) === 'alumno', 'Editar no permite cambiar el rol');
$admin->post("mod_admin/usuario_form.php?id=$id_ana", ['correo' => 'alumno@banco.test'] + $base_alumno);
ok($admin->tiene('Ese correo ya está registrado'), 'Editar no permite usar el correo de otro usuario');
$admin->get('mod_admin/usuario_form.php?id=USR-99999999');
ok($admin->codigo === 404, 'Editar un usuario inexistente da 404');

// Desactivar corta la sesión abierta.
$ana = (new Cliente($BASE))->entrar('ana@banco.test', 'Inicial2026');
$ana->get('mod_alumno/');
ok($ana->codigo === 200, 'La alumna nueva entra a su panel');
$admin->post('mod_admin/usuarios.php', ['c_usuario' => $id_ana, 'accion' => 'desactivar']);
$ana->get('mod_alumno/');
ok($ana->codigo === 302, 'Al desactivarla, su sesión abierta se cierra en el siguiente clic');
$x = (new Cliente($BASE))->entrar('ana@banco.test', 'Inicial2026');
ok($x->tiene('Correo o contraseña incorrectos'), 'Una cuenta desactivada no puede iniciar sesión');
$admin->post('mod_admin/usuarios.php', ['c_usuario' => 'USR-00000001', 'accion' => 'desactivar']);
ok($uno("SELECT estado FROM usuario WHERE c_usuario = 'USR-00000001'") === 'activo', 'El administrador no puede desactivarse a sí mismo');
$admin->post('mod_admin/usuarios.php', ['c_usuario' => $id_ana, 'accion' => 'activar']);

// Habilidades y criterios
$admin->post('mod_admin/habilidades.php', ['nombre' => 'php', 'tipo' => 'dura']);
ok((int) $uno("SELECT COUNT(*) FROM habilidad WHERE nombre_habilidad = 'PHP'") === 1 && $admin->tiene('Ya existe'), 'Habilidad repetida (sin importar mayúsculas) se rechaza');
$admin->post('mod_admin/habilidades.php', ['nombre' => 'Docker', 'tipo' => 'mixta']);
ok(!$uno("SELECT COUNT(*) FROM habilidad WHERE nombre_habilidad = 'Docker'"), 'Tipo de habilidad inválido se rechaza');
$admin->post('mod_admin/habilidades.php', ['accion' => 'eliminar', 'original' => 'PHP']);
ok((int) $uno("SELECT COUNT(*) FROM habilidad WHERE nombre_habilidad = 'PHP'") === 1, 'No se borra una habilidad en uso');
$admin->post('mod_admin/habilidades.php', ['original' => 'MySQL / MariaDB', 'nombre' => 'MySQL', 'tipo' => 'dura']);
ok($uno("SELECT COUNT(*) FROM perfil_habilidad WHERE nombre_habilidad = 'MySQL'") === 1, 'Renombrar una habilidad actualiza los perfiles en cascada');

foreach (['0' => 'peso 0', '100.01' => 'peso 100.01', '-5' => 'peso negativo', '12.345' => '3 decimales', 'abc' => 'texto'] as $peso => $nombre) {
    $admin->post('mod_admin/criterios.php', ['nombre' => 'Puntualidad', 'peso' => $peso]);
    ok(!$uno("SELECT COUNT(*) FROM criterio WHERE nombre_criterio = 'Puntualidad'"), "Criterio rechaza $nombre");
}

// =====================================================================
seccion('4. Proyectos: formulario y revisión');
// =====================================================================
$futuro = date('Y-m-d', strtotime('+60 days'));
$fin = date('Y-m-d', strtotime('+150 days'));
$proyecto = ['titulo' => 'Inventarios portuarios', 'descripcion' => 'Sistema web de inventarios', 'problematica' => 'Hojas de cálculo',
             'objetivos' => "Registrar entradas\nAlertas de mínimos", 'justificacion' => 'Reducir pérdidas', 'alcance' => 'Catálogo y reportes',
             'duracion_estimada' => '16', 'modalidad' => 'hibrida', 'fecha_inicio' => $futuro, 'fecha_fin' => $fin, 'cupo_alumnos' => '1',
             'req' => ['0' => ['habilidad' => 'PHP', 'nivel' => 'intermedio', 'obligatorio' => '1']]];
$antes = (int) $uno('SELECT COUNT(*) FROM proyecto');
foreach ([
    'Título vacío'                      => ['titulo' => ''],
    'Título de 151 caracteres'          => ['titulo' => str_repeat('x', 151)],
    'Fecha de fin antes del inicio'     => ['fecha_fin' => date('Y-m-d', strtotime('+10 days'))],
    'Fecha de inicio en el pasado'      => ['fecha_inicio' => '2020-01-01', 'fecha_fin' => '2020-05-01'],
    'Fecha imposible (30 de febrero)'   => ['fecha_inicio' => '2027-02-30'],
    'Duración 0'                        => ['duracion_estimada' => '0'],
    'Duración con letras'               => ['duracion_estimada' => '12a'],
    'Cupo 0'                            => ['cupo_alumnos' => '0'],
    'Cupo 51'                           => ['cupo_alumnos' => '51'],
    'Modalidad inventada'               => ['modalidad' => 'lunar'],
    'Habilidad fuera del catálogo'      => ['req' => [['habilidad' => 'Magia', 'nivel' => 'basico', 'obligatorio' => '1']]],
    'Nivel inventado'                   => ['req' => [['habilidad' => 'PHP', 'nivel' => 'experto', 'obligatorio' => '1']]],
    'Habilidad repetida'                => ['req' => [['habilidad' => 'PHP', 'nivel' => 'basico', 'obligatorio' => '1'], ['habilidad' => 'PHP', 'nivel' => 'avanzado', 'obligatorio' => '0']]],
    'Falta la descripción al enviar'    => ['descripcion' => ''],
    'Descripción de 70 000 caracteres'  => ['descripcion' => str_repeat('ñ', 70000)],
] as $nombre => $cambio) {
    $empresa->post('mod_empresa/proyecto_form.php', ['accion' => 'enviar'] + $cambio + $proyecto);
    ok($empresa->codigo === 200, "Enviar a revisión rechaza: $nombre");
}
ok((int) $uno('SELECT COUNT(*) FROM proyecto') === $antes, 'Ningún envío inválido creó proyectos');

$empresa->post('mod_empresa/proyecto_form.php', ['accion' => 'guardar', 'titulo' => 'Solo título']);
$borrador = $uno("SELECT c_proyecto FROM proyecto WHERE titulo = 'Solo título'");
ok($borrador && $uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$borrador]) === 'borrador', 'Un borrador incompleto sí se guarda');
$empresa->post('mod_empresa/proyecto_form.php', ['accion' => 'enviar'] + $proyecto);
$p1 = $uno("SELECT c_proyecto FROM proyecto WHERE titulo = 'Inventarios portuarios'");
ok($p1 && $uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'en_revision', 'Proyecto completo pasa a «En revisión»');
ok((int) $uno('SELECT COUNT(*) FROM requisito_proyecto WHERE c_proyecto = ?', [$p1]) === 1, 'Sus habilidades requeridas se guardan');

$empresa->post("mod_empresa/proyecto_form.php?id=$p1", ['accion' => 'guardar', 'titulo' => 'Cambio a escondidas'] + $proyecto, "mod_empresa/proyecto.php?id=$p1");
ok($uno('SELECT titulo FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'Inventarios portuarios', 'No se puede editar un proyecto en revisión (ni por POST directo)');
$empresa2->get("mod_empresa/proyecto.php?id=$p1");
ok($empresa2->codigo === 404, 'Otra empresa no puede ver el proyecto (404)');
$empresa2->post("mod_empresa/proyecto_form.php?id=$borrador", ['accion' => 'guardar', 'titulo' => 'Robado'], 'mod_empresa/proyectos.php');
ok($uno('SELECT titulo FROM proyecto WHERE c_proyecto = ?', [$borrador]) === 'Solo título', 'Otra empresa no puede editar un borrador ajeno');
$alumno->get("mod_alumno/proyecto.php?id=$p1");
ok($alumno->codigo === 404, 'Un alumno no ve proyectos en revisión');
$admin->get("mod_admin/proyecto.php?id=$borrador");
ok($admin->codigo === 404, 'El administrador no ve borradores de la empresa');

$admin->post("mod_admin/revisar_proyecto.php?id=$p1", ['decision' => 'devolver', 'justificacion' => 'corto']);
ok($uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'en_revision', 'Devolver sin justificación suficiente no hace nada');
$admin->post("mod_admin/revisar_proyecto.php?id=$p1", ['decision' => 'borrar', 'justificacion' => 'Decisión inventada']);
ok($uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'en_revision', 'Una decisión inventada se rechaza');
$csrf = $admin->get("mod_admin/revisar_proyecto.php?id=$p1")->csrf();
$admin->post("mod_admin/revisar_proyecto.php?id=$p1", ['csrf' => $csrf, 'decision' => 'devolver', 'justificacion' => 'Detalla el alcance de la primera entrega.']);
$admin->post("mod_admin/revisar_proyecto.php?id=$p1", ['csrf' => $csrf, 'decision' => 'aprobar', 'justificacion' => '']);
ok($uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'devuelto'
    && (int) $uno('SELECT COUNT(*) FROM autorizacion WHERE c_proyecto = ?', [$p1]) === 1, 'Decidir dos veces (doble clic o dos pestañas) solo registra la primera');

$empresa->post("mod_empresa/proyecto_form.php?id=$p1", ['accion' => 'guardar', 'descripcion' => ''] + $proyecto);
ok($uno('SELECT descripcion FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'Sistema web de inventarios', 'Un proyecto devuelto no se puede guardar incompleto');
$empresa->post("mod_empresa/proyecto_form.php?id=$p1", ['accion' => 'enviar'] + $proyecto);
$admin->post("mod_admin/revisar_proyecto.php?id=$p1", ['decision' => 'aprobar', 'justificacion' => '']);
ok($uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'publicado', 'Reenviado y aprobado: queda publicado');

// Un segundo proyecto para rechazarlo
$empresa->post('mod_empresa/proyecto_form.php', ['accion' => 'enviar', 'titulo' => 'Proyecto a rechazar'] + $proyecto);
$p_rech = $uno("SELECT c_proyecto FROM proyecto WHERE titulo = 'Proyecto a rechazar'");
$admin->post("mod_admin/revisar_proyecto.php?id=$p_rech", ['decision' => 'rechazar', 'justificacion' => 'No corresponde al área de sistemas.']);
$empresa->get("mod_empresa/proyecto_form.php?id=$p_rech");
ok($empresa->codigo === 302 && $uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$p_rech]) === 'rechazado', 'Un proyecto rechazado ya no se puede editar');

// =====================================================================
seccion('5. Postulaciones de alumnos');
// =====================================================================
// Más proyectos publicados para probar el límite de 3.
$extra = [];
foreach (['B', 'C', 'D'] as $l) {
    $empresa->post('mod_empresa/proyecto_form.php', ['accion' => 'enviar', 'titulo' => "Proyecto $l", 'cupo_alumnos' => '', 'req' => []] + $proyecto);
    $extra[$l] = $uno('SELECT c_proyecto FROM proyecto WHERE titulo = ?', ["Proyecto $l"]);
    $admin->post("mod_admin/revisar_proyecto.php?id={$extra[$l]}", ['decision' => 'aprobar', 'justificacion' => '']);
}
$alumno->post("mod_alumno/proyecto.php?id=$p_rech");
ok(!$uno("SELECT COUNT(*) FROM solicitud_candidatura WHERE c_proyecto = ?", [$p_rech]), 'No se puede postular a un proyecto rechazado');
$alumno->post("mod_alumno/proyecto.php?id=$borrador");
ok(!$uno("SELECT COUNT(*) FROM solicitud_candidatura WHERE c_proyecto = ?", [$borrador]), 'No se puede postular a un borrador');

foreach ([$p1, $extra['B'], $extra['C']] as $p) {
    $alumno->post("mod_alumno/proyecto.php?id=$p");
}
ok((int) $uno("SELECT COUNT(*) FROM v_candidatura_vigente WHERE matricula = '20260001'") === 3, 'El alumno llega a 3 candidaturas vigentes');
$alumno->post("mod_alumno/proyecto.php?id={$extra['D']}");
ok(!$uno("SELECT COUNT(*) FROM solicitud_candidatura WHERE c_proyecto = ?", [$extra['D']]), 'La cuarta postulación se bloquea (RN-03)');
$alumno->post("mod_alumno/proyecto.php?id=$p1");
ok((int) $uno("SELECT COUNT(*) FROM solicitud_candidatura WHERE c_proyecto = ? AND matricula = '20260001'", [$p1]) === 1, 'No se duplica la postulación al mismo proyecto');

$c_b = $uno("SELECT c_candidatura FROM solicitud_candidatura WHERE c_proyecto = ?", [$extra['B']]);
$otro = (new Cliente($BASE))->entrar('ana@banco.test', 'Inicial2026');
$otro->post('mod_alumno/solicitudes.php', ['c_candidatura' => $c_b]);
ok($uno('SELECT estado FROM solicitud_candidatura WHERE c_candidatura = ?', [$c_b]) === 'pendiente', 'Otra alumna no puede cancelar una solicitud ajena');
$alumno->post('mod_alumno/solicitudes.php', ['c_candidatura' => $c_b, 'motivo' => str_repeat('m', 900)]);
ok($uno('SELECT estado FROM solicitud_candidatura WHERE c_candidatura = ?', [$c_b]) === 'cancelada'
    && mb_strlen((string) $uno('SELECT observaciones FROM solicitud_candidatura WHERE c_candidatura = ?', [$c_b])) <= 500, 'Cancelar libera el lugar y recorta el motivo a 500 caracteres');
$alumno->post("mod_alumno/proyecto.php?id={$extra['D']}");
ok((int) $uno("SELECT COUNT(*) FROM solicitud_candidatura WHERE c_proyecto = ?", [$extra['D']]) === 1, 'Con el lugar libre ya puede postularse a otro');
$alumno->post("mod_alumno/proyecto.php?id={$extra['B']}");
ok((int) $uno("SELECT COUNT(*) FROM solicitud_candidatura WHERE c_proyecto = ? AND estado = 'pendiente'", [$extra['B']]) === 0, 'Con 3 vigentes otra vez, no puede volver al proyecto que canceló');
$otro->post("mod_alumno/proyecto.php?id=$p1");

// Habilidades del alumno
foreach (['javascript:alert(1)' => 'javascript:', 'ftp://x.com/a' => 'ftp://', 'https://' => 'URL incompleta'] as $url => $nombre) {
    $alumno->post('mod_alumno/habilidades.php', ['habilidad' => 'Python', 'nivel' => 'basico', 'evidencia' => $url]);
    ok(!$uno("SELECT COUNT(*) FROM perfil_habilidad WHERE matricula = '20260001' AND nombre_habilidad = 'Python'"), "Evidencia rechaza $nombre");
}
$alumno->post('mod_alumno/habilidades.php', ['habilidad' => 'Python', 'nivel' => 'experto']);
ok(!$uno("SELECT COUNT(*) FROM perfil_habilidad WHERE matricula = '20260001' AND nombre_habilidad = 'Python'"), 'Nivel de habilidad inventado se rechaza');
$alumno->post('mod_alumno/perfil.php', ['carrera' => 'Ing. en Sistemas Computacionales', 'semestre' => '15']);
ok((int) $uno("SELECT semestre FROM alumno WHERE matricula = '20260001'") === 7, 'Perfil: semestre 15 se rechaza');

// =====================================================================
seccion('6. Evaluación de la empresa');
// =====================================================================
$c1 = $uno("SELECT c_candidatura FROM solicitud_candidatura WHERE c_proyecto = ? AND matricula = '20260001'", [$p1]);
$c_ana = $uno("SELECT c_candidatura FROM solicitud_candidatura WHERE c_proyecto = ? AND matricula = '22020198'", [$p1]);
$n = (int) $uno('SELECT COUNT(*) FROM criterio');
$calif = fn (array $v) => ['punt' => $v, 'coment' => array_fill(0, $n, '')];
foreach (['101' => '101', '-1' => 'negativa', '50.555' => '3 decimales', 'diez' => 'texto', '' => 'vacía'] as $v => $nombre) {
    $empresa->post("mod_empresa/evaluar.php?id=$c1", $calif(array_fill(0, $n, (string) $v)));
    ok(!$uno('SELECT COUNT(*) FROM evaluacion_candidato WHERE c_candidatura = ?', [$c1]), "Calificación $nombre se rechaza");
}
$empresa2->get("mod_empresa/evaluar.php?id=$c1");
ok($empresa2->codigo === 404, 'Otra empresa no puede ver ni evaluar al candidato');
$empresa->post("mod_empresa/evaluar.php?id=$c1", $calif(['90', '80', '85', '100']) + ['preseleccionado' => '1']);
ok(abs((float) $uno('SELECT puntuacion_total FROM evaluacion_candidato WHERE c_candidatura = ?', [$c1]) - 88.0) < 0.001,
    'La puntuación ponderada se calcula en el servidor (90·40 + 80·20 + 85·25 + 100·15 = 88.00)');
ok($uno('SELECT estado FROM solicitud_candidatura WHERE c_candidatura = ?', [$c1]) === 'en_revision', 'La primera evaluación pasa la candidatura a «En revisión»');
$empresa->post("mod_empresa/evaluar.php?id=$c_ana", $calif(['60', '60', '60', '60']));

$db->exec("UPDATE criterio SET peso = 10 WHERE nombre_criterio = 'Disponibilidad'");
$empresa->post("mod_empresa/evaluar.php?id=$c1", $calif(['50', '50', '50', '50']));
ok((int) $uno('SELECT COUNT(*) FROM evaluacion_candidato WHERE c_candidatura = ?', [$c1]) === 1, 'Con pesos que no suman 100 % no se puede evaluar');
$db->exec("UPDATE criterio SET peso = 15 WHERE nombre_criterio = 'Disponibilidad'");

// =====================================================================
seccion('7. Decisión de candidaturas y cupo');
// =====================================================================
$admin->post("mod_admin/candidatura.php?id=$c1", ['decision' => 'aprobar', 'justificacion' => 'ok']);
ok($uno('SELECT estado FROM solicitud_candidatura WHERE c_candidatura = ?', [$c1]) === 'en_revision', 'Aprobar sin justificación suficiente no hace nada');
$csrf = $admin->get("mod_admin/candidatura.php?id=$c1")->csrf();
$admin->post("mod_admin/candidatura.php?id=$c1", ['csrf' => $csrf, 'decision' => 'aprobar', 'justificacion' => 'Cumple y fue preseleccionado.']);
$admin->post("mod_admin/candidatura.php?id=$c1", ['csrf' => $csrf, 'decision' => 'aprobar', 'justificacion' => 'Cumple y fue preseleccionado.']);
ok((int) $uno('SELECT COUNT(*) FROM asignacion_alumno WHERE c_candidatura = ?', [$c1]) === 1
    && (int) $uno('SELECT COUNT(*) FROM autorizacion WHERE c_candidatura = ?', [$c1]) === 1, 'Aprobar crea una sola asignación y una sola autorización');
$admin->post("mod_admin/candidatura.php?id=$c_ana", ['decision' => 'aprobar', 'justificacion' => 'Intento con el cupo lleno.']);
ok($uno('SELECT estado FROM solicitud_candidatura WHERE c_candidatura = ?', [$c_ana]) === 'en_revision'
    && !$uno('SELECT COUNT(*) FROM asignacion_alumno WHERE c_candidatura = ?', [$c_ana]), 'Con el cupo lleno (1 de 1) no se puede aprobar a otra alumna');
$alumno->post('mod_alumno/solicitudes.php', ['c_candidatura' => $c1]);
ok($uno('SELECT estado FROM solicitud_candidatura WHERE c_candidatura = ?', [$c1]) === 'aprobada', 'El alumno no puede cancelar una candidatura ya aprobada');
$c_pend = $uno("SELECT c_candidatura FROM solicitud_candidatura WHERE c_proyecto = ?", [$extra['C']]);
$admin->post("mod_admin/candidatura.php?id=$c_pend", ['decision' => 'aprobar', 'justificacion' => 'Sin evaluación de la empresa.']);
ok($uno('SELECT estado FROM solicitud_candidatura WHERE c_candidatura = ?', [$c_pend]) === 'pendiente', 'No se aprueba una candidatura que la empresa no ha evaluado');

// =====================================================================
seccion('8. Dirección de proyectos');
// =====================================================================
$profesor->post("mod_profesor/proyecto.php?id=$p1", ['experiencia' => 'corta', 'justificacion' => 'corta']);
ok(!$uno("SELECT COUNT(*) FROM solicitud_direccion WHERE c_proyecto = ?", [$p1]), 'Solicitud de dirección con textos muy cortos se rechaza');
$sol = ['experiencia' => 'Dirigí sistemas de inventarios para el puerto.', 'justificacion' => 'Es mi línea de trabajo en bases de datos.'];
$profesor->post("mod_profesor/proyecto.php?id=$p1", $sol);
$profesor->post("mod_profesor/proyecto.php?id=$p1", $sol);
ok((int) $uno("SELECT COUNT(*) FROM solicitud_direccion WHERE c_proyecto = ?", [$p1]) === 1, 'No se duplica la solicitud pendiente al mismo proyecto');
$laura = (new Cliente($BASE))->entrar('laura@banco.test', 'Inicial2026');
$laura->post("mod_profesor/proyecto.php?id=$p1", $sol);
$sd1 = $uno("SELECT c_solicitud_direccion FROM solicitud_direccion WHERE c_proyecto = ? AND num_empleado = 'PRF001'", [$p1]);
$sd2 = $uno("SELECT c_solicitud_direccion FROM solicitud_direccion WHERE c_proyecto = ? AND num_empleado = 'E1045'", [$p1]);
$admin->post("mod_admin/direccion.php?id=$sd1", ['decision' => 'aprobar', 'justificacion' => 'Experiencia adecuada.']);
$admin->post("mod_admin/direccion.php?id=$sd2", ['decision' => 'aprobar', 'justificacion' => 'Segundo director.']);
ok((int) $uno("SELECT COUNT(*) FROM asignacion_profesor ap JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion
    WHERE sd.c_proyecto = ? AND ap.estado = 'activa'", [$p1]) === 1, 'Un proyecto no puede tener dos directores activos');
$laura->get("mod_profesor/proyecto.php?id=$p1");
ok($uno('SELECT estado FROM solicitud_direccion WHERE c_solicitud_direccion = ?', [$sd2]) === 'rechazada'
    && (int) $uno('SELECT COUNT(*) FROM autorizacion WHERE c_solicitud_direccion = ?', [$sd2]) === 1, 'Al aprobar un director, las otras solicitudes pendientes se rechazan con su autorización');
ok($laura->tiene('El proyecto ya tiene director'), 'Otro profesor ve el motivo por el que ya no puede solicitar');

// Límite de 3 proyectos por profesor.
foreach (['B', 'C', 'D'] as $l) {
    $profesor->post("mod_profesor/proyecto.php?id={$extra[$l]}", $sol);
    $s = $uno('SELECT c_solicitud_direccion FROM solicitud_direccion WHERE c_proyecto = ? AND num_empleado = ?', [$extra[$l], 'PRF001']);
    if ($s) {
        $admin->post("mod_admin/direccion.php?id=$s", ['decision' => 'aprobar', 'justificacion' => 'Experiencia adecuada.']);
    }
}
ok((int) $uno("SELECT proyectos_activos FROM v_carga_profesor WHERE num_empleado = 'PRF001'") === 3, 'El profesor llega a 3 proyectos activos y no más (RN-05)');
ok(!$uno('SELECT COUNT(*) FROM solicitud_direccion WHERE c_proyecto = ? AND num_empleado = ?', [$extra['D'], 'PRF001']), 'Con 3 activos ya no puede ni solicitar un cuarto');

// =====================================================================
seccion('9. Ciclo de vida del proyecto');
// =====================================================================
$accion = fn (string $p, string $a) => $admin->post('mod_admin/proyecto_accion.php', ['id' => $p, 'accion' => $a, 'volver' => 'mod_admin/proyectos.php'], 'mod_admin/proyectos.php');
$accion($extra['B'], 'iniciar');
ok($uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$extra['B']]) === 'publicado', 'No se inicia un proyecto sin alumnos asignados');
$accion($p1, 'finalizar');
ok($uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'publicado', 'No se finaliza un proyecto que no ha iniciado');
$admin->post('mod_admin/proyecto_accion.php', ['id' => $p1, 'accion' => 'iniciar', 'volver' => 'https://sitio-malicioso.com/'], 'mod_admin/proyectos.php');
ok($uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'en_curso' && !str_contains((string) $admin->destino, 'malicioso'),
    'Iniciar funciona y no redirige a sitios externos');
$accion($p1, 'finalizar');
ok($uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'finalizado'
    && !$uno("SELECT COUNT(*) FROM asignacion_alumno aa JOIN solicitud_candidatura sc ON sc.c_candidatura = aa.c_candidatura WHERE sc.c_proyecto = ? AND aa.estado = 'activa'", [$p1])
    && $uno('SELECT estado FROM solicitud_candidatura WHERE c_candidatura = ?', [$c_ana]) === 'cancelada', 'Finalizar cierra asignaciones y cancela candidaturas abiertas');
ok((int) $uno("SELECT proyectos_activos FROM v_carga_profesor WHERE num_empleado = 'PRF001'") === 2, 'El director libera un lugar al finalizar');
$accion($p1, 'cancelar');
ok($uno('SELECT estado FROM proyecto WHERE c_proyecto = ?', [$p1]) === 'finalizado', 'Un proyecto finalizado ya no se puede cancelar');
$alumno->post("mod_alumno/proyecto.php?id=$p1");
ok((int) $uno("SELECT COUNT(*) FROM solicitud_candidatura WHERE c_proyecto = ? AND matricula = '20260001'", [$p1]) === 1, 'No se puede postular a un proyecto finalizado');

// =====================================================================
seccion('10. Reglas de la base (segunda capa)');
// =====================================================================
// El alumno tiene 2 vigentes (C y D); se llena la tercera directo en la base y la cuarta debe fallar.
$db->exec("INSERT INTO proyecto (c_proyecto, rfc_empresa, titulo, estado) VALUES ('PRY-00009999', 'TDE200101AB1', 'Extra', 'borrador')");
$db->exec("UPDATE proyecto SET descripcion='d', problematica='p', objetivos='o', justificacion='j', alcance='a', duracion_estimada=4, modalidad='remota',
    fecha_inicio='2030-01-01', fecha_fin='2030-02-01', estado='publicado' WHERE c_proyecto = 'PRY-00009999'");
$db->exec("INSERT INTO solicitud_candidatura (c_candidatura, matricula, c_proyecto) VALUES ('CAN-00009997', '20260001', '{$extra['B']}')");
ok((int) $uno("SELECT COUNT(*) FROM v_candidatura_vigente WHERE matricula = '20260001'") === 3, 'Directo en la base: la tercera candidatura vigente sí entra');
rechaza($db, "INSERT INTO solicitud_candidatura (c_candidatura, matricula, c_proyecto) VALUES ('CAN-00009999', '20260001', 'PRY-00009999')",
    'Trigger: 4.ª candidatura vigente directa en la base', 'RN-03');
rechaza($db, "INSERT INTO solicitud_candidatura (c_candidatura, matricula, c_proyecto) VALUES ('CAN-00009998', '22020198', '$p_rech')",
    'Trigger: candidatura a proyecto rechazado', 'RN-09');
rechaza($db, "DELETE FROM solicitud_candidatura WHERE c_candidatura = '$c1'", 'Trigger: las candidaturas no se borran', 'RN-13');
rechaza($db, "UPDATE solicitud_candidatura SET estado = 'pendiente' WHERE c_candidatura = '$c_b'", 'Trigger: una candidatura cancelada no revive', 'RN-14');
rechaza($db, "UPDATE proyecto SET estado = 'publicado' WHERE c_proyecto = '$p1'", 'Trigger: un proyecto finalizado no cambia de estado', 'RN-14');
rechaza($db, "UPDATE autorizacion SET decision = 'rechazada' LIMIT 1", 'Trigger: las autorizaciones no se modifican', 'RN-12');
rechaza($db, 'DELETE FROM autorizacion LIMIT 1', 'Trigger: las autorizaciones no se borran', 'RN-12');
rechaza($db, 'DELETE FROM historial_auditoria LIMIT 1', 'Trigger: el historial no se borra', 'RNF-10');
rechaza($db, "UPDATE historial_auditoria SET resultado = 'exito' LIMIT 1", 'Trigger: el historial no se modifica', 'RNF-10');
rechaza($db, "UPDATE proyecto SET estado = 'publicado', descripcion = NULL WHERE c_proyecto = '$borrador'", 'CHECK: fuera de borrador los datos deben estar completos');
rechaza($db, "INSERT INTO empresa (rfc_empresa, c_usuario, razon_social, sector) VALUES ('abc123456xyz', 'USR-00000001', 'x', 'y')", 'CHECK: RFC en minúsculas');
rechaza($db, "UPDATE usuario SET rol = 'root' WHERE c_usuario = 'USR-00000001'", 'CHECK: rol inválido');
rechaza($db, "UPDATE criterio SET peso = 0 WHERE nombre_criterio = 'Disponibilidad'", 'CHECK: peso de criterio en 0');
rechaza($db, "UPDATE asignacion_alumno SET estado = 'activa', fecha_cierre = NULL LIMIT 1", 'Trigger: una asignación cerrada no se reabre', 'RN-14');
rechaza($db, "INSERT INTO autorizacion (c_autorizacion, num_empleado_admin, c_proyecto, c_candidatura, decision, justificacion) VALUES ('AUT-00009999', 'ADM001', '$p1', '$c1', 'aprobada', 'x')",
    'CHECK: una autorización apunta a una sola cosa');
ok((int) $uno("SELECT COUNT(*) FROM v_candidatura_vigente v LEFT JOIN alumno a ON a.matricula = v.matricula WHERE a.matricula IS NULL") === 0, 'Sin candidaturas huérfanas');
ok((int) $uno("SELECT MAX(n) FROM (SELECT COUNT(*) n FROM v_candidatura_vigente GROUP BY matricula) t") <= 3, 'Ningún alumno quedó con más de 3 vigentes');
ok((int) $uno("SELECT MAX(proyectos_activos) FROM v_carga_profesor") <= 3, 'Ningún profesor quedó con más de 3 activos');
ok((int) $uno("SELECT COUNT(*) FROM v_proyecto_resumen WHERE cupo_alumnos IS NOT NULL AND alumnos_asignados > cupo_alumnos") === 0, 'Ningún proyecto rebasó su cupo');
ok((int) $uno("SELECT COUNT(*) FROM historial_auditoria WHERE c_usuario NOT IN (SELECT c_usuario FROM usuario)") === 0, 'Todo el historial apunta a usuarios existentes');

// =====================================================================
seccion('11. Reportes');
// =====================================================================
$admin->get('mod_admin/reportes.php?csv=1');
ok(str_starts_with($admin->html, "\xEF\xBB\xBF") && str_contains($admin->html, 'Inventarios portuarios'), 'El CSV sale con BOM UTF-8 y con los datos');
$db->exec("UPDATE proyecto SET titulo = '=HYPERLINK(\"http://x\")' WHERE c_proyecto = '{$extra['D']}'");
$admin->get('mod_admin/reportes.php?csv=1');
ok(str_contains($admin->html, "'=HYPERLINK"), 'Una celda que empieza con = se neutraliza en el CSV');
foreach (['?tab=alumnos&periodo=1900-1', '?estado=inventado&sector=<x>', '?tab=../../etc'] as $q) {
    $admin->get("mod_admin/reportes.php$q");
    ok($admin->codigo === 200, "Reportes aguanta filtros raros: $q");
}
$admin->get('mod_admin/historial.php?desde=2026-13-45&usuario=%27%20OR%201=1--');
ok($admin->codigo === 200, 'El historial aguanta fechas inválidas y texto con comillas');

// ---------------------------------------------------------------------
// Resultado
// ---------------------------------------------------------------------
echo "\n" . str_repeat('-', 60) . "\n";
echo $fallos ? count($fallos) . " de $total pruebas fallaron:\n  - " . implode("\n  - ", $fallos) . "\n" : "Las $total pruebas pasaron.\n";

$restaurar();
if (!isset($opciones['conservar'])) {
    exec($mysql("-e \"DROP DATABASE IF EXISTS $BD\""));
    echo "Base temporal borrada; la aplicación volvió a su base normal.\n";
} else {
    echo "Base temporal conservada: $BD (la aplicación ya volvió a su base normal).\n";
}
exit($fallos ? 1 : 0);
