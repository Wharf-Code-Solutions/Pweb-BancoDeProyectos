<?php
/** Pantalla D2 · Nuevo / editar usuario (los cuatro roles). */
require_once __DIR__ . '/../inc/inicio.php';
$admin = requiere_rol('administrador');
$pdo = conexion();

const CARRERAS = ['Ing. en Sistemas Computacionales'];

$id = isset($_GET['id']) ? (string) $_GET['id'] : null;
$editando = $id !== null;

$d = [
    'rol' => 'alumno', 'nombre' => '', 'correo' => '', 'telefono' => '',
    'matricula' => '', 'carrera' => CARRERAS[0], 'semestre' => '', 'datos_academicos' => '',
    'rfc' => '', 'razon_social' => '', 'sector' => '',
    'num_empleado' => '', 'especialidad' => '', 'experiencia' => '', 'cargo' => '',
];
$clave_original = null; // matrícula, RFC o número de empleado antes de editar

if ($editando) {
    $s = $pdo->prepare('SELECT u.*, a.matricula, a.carrera, a.semestre, a.datos_academicos,
            em.rfc_empresa AS rfc, em.razon_social, em.sector,
            COALESCE(p.num_empleado, ad.num_empleado) AS num_empleado, p.especialidad, p.experiencia, ad.cargo
        FROM usuario u
        LEFT JOIN alumno a ON a.c_usuario = u.c_usuario
        LEFT JOIN empresa em ON em.c_usuario = u.c_usuario
        LEFT JOIN profesor p ON p.c_usuario = u.c_usuario
        LEFT JOIN administrador ad ON ad.c_usuario = u.c_usuario
        WHERE u.c_usuario = ?');
    $s->execute([$id]);
    $fila = $s->fetch();
    if (!$fila) {
        error_http(404);
    }
    foreach ($d as $k => $_) {
        $d[$k] = (string) ($fila[$k] ?? '');
    }
    $clave_original = match ($d['rol']) {
        'alumno' => $d['matricula'], 'empresa' => $d['rfc'], default => $d['num_empleado'],
    };
}

$errores = [];

if (es_post()) {
    verificar_csrf();
    if (!$editando) {
        $d['rol'] = in_array($_POST['rol'] ?? '', ROLES, true) ? $_POST['rol'] : '';
    }
    $d['nombre']   = limpiar($_POST['nombre'] ?? '');
    $d['correo']   = mb_strtolower(limpiar($_POST['correo'] ?? ''));
    $d['telefono'] = preg_replace('/[\s\-()]/', '', (string) ($_POST['telefono'] ?? ''));
    $pw = (string) ($_POST['contrasena'] ?? '');

    $errores = array_filter([
        'rol'      => $d['rol'] === '' ? 'Elige el rol del usuario.' : null,
        'nombre'   => validar_requerido($d['nombre'], 'El nombre') ?? (mb_strlen($d['nombre']) > 120 ? 'Máximo 120 caracteres.' : null),
        'correo'   => validar_correo($d['correo']),
        'telefono' => validar_telefono($d['telefono']),
    ]);
    if (!isset($errores['correo']) && correo_registrado($d['correo'], $id)) {
        $errores['correo'] = 'Ese correo ya está registrado.';
    }
    if (!$editando || $pw !== '') {
        if ($e = validar_contrasena($pw, $pw)) {
            $errores['contrasena'] = $e;
        }
    }

    // Datos del rol
    $existe = function (string $tabla, string $columna, string $valor) use ($clave_original): bool {
        $s = conexion()->prepare("SELECT COUNT(*) FROM $tabla WHERE $columna = ? AND $columna <> ?");
        $s->execute([$valor, $clave_original ?? '']);
        return (int) $s->fetchColumn() > 0;
    };
    switch ($d['rol']) {
        case 'alumno':
            $d['matricula']        = limpiar($_POST['matricula'] ?? '');
            $d['carrera']          = limpiar($_POST['carrera'] ?? '');
            $d['semestre']         = (string) (int) ($_POST['semestre'] ?? 0);
            $d['datos_academicos'] = trim((string) ($_POST['datos_academicos'] ?? ''));
            if (!preg_match('/^\d{8}$/', $d['matricula'])) {
                $errores['matricula'] = 'La matrícula debe tener 8 dígitos.';
            } elseif ($existe('alumno', 'matricula', $d['matricula'])) {
                $errores['matricula'] = 'Ya hay un alumno con esa matrícula.';
            }
            if (!in_array($d['carrera'], CARRERAS, true)) {
                $errores['carrera'] = 'Elige la carrera.';
            }
            if ($d['semestre'] < 1 || $d['semestre'] > 12) {
                $errores['semestre'] = 'Elige un semestre del 1 al 12.';
            }
            break;
        case 'empresa':
            $d['rfc']          = mb_strtoupper(str_replace([' ', '-'], '', limpiar($_POST['rfc'] ?? '')));
            $d['razon_social'] = limpiar($_POST['razon_social'] ?? '');
            $d['sector']       = limpiar($_POST['sector'] ?? '');
            if ($e = validar_rfc($d['rfc'])) {
                $errores['rfc'] = $e;
            } elseif ($existe('empresa', 'rfc_empresa', $d['rfc'])) {
                $errores['rfc'] = 'Ya hay una empresa con ese RFC.';
            }
            if ($e = validar_requerido($d['razon_social'], 'La razón social')) {
                $errores['razon_social'] = $e;
            }
            if (!in_array($d['sector'], SECTORES_EMPRESA, true)) {
                $errores['sector'] = 'Elige un sector.';
            }
            break;
        case 'profesor':
        case 'administrador':
            $d['num_empleado'] = mb_strtoupper(limpiar($_POST['num_empleado'] ?? ''));
            $tabla = $d['rol'];
            if ($d['num_empleado'] === '' || mb_strlen($d['num_empleado']) > 10) {
                $errores['num_empleado'] = 'Escribe el número de empleado (máximo 10 caracteres).';
            } elseif ($existe($tabla, 'num_empleado', $d['num_empleado'])) {
                $errores['num_empleado'] = 'Ese número de empleado ya está registrado.';
            }
            if ($d['rol'] === 'profesor') {
                $d['especialidad'] = limpiar($_POST['especialidad'] ?? '');
                $d['experiencia']  = trim((string) ($_POST['experiencia'] ?? ''));
                if ($e = validar_requerido($d['especialidad'], 'La especialidad')) {
                    $errores['especialidad'] = $e;
                }
            } else {
                $d['cargo'] = limpiar($_POST['cargo'] ?? '');
                if ($e = validar_requerido($d['cargo'], 'El cargo')) {
                    $errores['cargo'] = $e;
                }
            }
            break;
    }

    if (!$errores) {
        try {
            $pdo->beginTransaction();
            $c_usuario = $id ?? siguiente_folio('USR');
            $tel = $d['telefono'] ?: null;

            if ($editando) {
                $pdo->prepare('UPDATE usuario SET nombre = ?, correo = ?, telefono = ? WHERE c_usuario = ?')
                    ->execute([$d['nombre'], $d['correo'], $tel, $c_usuario]);
                if ($pw !== '') {
                    $pdo->prepare('UPDATE usuario SET contrasena_hash = ? WHERE c_usuario = ?')
                        ->execute([password_hash($pw, PASSWORD_BCRYPT), $c_usuario]);
                }
            } else {
                $pdo->prepare('INSERT INTO usuario (c_usuario, nombre, correo, telefono, contrasena_hash, rol) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$c_usuario, $d['nombre'], $d['correo'], $tel, password_hash($pw, PASSWORD_BCRYPT), $d['rol']]);
            }

            // Subtipo: en edición se actualiza (la clave cambia en cascada en las tablas hijas).
            [$sql, $valores] = match ($d['rol']) {
                'alumno' => $editando
                    ? ['UPDATE alumno SET matricula = ?, carrera = ?, semestre = ?, datos_academicos = ? WHERE c_usuario = ?',
                       [$d['matricula'], $d['carrera'], (int) $d['semestre'], $d['datos_academicos'] ?: null, $c_usuario]]
                    : ['INSERT INTO alumno (matricula, carrera, semestre, datos_academicos, c_usuario) VALUES (?, ?, ?, ?, ?)',
                       [$d['matricula'], $d['carrera'], (int) $d['semestre'], $d['datos_academicos'] ?: null, $c_usuario]],
                'empresa' => $editando
                    ? ['UPDATE empresa SET rfc_empresa = ?, razon_social = ?, sector = ? WHERE c_usuario = ?',
                       [$d['rfc'], $d['razon_social'], $d['sector'], $c_usuario]]
                    : ['INSERT INTO empresa (rfc_empresa, razon_social, sector, c_usuario) VALUES (?, ?, ?, ?)',
                       [$d['rfc'], $d['razon_social'], $d['sector'], $c_usuario]],
                'profesor' => $editando
                    ? ['UPDATE profesor SET num_empleado = ?, especialidad = ?, experiencia = ? WHERE c_usuario = ?',
                       [$d['num_empleado'], $d['especialidad'], $d['experiencia'] ?: null, $c_usuario]]
                    : ['INSERT INTO profesor (num_empleado, especialidad, experiencia, c_usuario) VALUES (?, ?, ?, ?)',
                       [$d['num_empleado'], $d['especialidad'], $d['experiencia'] ?: null, $c_usuario]],
                'administrador' => $editando
                    ? ['UPDATE administrador SET num_empleado = ?, cargo = ? WHERE c_usuario = ?',
                       [$d['num_empleado'], $d['cargo'], $c_usuario]]
                    : ['INSERT INTO administrador (num_empleado, cargo, c_usuario) VALUES (?, ?, ?)',
                       [$d['num_empleado'], $d['cargo'], $c_usuario]],
            };
            $pdo->prepare($sql)->execute($valores);
            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log((string) $ex);
            $errores['_general'] = mensaje_error_bd($ex);
        }

        if (!$errores) {
            registrar_auditoria($editando ? 'editar' : 'crear', 'usuario', $c_usuario);
            if ($c_usuario === $admin['c_usuario']) {
                $_SESSION['usuario']['nombre'] = $d['nombre'];
                $_SESSION['usuario']['correo'] = $d['correo'];
                $_SESSION['usuario']['clave'] = $d['num_empleado'];
                $_SESSION['usuario']['subtitulo'] = $d['cargo'];
            }
            aviso('exito', $editando ? 'Usuario actualizado.' : 'Usuario creado. Ya puede iniciar sesión con su correo y la contraseña inicial.');
            redirigir('mod_admin/usuarios.php');
        }
    }
}

$roles_tarjeta = [
    'empresa'       => ['bi-building', 'RFC y razón social'],
    'alumno'        => ['bi-mortarboard', 'Matrícula y carrera'],
    'profesor'      => ['bi-person-workspace', 'Núm. de empleado'],
    'administrador' => ['bi-shield-check', 'Núm. de empleado y cargo'],
];

$titulo = $editando ? 'Editar usuario' : 'Nuevo usuario';
$menu_activo = 'mod_admin/usuarios.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Gestión', null], ['Usuarios', 'mod_admin/usuarios.php'], [$titulo, null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$err = fn (string $k) => $errores[$k] ?? null;
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Administración</span><h1 class="titulo-izquierda"><?= e($titulo) ?></h1></div>
</div>
<?= alerta_errores($errores, count($errores) === 1 && !isset($errores['_general']) ? reset($errores) : 'Revisa los campos marcados.') ?>

<form method="post" style="max-width:900px" novalidate>
    <?= csrf_campo() ?>
    <section class="seccion-form" id="rol" aria-labelledby="rol-t">
        <h2 id="rol-t"><span class="num">1</span>Rol del usuario</h2>
        <fieldset>
            <legend class="visually-hidden">Rol</legend>
            <div class="row g-3">
                <?php foreach ($roles_tarjeta as $r => [$icono, $detalle]):
                    if ($editando && $r !== $d['rol']) continue; ?>
                    <div class="col-6 col-md-3">
                        <input class="form-check-input visually-hidden selector-rol" type="radio" name="rol" id="rol-<?= $r ?>" value="<?= $r ?>"
                            <?= $d['rol'] === $r ? 'checked' : '' ?><?= $editando ? ' disabled' : '' ?>>
                        <label for="rol-<?= $r ?>" class="card h-100 p-3 text-center tarjeta-rol">
                            <span class="d-block fs-4 text-navy"><i class="bi <?= $icono ?>" aria-hidden="true"></i></span>
                            <strong class="d-block"><?= e(nombre_rol($r)) ?></strong>
                            <span class="small text-mute"><?= e($detalle) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <p class="form-text mt-2 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> El rol no se puede cambiar después de crear el usuario.</p>
    </section>

    <section class="seccion-form" id="com" aria-labelledby="com-t">
        <h2 id="com-t"><span class="num">2</span>Datos comunes</h2>
        <div class="row g-3">
            <?= campo('nombre', 'Nombre completo', $d['nombre'], ['requerido' => true, 'maxlength' => 120, 'error' => $err('nombre'), 'clase_contenedor' => 'col-md-6']) ?>
            <?= campo('correo', 'Correo electrónico', $d['correo'], ['tipo' => 'email', 'requerido' => true, 'error' => $err('correo'), 'clase_contenedor' => 'col-md-6']) ?>
            <?= campo('telefono', 'Teléfono', $d['telefono'], ['tipo' => 'tel', 'opcional' => true, 'maxlength' => 15, 'ayuda' => '10 dígitos.', 'error' => $err('telefono'), 'clase_contenedor' => 'col-md-6']) ?>
            <?= campo('contrasena', $editando ? 'Nueva contraseña' : 'Contraseña inicial', '', [
                'tipo' => 'text', 'requerido' => !$editando, 'opcional' => $editando, 'autocomplete' => 'off', 'error' => $err('contrasena'), 'clase_contenedor' => 'col-md-6',
                'ayuda' => $editando ? 'Déjala vacía para conservar la actual.' : 'Al menos 8 caracteres, con mayúscula, minúscula y número. El usuario podrá cambiarla en «Mi cuenta».']) ?>
        </div>
    </section>

    <section class="seccion-form seccion-rol" data-rol="alumno" aria-labelledby="alu-t">
        <h2 id="alu-t"><span class="num">3</span>Datos del alumno</h2>
        <div class="row g-3">
            <?= campo('matricula', 'Matrícula', $d['matricula'], ['requerido' => true, 'maxlength' => 8, 'ayuda' => '8 dígitos.', 'error' => $err('matricula'), 'clase_contenedor' => 'col-md-4']) ?>
            <?= campo_select('carrera', 'Carrera', $d['carrera'], CARRERAS, ['requerido' => true, 'error' => $err('carrera'), 'clase_contenedor' => 'col-md-5']) ?>
            <?= campo_select('semestre', 'Semestre', $d['semestre'], array_combine(range(1, 12), range(1, 12)), ['requerido' => true, 'error' => $err('semestre'), 'clase_contenedor' => 'col-md-3']) ?>
            <?= campo_area('datos_academicos', 'Datos académicos', $d['datos_academicos'], ['opcional' => true, 'filas' => 2, 'clase_contenedor' => 'col-12']) ?>
        </div>
    </section>

    <section class="seccion-form seccion-rol" data-rol="empresa" aria-labelledby="emp-t">
        <h2 id="emp-t"><span class="num">3</span>Datos de la empresa</h2>
        <div class="row g-3">
            <?= campo('rfc', 'RFC', $d['rfc'], ['requerido' => true, 'maxlength' => 13, 'ayuda' => '12 caracteres (moral) o 13 (física).', 'error' => $err('rfc'), 'clase_contenedor' => 'col-md-4']) ?>
            <?= campo('razon_social', 'Razón social', $d['razon_social'], ['requerido' => true, 'maxlength' => 200, 'error' => $err('razon_social'), 'clase_contenedor' => 'col-md-8']) ?>
            <?= campo_select('sector', 'Sector', $d['sector'], SECTORES_EMPRESA, ['requerido' => true, 'error' => $err('sector'), 'clase_contenedor' => 'col-md-6']) ?>
        </div>
    </section>

    <section class="seccion-form seccion-rol" data-rol="profesor administrador" aria-labelledby="emp2-t">
        <h2 id="emp2-t"><span class="num">3</span>Datos del <span class="texto-rol"><?= $d['rol'] === 'administrador' ? 'administrador' : 'profesor' ?></span></h2>
        <div class="row g-3">
            <?= campo('num_empleado', 'Número de empleado', $d['num_empleado'], ['requerido' => true, 'maxlength' => 10, 'error' => $err('num_empleado'), 'clase_contenedor' => 'col-md-4']) ?>
            <div class="col-md-8 solo-rol" data-rol="profesor">
                <?= campo('especialidad', 'Especialidad', $d['especialidad'], ['requerido' => true, 'maxlength' => 150, 'error' => $err('especialidad')]) ?>
            </div>
            <div class="col-md-8 solo-rol" data-rol="administrador">
                <?= campo('cargo', 'Cargo', $d['cargo'], ['requerido' => true, 'maxlength' => 80, 'error' => $err('cargo')]) ?>
            </div>
            <div class="col-12 solo-rol" data-rol="profesor">
                <?= campo_area('experiencia', 'Experiencia', $d['experiencia'], ['opcional' => true, 'filas' => 3]) ?>
            </div>
        </div>
    </section>

    <div class="barra-acciones-form">
        <a class="btn btn-terciario" href="<?= e(url('mod_admin/usuarios.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-principal"><i class="bi <?= $editando ? 'bi-check-lg' : 'bi-person-plus' ?>" aria-hidden="true"></i><?= $editando ? 'Guardar cambios' : 'Crear usuario' ?></button>
    </div>
</form>

<script>
// Muestra solo la sección del rol elegido.
(() => {
  const fijo = <?= json_encode($editando ? $d['rol'] : null) ?>;
  const actualizar = () => {
    const rol = fijo || document.querySelector('.selector-rol:checked')?.value || '';
    document.querySelectorAll('.seccion-rol, .solo-rol').forEach((el) => {
      el.hidden = !el.dataset.rol.split(' ').includes(rol);
    });
    document.querySelectorAll('.texto-rol').forEach((el) => { el.textContent = rol === 'administrador' ? 'administrador' : 'profesor'; });
  };
  document.querySelectorAll('.selector-rol').forEach((r) => r.addEventListener('change', actualizar));
  actualizar();
})();
</script>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
