<?php
/**
 * Datos de prueba completos: deja la base lista para probar todos los flujos.
 *
 * Uso (desde la raíz del proyecto, con MySQL de XAMPP encendido):
 *   C:\xampp\php\php.exe sql\sembrar.php --confirmar
 *
 * Qué hace:
 *   1. BORRA y vuelve a crear la base (la de bd/config.local.php, o la que se
 *      indique con --base=nombre) con sql/banco_proyectos.sql.
 *   2. Importa sql/datos_prueba.sql (las 4 cuentas base, habilidades y criterios).
 *   3. Agrega más cuentas y arma escenarios con proyectos en todos los estados,
 *      candidaturas, evaluaciones, solicitudes de dirección, asignaciones,
 *      notificaciones e historial, respetando las mismas reglas que la app
 *      (los triggers de la base se encargan de revisarlo).
 *
 * Se puede correr las veces que se quiera: cada corrida empieza desde cero.
 * Se borra la base completa porque el historial de auditoría y las
 * autorizaciones no se pueden borrar fila por fila.
 *
 * Todas las cuentas usan la contraseña Prueba2026! (hasheada con
 * password_hash, igual que la app). La guía de pruebas con las credenciales
 * y los pasos de cada flujo está en pruebas/GUIA_PRUEBAS.md.
 *
 * Opciones: --confirmar (obligatoria), --base=nombre,
 *           --mysql=C:\xampp\mysql\bin\mysql.exe
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
$opc = getopt('', ['confirmar', 'base:', 'mysql:']);
$cfg_archivo = is_file("$raiz/bd/config.local.php") ? "$raiz/bd/config.local.php" : "$raiz/bd/config.ejemplo.php";
$cfg = require $cfg_archivo;
$BD = $opc['base'] ?? $cfg['db_name'];
$MYSQL = $opc['mysql'] ?? 'C:\\xampp\\mysql\\bin\\mysql.exe';
$PW = 'Prueba2026!';

if (!preg_match('/^[A-Za-z0-9_]+$/', $BD)) {
    exit("Nombre de base no válido: $BD\n");
}
if (!isset($opc['confirmar'])) {
    exit("Este script BORRA la base «{$BD}» y la vuelve a crear con datos de prueba.\n"
       . "Para continuar: C:\\xampp\\php\\php.exe sql\\sembrar.php --confirmar\n");
}
if (!is_file($MYSQL)) {
    exit("No encontré el cliente de MySQL en $MYSQL (usa --mysql=ruta).\n");
}

// ---------------------------------------------------------------------
// 1. Base desde cero: esquema + datos base
// ---------------------------------------------------------------------
$cred = sprintf('-h%s -u%s %s', escapeshellarg($cfg['db_host']), escapeshellarg($cfg['db_user']),
    $cfg['db_pass'] !== '' ? '-p' . escapeshellarg($cfg['db_pass']) : '');
$mysql = fn (string $args) => '"' . $MYSQL . '" ' . $cred . ' --default-character-set=utf8mb4 ' . $args;

echo "Creando la base $BD desde cero...\n";
exec($mysql("-e \"DROP DATABASE IF EXISTS $BD; CREATE DATABASE $BD CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""), $o, $rc);
if ($rc !== 0) {
    exit("No se pudo crear la base. ¿Está encendido MySQL?\n");
}
$script = file_get_contents("$raiz/sql/banco_proyectos.sql");
$script = preg_replace('/^CREATE DATABASE IF NOT EXISTS banco_proyectos.*?;\s*$/ms', '', $script, 1);
$script = preg_replace('/^USE banco_proyectos;\s*$/m', '', $script);
$tmp = tempnam(sys_get_temp_dir(), 'bp');
file_put_contents($tmp, $script);
exec($mysql("$BD < " . escapeshellarg($tmp)), $o, $rc1);
unlink($tmp);
exec($mysql("$BD < " . escapeshellarg("$raiz/sql/datos_prueba.sql")), $o, $rc2);
if ($rc1 || $rc2) {
    exit("No se pudo importar sql/banco_proyectos.sql o sql/datos_prueba.sql.\n");
}

$pdo = new PDO("mysql:host={$cfg['db_host']};dbname=$BD;charset=utf8mb4", $cfg['db_user'], $cfg['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->prepare('SET time_zone = ?')->execute([$cfg['db_timezone']]);
$zona = new DateTimeZone($cfg['db_timezone']);

// ---------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------

/** Fecha y hora relativas a hoy: hace(12, '10:30') = hace 12 días a las 10:30. */
function hace(int $dias, string $hora = '10:00'): string
{
    global $zona;
    return (new DateTimeImmutable("today $hora", $zona))->modify("-$dias days")->format('Y-m-d H:i:s');
}

/** Solo fecha, relativa a hoy (positivo = futuro). */
function dia(int $dias): string
{
    global $zona;
    return (new DateTimeImmutable('today', $zona))->modify(($dias >= 0 ? '+' : '') . "$dias days")->format('Y-m-d');
}

function sql(string $q, array $p = []): PDOStatement
{
    global $pdo;
    $s = $pdo->prepare($q);
    $s->execute($p);
    return $s;
}

function folio(string $prefijo): string
{
    global $pdo;
    $pdo->prepare('CALL sp_siguiente_folio(?, @f)')->execute([$prefijo]);
    return (string) $pdo->query('SELECT @f')->fetchColumn();
}

function avisar(string $c_usuario, string $mensaje, string $fecha): void
{
    // Lo de los últimos 3 días queda sin leer, para que la campana tenga avisos.
    $leida = strtotime($fecha) < strtotime(hace(3, '00:00')) ? 1 : 0;
    sql('INSERT INTO notificacion (c_notificacion, c_usuario, mensaje, fecha, leida) VALUES (?, ?, ?, ?, ?)',
        [folio('NTF'), $c_usuario, $mensaje, $fecha, $leida]);
}

function auditar(string $c_usuario, string $operacion, string $entidad, string $registro, string $fecha): void
{
    sql('INSERT INTO historial_auditoria (c_historial, c_usuario, operacion, entidad_afectada, registro_afectado, resultado, fecha)
         VALUES (?, ?, ?, ?, ?, ?, ?)', [folio('HIS'), $c_usuario, $operacion, $entidad, $registro, 'exito', $fecha]);
}

function cuenta_usuario(string $correo): string
{
    return (string) sql('SELECT c_usuario FROM usuario WHERE correo = ?', [$correo])->fetchColumn();
}

function nuevo_usuario(string $correo, string $nombre, string $tel, string $rol, string $estado = 'activo'): string
{
    global $PW;
    $id = folio('USR');
    sql('INSERT INTO usuario (c_usuario, nombre, correo, telefono, contrasena_hash, rol, estado) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$id, $nombre, $correo, $tel, password_hash($PW, PASSWORD_BCRYPT), $rol, $estado]);
    return $id;
}

// Datos de cada actor que se usan para armar los mensajes.
$U = [];   // clave corta => ['c_usuario', 'nombre', 'clave' (matrícula, RFC o num. empleado)]

function actor(string $clave_corta): array
{
    global $U;
    return $U[$clave_corta];
}

// ---------------------------------------------------------------------
// 2. Cuentas
// ---------------------------------------------------------------------
echo "Creando cuentas...\n";
$pdo->beginTransaction();

// Las 4 cuentas base de datos_prueba.sql
$U['admin']    = ['c_usuario' => cuenta_usuario('admin@banco.test'),    'nombre' => 'Administrador de Prueba', 'clave' => 'ADM001'];
$U['empresa']  = ['c_usuario' => cuenta_usuario('empresa@banco.test'),  'nombre' => 'Tecnología de Ejemplo',   'clave' => 'TDE200101AB1'];
$U['alumno']   = ['c_usuario' => cuenta_usuario('alumno@banco.test'),   'nombre' => 'Alumno de Prueba',        'clave' => '20260001'];
$U['profesor'] = ['c_usuario' => cuenta_usuario('profesor@banco.test'), 'nombre' => 'Profesor de Prueba',      'clave' => 'PRF001'];

// Segunda empresa
$id = nuevo_usuario('empresa2@banco.test', 'Logística del Bajío', '4490000102', 'empresa');
sql('INSERT INTO empresa (rfc_empresa, c_usuario, razon_social, sector) VALUES (?, ?, ?, ?)',
    ['LDB190315XY2', $id, 'Logística del Bajío S.A. de C.V.', 'Transporte y logística']);
$U['empresa2'] = ['c_usuario' => $id, 'nombre' => 'Logística del Bajío', 'clave' => 'LDB190315XY2'];

// Alumnos
$alumnos = [
    'alumno2' => ['alumno2@banco.test', 'Ana Martínez López',     '4490000202', '20260002', 'Ingeniería en Sistemas Computacionales', 8, 'Promedio 94. Servicio social en el centro de cómputo.'],
    'alumno3' => ['alumno3@banco.test', 'Bruno Hernández Ruiz',   '4490000203', '20260003', 'Ingeniería en Tecnologías de la Información', 6, 'Promedio 86.'],
    'alumno4' => ['alumno4@banco.test', 'Carla Gómez Torres',     '4490000204', '20260004', 'Ingeniería en Sistemas Computacionales', 9, 'Promedio 91. Prácticas en una agencia web.'],
    'alumno5' => ['alumno5@banco.test', 'Diego Ramírez Silva',    '4490000205', '20260005', 'Ingeniería Informática', 7, 'Promedio 88.'],
    'inactivo' => ['inactivo@banco.test', 'Elena Castro Vega',    '4490000206', '20260006', 'Ingeniería Informática', 5, null],
];
foreach ($alumnos as $k => [$correo, $nombre, $tel, $mat, $carrera, $sem, $datos]) {
    $id = nuevo_usuario($correo, $nombre, $tel, 'alumno', $k === 'inactivo' ? 'inactivo' : 'activo');
    sql('INSERT INTO alumno (matricula, c_usuario, carrera, semestre, datos_academicos) VALUES (?, ?, ?, ?, ?)',
        [$mat, $id, $carrera, $sem, $datos]);
    $U[$k] = ['c_usuario' => $id, 'nombre' => $nombre, 'clave' => $mat];
}

// Profesores
$profesores = [
    'profesor2' => ['profesor2@banco.test', 'Mtra. Laura Ortiz Méndez', '4490000302', 'PRF002', 'Ciencia de datos', 'Asesora de residencias en análisis de datos desde 2019.'],
    'profesor3' => ['profesor3@banco.test', 'Dr. Ricardo Navarro Paz',  '4490000303', 'PRF003', 'Sistemas distribuidos', 'Director de 12 proyectos de vinculación con empresas.'],
];
foreach ($profesores as $k => [$correo, $nombre, $tel, $num, $esp, $exp]) {
    $id = nuevo_usuario($correo, $nombre, $tel, 'profesor');
    sql('INSERT INTO profesor (num_empleado, c_usuario, especialidad, experiencia) VALUES (?, ?, ?, ?)', [$num, $id, $esp, $exp]);
    $U[$k] = ['c_usuario' => $id, 'nombre' => $nombre, 'clave' => $num];
}

// Perfiles de habilidades (alumno@banco.test ya trae PHP, MySQL y Trabajo en equipo)
$perfiles = [
    '20260002' => [['PHP', 'avanzado', 'https://github.com/'], ['MySQL / MariaDB', 'avanzado', null], ['JavaScript', 'intermedio', null],
                   ['Git', 'intermedio', null], ['Comunicación', 'avanzado', null]],
    '20260003' => [['Python', 'intermedio', null], ['HTML y CSS', 'intermedio', null], ['Trabajo en equipo', 'intermedio', null]],
    '20260004' => [['Python', 'avanzado', 'https://github.com/'], ['MySQL / MariaDB', 'intermedio', null], ['Resolución de problemas', 'avanzado', null]],
    '20260005' => [['JavaScript', 'avanzado', null], ['HTML y CSS', 'avanzado', null], ['PHP', 'basico', null], ['Responsabilidad', 'avanzado', null]],
    '20260006' => [['HTML y CSS', 'basico', null]],
];
foreach ($perfiles as $mat => $lista) {
    foreach ($lista as [$hab, $nivel, $evid]) {
        sql('INSERT INTO perfil_habilidad (matricula, nombre_habilidad, nivel, evidencia) VALUES (?, ?, ?, ?)', [$mat, $hab, $nivel, $evid]);
    }
}
$pdo->commit();

// ---------------------------------------------------------------------
// 3. Pasos del flujo (mismas reglas y mensajes que la app)
// ---------------------------------------------------------------------
$P = [];   // clave corta => ['id', 'titulo', 'empresa' (clave corta del actor)]

/** Datos completos de un proyecto (todo lo que exige la base fuera de borrador). */
function datos(string $desc, string $problema, string $objetivos, int $semanas, string $modalidad, int $inicio, int $cupo, array $extra = []): array
{
    return array_merge([
        'descripcion' => $desc,
        'problematica' => $problema,
        'objetivos' => $objetivos,
        'justificacion' => 'La empresa necesita resolverlo en el corto plazo y el proyecto es adecuado para una residencia profesional.',
        'alcance' => 'Análisis, diseño, desarrollo, pruebas y entrega con documentación técnica y manual de usuario.',
        'requisitos_academicos' => 'Haber cursado al menos el 70 % de los créditos de la carrera.',
        'duracion_estimada' => $semanas,
        'modalidad' => $modalidad,
        'fecha_inicio' => dia($inicio),
        'fecha_fin' => dia($inicio + $semanas * 7),
        'recursos' => 'Equipo de cómputo, acceso a servidores de pruebas y un asesor por parte de la empresa.',
        'cupo_alumnos' => $cupo,
    ], $extra);
}

function nuevo_proyecto(string $k, string $titulo, string $empresa, string $estado, array $d, array $requisitos, int $dias): void
{
    global $P;
    $id = folio('PRY');
    $d = array_merge([
        'descripcion' => null, 'problematica' => null, 'objetivos' => null, 'justificacion' => null, 'alcance' => null,
        'requisitos_academicos' => null, 'duracion_estimada' => null, 'modalidad' => null, 'fecha_inicio' => null,
        'fecha_fin' => null, 'recursos' => null, 'cupo_alumnos' => null,
    ], $d);
    $cols = implode(', ', array_keys($d));
    $marcas = implode(', ', array_fill(0, count($d), '?'));
    sql("INSERT INTO proyecto (c_proyecto, rfc_empresa, titulo, $cols, estado, fecha_registro) VALUES (?, ?, ?, $marcas, ?, ?)",
        array_merge([$id, actor($empresa)['clave'], $titulo], array_values($d), [$estado, hace($dias, '09:15')]));
    foreach ($requisitos as [$hab, $nivel, $oblig]) {
        sql('INSERT INTO requisito_proyecto (c_proyecto, nombre_habilidad, nivel_minimo, obligatorio) VALUES (?, ?, ?, ?)', [$id, $hab, $nivel, $oblig]);
    }
    $P[$k] = ['id' => $id, 'titulo' => $titulo, 'empresa' => $empresa];
    auditar(actor($empresa)['c_usuario'], 'crear', 'proyecto', $id, hace($dias, '09:15'));
    if ($estado !== 'borrador') {
        auditar(actor($empresa)['c_usuario'], 'enviar_revision', 'proyecto', $id, hace($dias, '09:20'));
        avisar(actor($empresa)['c_usuario'], "El proyecto «{$titulo}» se envió a revisión.", hace($dias, '09:20'));
    }
}

/** Decisión del administrador sobre un proyecto en revisión. */
function revisar_proyecto(string $k, string $decision, string $justificacion, int $dias): void
{
    global $P;
    $p = $P[$k];
    $fecha = hace($dias, '12:00');
    $valor = ['aprobar' => 'aprobada', 'devolver' => 'devuelta', 'rechazar' => 'rechazada'][$decision];
    sql('INSERT INTO autorizacion (c_autorizacion, num_empleado_admin, c_proyecto, decision, fecha, justificacion) VALUES (?, ?, ?, ?, ?, ?)',
        [folio('AUT'), actor('admin')['clave'], $p['id'], $valor, $fecha, $justificacion]);
    auditar(actor('admin')['c_usuario'], $decision, 'proyecto', $p['id'], $fecha);
    avisar(actor($p['empresa'])['c_usuario'], match ($decision) {
        'aprobar'  => "Tu proyecto «{$p['titulo']}» fue aprobado y publicado.",
        'devolver' => "Tu proyecto «{$p['titulo']}» fue devuelto para correcciones.",
        'rechazar' => "Tu proyecto «{$p['titulo']}» fue rechazado.",
    }, $fecha);
}

/** Postulación de un alumno (nace en pendiente). Devuelve el folio CAN. */
function postular(string $alumno, string $k, int $dias): string
{
    global $P;
    $p = $P[$k];
    $id = folio('CAN');
    $fecha = hace($dias, '11:00');
    sql('INSERT INTO solicitud_candidatura (c_candidatura, matricula, c_proyecto, fecha) VALUES (?, ?, ?, ?)',
        [$id, actor($alumno)['clave'], $p['id'], $fecha]);
    auditar(actor($alumno)['c_usuario'], 'postular', 'proyecto', $p['id'], $fecha);
    avisar(actor($p['empresa'])['c_usuario'], actor($alumno)['nombre'] . " se postuló a «{$p['titulo']}».", $fecha);
    return $id;
}

/** Evaluación de la empresa: calificaciones en el orden de los criterios (pesos 40, 20, 25, 15). */
function evaluar(string $c_cand, array $calif, bool $presel, string $comentario, int $dias): void
{
    $c = sql('SELECT sc.c_proyecto, sc.matricula, sc.estado, p.titulo, p.rfc_empresa, a.c_usuario
              FROM solicitud_candidatura sc JOIN proyecto p ON p.c_proyecto = sc.c_proyecto
              JOIN alumno a ON a.matricula = sc.matricula WHERE sc.c_candidatura = ?', [$c_cand])->fetch();
    $criterios = sql('SELECT nombre_criterio, peso FROM criterio ORDER BY peso DESC, nombre_criterio')->fetchAll();
    $total = 0.0;
    foreach ($criterios as $i => $cr) {
        $total += $calif[$i] * (float) $cr['peso'] / 100;
    }
    $fecha = hace($dias, '16:30');
    $ev = folio('EVA');
    sql('INSERT INTO evaluacion_candidato (c_evaluacion, c_candidatura, rfc_empresa, fecha, puntuacion_total, comentarios, preseleccionado)
         VALUES (?, ?, ?, ?, ?, ?, ?)', [$ev, $c_cand, $c['rfc_empresa'], $fecha, round($total, 2), $comentario, $presel ? 1 : 0]);
    foreach ($criterios as $i => $cr) {
        sql('INSERT INTO evaluacion_criterio (c_evaluacion, nombre_criterio, puntuacion) VALUES (?, ?, ?)', [$ev, $cr['nombre_criterio'], $calif[$i]]);
    }
    sql("UPDATE solicitud_candidatura SET estado = 'en_revision' WHERE c_candidatura = ? AND estado = 'pendiente'", [$c_cand]);
    $empresa = sql('SELECT c_usuario FROM empresa WHERE rfc_empresa = ?', [$c['rfc_empresa']])->fetchColumn();
    $nombre = sql('SELECT nombre FROM usuario WHERE c_usuario = ?', [$c['c_usuario']])->fetchColumn();
    auditar($empresa, 'evaluar', 'solicitud_candidatura', $c_cand, $fecha);
    avisar($c['c_usuario'], "La empresa evaluó tu candidatura a «{$c['titulo']}»" . ($presel ? ' y te preseleccionó.' : '.'), $fecha);
    avisar(actor('admin')['c_usuario'], "Hay una candidatura por decidir: $nombre en «{$c['titulo']}».", $fecha);
}

/** Decisión del administrador sobre una candidatura en revisión (aprobar crea la asignación). */
function decidir_candidatura(string $c_cand, bool $aprobar, string $justificacion, int $dias): void
{
    $c = sql('SELECT p.titulo, a.c_usuario, u.nombre, e.c_usuario AS empresa FROM solicitud_candidatura sc
              JOIN proyecto p ON p.c_proyecto = sc.c_proyecto JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
              JOIN alumno a ON a.matricula = sc.matricula JOIN usuario u ON u.c_usuario = a.c_usuario
              WHERE sc.c_candidatura = ?', [$c_cand])->fetch();
    $fecha = hace($dias, '13:00');
    sql("UPDATE solicitud_candidatura SET estado = ? WHERE c_candidatura = ? AND estado = 'en_revision'", [$aprobar ? 'aprobada' : 'rechazada', $c_cand]);
    sql('INSERT INTO autorizacion (c_autorizacion, num_empleado_admin, c_candidatura, decision, fecha, justificacion) VALUES (?, ?, ?, ?, ?, ?)',
        [folio('AUT'), actor('admin')['clave'], $c_cand, $aprobar ? 'aprobada' : 'rechazada', $fecha, $justificacion]);
    if ($aprobar) {
        sql('INSERT INTO asignacion_alumno (c_asignacion_alumno, c_candidatura) VALUES (?, ?)', [folio('AAL'), $c_cand]);
        avisar($c['c_usuario'], "¡Fuiste asignado al proyecto «{$c['titulo']}»!", $fecha);
        avisar($c['empresa'], "El Tec asignó a {$c['nombre']} al proyecto «{$c['titulo']}».", $fecha);
    } else {
        avisar($c['c_usuario'], "Tu candidatura a «{$c['titulo']}» fue rechazada.", $fecha);
        avisar($c['empresa'], "El Tec no autorizó la candidatura de {$c['nombre']} al proyecto «{$c['titulo']}».", $fecha);
    }
    auditar(actor('admin')['c_usuario'], $aprobar ? 'asignar' : 'rechazar', 'solicitud_candidatura', $c_cand, $fecha);
}

/** Solicitud de dirección de un profesor (nace en pendiente). Devuelve el folio SDI. */
function solicitar_direccion(string $profesor, string $k, string $justificacion, int $dias): string
{
    global $P;
    $p = $P[$k];
    $id = folio('SDI');
    $fecha = hace($dias, '10:30');
    $exp = sql('SELECT experiencia FROM profesor WHERE num_empleado = ?', [actor($profesor)['clave']])->fetchColumn()
        ?: 'Experiencia en dirección de proyectos de software.';
    sql('INSERT INTO solicitud_direccion (c_solicitud_direccion, num_empleado, c_proyecto, fecha, experiencia, justificacion) VALUES (?, ?, ?, ?, ?, ?)',
        [$id, actor($profesor)['clave'], $p['id'], $fecha, $exp, $justificacion]);
    auditar(actor($profesor)['c_usuario'], 'solicitar_direccion', 'proyecto', $p['id'], $fecha);
    avisar(actor('admin')['c_usuario'], actor($profesor)['nombre'] . " solicitó dirigir «{$p['titulo']}».", $fecha);
    return $id;
}

function aprobar_direccion(string $c_sol, string $justificacion, int $dias): void
{
    $s = sql('SELECT p.titulo, pr.c_usuario, u.nombre, e.c_usuario AS empresa FROM solicitud_direccion sd
              JOIN proyecto p ON p.c_proyecto = sd.c_proyecto JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
              JOIN profesor pr ON pr.num_empleado = sd.num_empleado JOIN usuario u ON u.c_usuario = pr.c_usuario
              WHERE sd.c_solicitud_direccion = ?', [$c_sol])->fetch();
    $fecha = hace($dias, '13:30');
    sql("UPDATE solicitud_direccion SET estado = 'aprobada' WHERE c_solicitud_direccion = ? AND estado = 'pendiente'", [$c_sol]);
    sql('INSERT INTO autorizacion (c_autorizacion, num_empleado_admin, c_solicitud_direccion, decision, fecha, justificacion) VALUES (?, ?, ?, ?, ?, ?)',
        [folio('AUT'), actor('admin')['clave'], $c_sol, 'aprobada', $fecha, $justificacion]);
    sql('INSERT INTO asignacion_profesor (c_asignacion_profesor, c_solicitud_direccion) VALUES (?, ?)', [folio('APR'), $c_sol]);
    auditar(actor('admin')['c_usuario'], 'asignar', 'solicitud_direccion', $c_sol, $fecha);
    avisar($s['c_usuario'], "Tu solicitud para dirigir «{$s['titulo']}» fue aprobada. Ya eres el director.", $fecha);
    avisar($s['empresa'], "{$s['nombre']} será el director del proyecto «{$s['titulo']}».", $fecha);
}

function rechazar_direccion(string $c_sol, string $justificacion, int $dias): void
{
    $s = sql('SELECT p.titulo, pr.c_usuario FROM solicitud_direccion sd JOIN proyecto p ON p.c_proyecto = sd.c_proyecto
              JOIN profesor pr ON pr.num_empleado = sd.num_empleado WHERE sd.c_solicitud_direccion = ?', [$c_sol])->fetch();
    $fecha = hace($dias, '13:45');
    sql("UPDATE solicitud_direccion SET estado = 'rechazada' WHERE c_solicitud_direccion = ? AND estado = 'pendiente'", [$c_sol]);
    sql('INSERT INTO autorizacion (c_autorizacion, num_empleado_admin, c_solicitud_direccion, decision, fecha, justificacion) VALUES (?, ?, ?, ?, ?, ?)',
        [folio('AUT'), actor('admin')['clave'], $c_sol, 'rechazada', $fecha, $justificacion]);
    auditar(actor('admin')['c_usuario'], 'rechazar', 'solicitud_direccion', $c_sol, $fecha);
    avisar($s['c_usuario'], "Tu solicitud para dirigir «{$s['titulo']}» fue rechazada.", $fecha);
}

function cancelar_candidatura(string $c_cand, string $alumno, int $dias): void
{
    $fecha = hace($dias, '18:00');
    sql("UPDATE solicitud_candidatura SET estado = 'cancelada' WHERE c_candidatura = ?", [$c_cand]);
    auditar(actor($alumno)['c_usuario'], 'cancelar', 'solicitud_candidatura', $c_cand, $fecha);
    $c = sql('SELECT p.titulo, e.c_usuario FROM solicitud_candidatura sc JOIN proyecto p ON p.c_proyecto = sc.c_proyecto
              JOIN empresa e ON e.rfc_empresa = p.rfc_empresa WHERE sc.c_candidatura = ?', [$c_cand])->fetch();
    avisar($c['c_usuario'], actor($alumno)['nombre'] . " canceló su solicitud a «{$c['titulo']}».", $fecha);
}

function iniciar_proyecto(string $k, int $dias): void
{
    global $P;
    $p = $P[$k];
    $fecha = hace($dias, '08:30');
    sql("UPDATE proyecto SET estado = 'en_curso' WHERE c_proyecto = ? AND estado = 'publicado'", [$p['id']]);
    auditar(actor('admin')['c_usuario'], 'iniciar', 'proyecto', $p['id'], $fecha);
    foreach (participantes($p['id']) as $u) {
        avisar($u, "El proyecto «{$p['titulo']}» inició.", $fecha);
    }
}

/** Igual que cerrar_proyecto() de la app, con fecha de cierre explícita. */
function cerrar_proyecto(string $k, string $estado, int $dias): void
{
    global $P;
    $p = $P[$k];
    $fecha = hace($dias, '17:00');
    $cierre = substr($fecha, 0, 10);
    $avisar = participantes($p['id']);
    $solicitantes = array_diff(sql("SELECT a.c_usuario FROM solicitud_candidatura sc JOIN alumno a ON a.matricula = sc.matricula
            WHERE sc.c_proyecto = ? AND sc.estado IN ('pendiente', 'en_revision')
        UNION SELECT pr.c_usuario FROM solicitud_direccion sd JOIN profesor pr ON pr.num_empleado = sd.num_empleado
            WHERE sd.c_proyecto = ? AND sd.estado = 'pendiente'", [$p['id'], $p['id']])->fetchAll(PDO::FETCH_COLUMN), $avisar);
    $asig = $estado === 'finalizado' ? 'finalizada' : 'cancelada';
    sql('UPDATE proyecto SET estado = ? WHERE c_proyecto = ?', [$estado, $p['id']]);
    sql("UPDATE asignacion_alumno aa JOIN solicitud_candidatura sc ON sc.c_candidatura = aa.c_candidatura
            SET aa.estado = ?, aa.fecha_cierre = ? WHERE sc.c_proyecto = ? AND aa.estado = 'activa'", [$asig, $cierre, $p['id']]);
    sql("UPDATE asignacion_profesor ap JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion
            SET ap.estado = ?, ap.fecha_cierre = ? WHERE sd.c_proyecto = ? AND ap.estado = 'activa'", [$asig, $cierre, $p['id']]);
    sql("UPDATE solicitud_candidatura SET estado = 'cancelada' WHERE c_proyecto = ? AND estado IN ('pendiente', 'en_revision')", [$p['id']]);
    sql("UPDATE solicitud_direccion SET estado = 'cancelada' WHERE c_proyecto = ? AND estado = 'pendiente'", [$p['id']]);
    auditar(actor('admin')['c_usuario'], $estado === 'finalizado' ? 'finalizar' : 'cancelar', 'proyecto', $p['id'], $fecha);
    foreach ($avisar as $u) {
        avisar($u, $estado === 'finalizado'
            ? "El proyecto «{$p['titulo']}» finalizó. Gracias por tu participación."
            : "El proyecto «{$p['titulo']}» fue cancelado por la administración.", $fecha);
    }
    foreach ($solicitantes as $u) {
        avisar($u, "Tu solicitud a «{$p['titulo']}» se canceló porque el proyecto "
            . ($estado === 'finalizado' ? 'finalizó.' : 'fue cancelado.') . ' Ya puedes usar ese lugar en otra solicitud.', $fecha);
    }
}

function participantes(string $c_proyecto): array
{
    return sql("SELECT e.c_usuario FROM proyecto p JOIN empresa e ON e.rfc_empresa = p.rfc_empresa WHERE p.c_proyecto = ?
        UNION SELECT a.c_usuario FROM solicitud_candidatura sc JOIN alumno a ON a.matricula = sc.matricula
               JOIN asignacion_alumno aa ON aa.c_candidatura = sc.c_candidatura WHERE sc.c_proyecto = ? AND aa.estado = 'activa'
        UNION SELECT pr.c_usuario FROM solicitud_direccion sd JOIN profesor pr ON pr.num_empleado = sd.num_empleado
               JOIN asignacion_profesor ap ON ap.c_solicitud_direccion = sd.c_solicitud_direccion WHERE sd.c_proyecto = ? AND ap.estado = 'activa'",
        [$c_proyecto, $c_proyecto, $c_proyecto])->fetchAll(PDO::FETCH_COLUMN);
}

// ---------------------------------------------------------------------
// 4. Escenarios
// ---------------------------------------------------------------------
echo "Armando proyectos, candidaturas y solicitudes...\n";
$pdo->beginTransaction();

// Bienvenida de la segunda empresa (se registró sola, como en P3)
auditar(actor('empresa2')['c_usuario'], 'registrar', 'usuario', actor('empresa2')['c_usuario'], hace(40, '08:00'));
avisar(actor('empresa2')['c_usuario'], 'Bienvenido al Banco de Proyectos. Ya puedes registrar los proyectos de Logística del Bajío S.A. de C.V.', hace(40, '08:00'));
avisar(actor('admin')['c_usuario'], 'Se registró la empresa Logística del Bajío S.A. de C.V. (LDB190315XY2).', hace(40, '08:00'));

// --- Proyectos que pasan por la revisión del administrador ---------------
$web  = [['PHP', 'intermedio', 1], ['MySQL / MariaDB', 'intermedio', 1], ['Trabajo en equipo', 'basico', 0]];
$datosr = [['Python', 'intermedio', 1], ['MySQL / MariaDB', 'basico', 1], ['Resolución de problemas', 'intermedio', 0]];
$front = [['JavaScript', 'intermedio', 1], ['HTML y CSS', 'intermedio', 1], ['Comunicación', 'basico', 0]];

// 1. Borrador incompleto (empresa): editar y enviar a revisión
nuevo_proyecto('borrador', 'Portal de citas para clínica veterinaria', 'empresa', 'borrador', [
    'descripcion' => 'Portal web para que los clientes agenden citas y reciban recordatorios.',
    'modalidad' => 'hibrida', 'cupo_alumnos' => 2,
], [['PHP', 'basico', 1]], 2);

// 2 y 3. En revisión (una de cada empresa): el administrador decide
nuevo_proyecto('revision1', 'Sistema de inventario para almacén', 'empresa', 'en_revision',
    datos('Control de entradas, salidas y existencias del almacén central.', 'El inventario se lleva en hojas de cálculo y hay diferencias cada mes.',
          'Tener existencias confiables en tiempo real y reportes semanales.', 16, 'presencial', 30, 2), $web, 4);
nuevo_proyecto('revision2', 'Rastreo de unidades de reparto', 'empresa2', 'en_revision',
    datos('Aplicación web para ver en un mapa dónde está cada unidad de reparto.', 'Los clientes llaman para saber dónde viene su pedido.',
          'Mostrar la ubicación y la hora estimada de llegada de cada pedido.', 20, 'remota', 35, 3), $front, 1);

// 4. Devuelto con observaciones: la empresa corrige y reenvía
nuevo_proyecto('devuelto', 'App de control de asistencia', 'empresa', 'en_revision',
    datos('Registro de entradas y salidas del personal con código QR.', 'El registro en papel se pierde y no se puede consultar.',
          'Registrar asistencia y generar reportes quincenales.', 12, 'hibrida', 25, 2), $web, 12);
revisar_proyecto('devuelto', 'devolver', 'Falta detallar el alcance: ¿incluye la app móvil o solo la versión web? Indiquen también qué equipo pondrá la empresa para leer los códigos QR.', 10);
sql("UPDATE proyecto SET estado = 'devuelto' WHERE c_proyecto = ?", [$P['devuelto']['id']]);

// 5. Rechazado (estado final)
nuevo_proyecto('rechazado', 'Página de ventas por catálogo', 'empresa', 'en_revision',
    datos('Sitio informativo con el catálogo de productos.', 'No tienen presencia en internet.',
          'Publicar el catálogo de productos.', 4, 'remota', 10, 1), [['HTML y CSS', 'basico', 1]], 30);
revisar_proyecto('rechazado', 'rechazar', 'El proyecto no tiene la complejidad que pide una residencia profesional (solo es un sitio informativo de 4 semanas).', 28);
sql("UPDATE proyecto SET estado = 'rechazado' WHERE c_proyecto = ?", [$P['rechazado']['id']]);

// --- Proyectos publicados y en marcha ------------------------------------
// Se insertan en revisión y se aprueban, para que tengan su autorización.
$publicar = function (string $k, string $titulo, string $empresa, array $d, array $req, int $creado, int $aprobado) {
    global $P;
    nuevo_proyecto($k, $titulo, $empresa, 'en_revision', $d, $req, $creado);
    revisar_proyecto($k, 'aprobar', 'Proyecto aprobado y publicado.', $aprobado);
    sql("UPDATE proyecto SET estado = 'publicado' WHERE c_proyecto = ?", [$P[$k]['id']]);
};

// 6. Publicado con candidaturas y solicitudes de dirección por decidir
$publicar('egresados', 'Plataforma de seguimiento de egresados', 'empresa',
    datos('Plataforma para que egresados actualicen su situación laboral y la empresa publique vacantes.', 'No hay forma de contactar a los egresados ni de medir su colocación.',
          'Tener un directorio de egresados y una bolsa de trabajo.', 16, 'hibrida', 20, 3), $web, 25, 22);

// 7. Publicado, con director y cupo lleno: listo para iniciar
$publicar('ventas', 'Tablero de indicadores de ventas', 'empresa2',
    datos('Tablero con los indicadores de ventas por región y por ruta.', 'Los reportes se arman a mano cada lunes.',
          'Automatizar el reporte semanal de ventas.', 14, 'remota', 7, 1), $front, 35, 33);

// 8. En curso, con director y un alumno asignado
$publicar('chatbot', 'Asistente virtual de atención a clientes', 'empresa',
    datos('Asistente que responde preguntas frecuentes por el sitio web.', 'El área de atención recibe las mismas preguntas todos los días.',
          'Responder automáticamente el 60 % de las preguntas frecuentes.', 18, 'presencial', -14, 3), $datosr, 60, 57);

// 9. Publicado con director y sin alumnos: para postularse
$publicar('reservas', 'Sistema de reservas de andenes', 'empresa2',
    datos('Sistema para que los transportistas reserven horario de carga y descarga.', 'Se forman filas de camiones en la entrada del centro de distribución.',
          'Reducir el tiempo de espera de los transportistas.', 16, 'hibrida', 21, 3), $web, 20, 18);

// 10. Publicado sin director ni solicitudes: para pedir dirección desde cero
$publicar('expedientes', 'Digitalización de expedientes de clientes', 'empresa2',
    datos('Escaneo, clasificación y consulta en línea de los expedientes de clientes.', 'Los expedientes en papel tardan días en localizarse.',
          'Consultar cualquier expediente en menos de un minuto.', 14, 'presencial', 28, 2), $web, 10, 7);

// 11. Finalizado (historial)
$publicar('finalizado', 'Migración de la base de clientes', 'empresa',
    datos('Migración de la base de clientes a MariaDB con limpieza de duplicados.', 'La base actual tiene registros duplicados e incompletos.',
          'Tener una sola base de clientes limpia.', 12, 'remota', -120, 2, ['fecha_fin' => dia(-30)]), $web, 150, 145);

// 12. Cancelado (historial)
$publicar('cancelado', 'Kiosco de autoservicio', 'empresa',
    datos('Kiosco táctil para que los clientes consulten precios.', 'Los clientes hacen fila solo para preguntar precios.',
          'Consultar precios sin personal.', 10, 'presencial', 15, 2), $front, 50, 48);

foreach ($P as &$p) {   // títulos para los mensajes de las funciones
    $p['titulo'] = (string) sql('SELECT titulo FROM proyecto WHERE c_proyecto = ?', [$p['id']])->fetchColumn();
}
unset($p);

// --- Historial: proyecto finalizado y cancelado --------------------------
$sd = solicitar_direccion('profesor2', 'finalizado', 'Tengo experiencia en migración y limpieza de datos.', 140);
aprobar_direccion($sd, 'La profesora tiene experiencia directa en el tema.', 138);
$c = postular('alumno2', 'finalizado', 139);
evaluar($c, [95, 85, 90, 100], true, 'Excelente perfil técnico.', 137);
decidir_candidatura($c, true, 'Cumple todos los requisitos y la empresa la preseleccionó.', 136);
iniciar_proyecto('finalizado', 120);
cerrar_proyecto('finalizado', 'finalizado', 30);

$cx = postular('alumno', 'cancelado', 45);
$sx = solicitar_direccion('profesor', 'cancelado', 'Me interesa dirigir proyectos de interfaces.', 44);
cerrar_proyecto('cancelado', 'cancelado', 40);

// --- Proyecto en curso: chatbot ------------------------------------------
$sd = solicitar_direccion('profesor3', 'chatbot', 'Dirigí dos asistentes virtuales con procesamiento de lenguaje.', 55);
$sd_rech = solicitar_direccion('profesor', 'chatbot', 'Me gustaría apoyar en la parte web del asistente.', 54);
aprobar_direccion($sd, 'Tiene la experiencia más cercana al proyecto.', 52);
rechazar_direccion($sd_rech, 'El proyecto ya tiene director asignado.', 52);
$c = postular('alumno4', 'chatbot', 50);
evaluar($c, [92, 80, 85, 90], true, 'Muy buen manejo de Python.', 45);
decidir_candidatura($c, true, 'Cumple los requisitos obligatorios y fue preseleccionada.', 44);
iniciar_proyecto('chatbot', 14);

// --- Proyecto listo para iniciar: ventas (cupo 1, ya lleno) ---------------
$sd = solicitar_direccion('profesor3', 'ventas', 'He dirigido tableros de indicadores para empresas de la región.', 30);
aprobar_direccion($sd, 'Perfil adecuado para el proyecto.', 29);
$c = postular('alumno5', 'ventas', 31);
evaluar($c, [90, 70, 85, 95], true, 'Buen dominio de JavaScript.', 27);
decidir_candidatura($c, true, 'Cumple los requisitos y la empresa lo preseleccionó.', 26);

// --- Proyecto con director y sin alumnos: reservas ------------------------
$sd = solicitar_direccion('profesor3', 'reservas', 'Experiencia en sistemas de reservas y agendas.', 16);
aprobar_direccion($sd, 'Perfil adecuado para el proyecto.', 15);
// Con esto profesor3 llega al límite de 3 proyectos activos (chatbot, ventas, reservas).

// --- Proyecto publicado con todo por decidir: egresados -------------------
// alumno5: rechazada (puede volver a postularse)
$c = postular('alumno5', 'egresados', 20);
evaluar($c, [55, 40, 70, 60], false, 'Le falta experiencia en PHP para este proyecto.', 18);
decidir_candidatura($c, false, 'No cumple el nivel mínimo de PHP que pide el proyecto.', 17);
// alumno2: evaluada y preseleccionada, en revisión → el administrador decide
$c = postular('alumno2', 'egresados', 9);
evaluar($c, [96, 85, 90, 100], true, 'Perfil muy completo, la recomendamos.', 2);
// alumno (base): pendiente → la empresa la evalúa o el alumno la cancela
postular('alumno', 'egresados', 3);
// alumno3: pendiente
postular('alumno3', 'egresados', 4);
// Dos profesores piden dirigirlo: al aprobar uno, el otro se rechaza solo
solicitar_direccion('profesor', 'egresados', 'He dirigido tres proyectos web con PHP y MySQL.', 3);
solicitar_direccion('profesor2', 'egresados', 'Puedo apoyar con el análisis de la colocación de egresados.', 2);

// alumno3 llega al límite de 3 candidaturas vigentes
$c = postular('alumno3', 'ventas', 8);
evaluar($c, [70, 60, 75, 80], false, 'Perfil aceptable; el cupo ya está ocupado.', 1);   // en revisión, pero sin cupo
postular('alumno3', 'chatbot', 6);

// Una candidatura cancelada por el propio alumno (historial de alumno@banco.test)
$c = postular('alumno', 'reservas', 12);
cancelar_candidatura($c, 'alumno', 11);

$pdo->commit();

// ---------------------------------------------------------------------
// 5. Resumen
// ---------------------------------------------------------------------
echo "\nListo. Base «{$BD}» con datos de prueba.\n\nProyectos:\n";
foreach (sql('SELECT c_proyecto, estado, titulo FROM proyecto ORDER BY c_proyecto') as $r) {
    printf("  %s  %-11s  %s\n", $r['c_proyecto'], $r['estado'], $r['titulo']);
}
echo "\nCuentas (contraseña para todas: $PW):\n";
foreach (sql('SELECT correo, rol, estado FROM usuario ORDER BY rol, correo') as $r) {
    printf("  %-24s %-13s %s\n", $r['correo'], $r['rol'], $r['estado'] === 'activo' ? '' : '(inactiva)');
}
echo "\nGuía de pruebas: pruebas/GUIA_PRUEBAS.md\n";
