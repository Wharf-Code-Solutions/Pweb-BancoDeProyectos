<?php
/**
 * Pantalla D1b · Detalle de usuario (solo lectura). Datos de la cuenta y, según
 * el rol, el perfil del alumno (habilidades y candidaturas), los proyectos de la
 * empresa o las solicitudes de dirección del profesor.
 */
require_once __DIR__ . '/../inc/inicio.php';
$admin = requiere_rol('administrador');
$pdo = conexion();

$id = (string) ($_GET['id'] ?? '');
$s = $pdo->prepare('SELECT u.c_usuario, u.nombre, u.correo, u.telefono, u.rol, u.estado,
        a.matricula, a.carrera, a.semestre, a.datos_academicos,
        em.rfc_empresa, em.razon_social, em.sector,
        p.num_empleado AS num_profesor, p.especialidad, p.experiencia,
        ad.num_empleado AS num_admin, ad.cargo
    FROM usuario u
    LEFT JOIN alumno a ON a.c_usuario = u.c_usuario
    LEFT JOIN empresa em ON em.c_usuario = u.c_usuario
    LEFT JOIN profesor p ON p.c_usuario = u.c_usuario
    LEFT JOIN administrador ad ON ad.c_usuario = u.c_usuario
    WHERE u.c_usuario = ?');
$s->execute([$id]);
$u = $s->fetch();
if (!$u) {
    error_http(404);
}

$es_empresa = $u['rol'] === 'empresa';
$nombre_visible = $es_empresa && $u['razon_social'] ? $u['razon_social'] : $u['nombre'];
$identificador = $u['matricula'] ?? $u['rfc_empresa'] ?? $u['num_profesor'] ?? $u['num_admin'] ?? '—';
$rotulo_id = match ($u['rol']) {
    'alumno' => 'Matrícula', 'empresa' => 'RFC', default => 'Número de empleado',
};

// Información propia de cada rol
$habilidades = $candidaturas = $proyectos = $direcciones = [];
if ($u['rol'] === 'alumno' && $u['matricula']) {
    $s = $pdo->prepare('SELECT ph.nombre_habilidad, ph.nivel, ph.descripcion, ph.evidencia, h.tipo
        FROM perfil_habilidad ph JOIN habilidad h ON h.nombre_habilidad = ph.nombre_habilidad
        WHERE ph.matricula = ? ORDER BY h.tipo, ph.nombre_habilidad');
    $s->execute([$u['matricula']]);
    $habilidades = $s->fetchAll();

    $s = $pdo->prepare('SELECT sc.c_candidatura, sc.fecha, sc.estado, p.c_proyecto, p.titulo, em.razon_social,
            aa.estado AS estado_asignacion
        FROM solicitud_candidatura sc
        JOIN proyecto p ON p.c_proyecto = sc.c_proyecto
        JOIN empresa em ON em.rfc_empresa = p.rfc_empresa
        LEFT JOIN asignacion_alumno aa ON aa.c_candidatura = sc.c_candidatura
        WHERE sc.matricula = ? ORDER BY sc.fecha DESC');
    $s->execute([$u['matricula']]);
    $candidaturas = $s->fetchAll();
} elseif ($es_empresa && $u['rfc_empresa']) {
    $s = $pdo->prepare('SELECT c_proyecto, titulo, estado, fecha_registro FROM proyecto
        WHERE rfc_empresa = ? ORDER BY fecha_registro DESC');
    $s->execute([$u['rfc_empresa']]);
    $proyectos = $s->fetchAll();
} elseif ($u['rol'] === 'profesor' && $u['num_profesor']) {
    $s = $pdo->prepare('SELECT sd.c_solicitud_direccion, sd.fecha, sd.estado, p.c_proyecto, p.titulo,
            ap.estado AS estado_asignacion
        FROM solicitud_direccion sd
        JOIN proyecto p ON p.c_proyecto = sd.c_proyecto
        LEFT JOIN asignacion_profesor ap ON ap.c_solicitud_direccion = sd.c_solicitud_direccion
        WHERE sd.num_empleado = ? ORDER BY sd.fecha DESC');
    $s->execute([$u['num_profesor']]);
    $direcciones = $s->fetchAll();
}

$titulo = $nombre_visible;
$menu_activo = 'mod_admin/usuarios.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Gestión', null], ['Usuarios', 'mod_admin/usuarios.php'], [$nombre_visible, null]];
require __DIR__ . '/../menumain/app_encabezado.php';

$fila = fn (string $k, ?string $v) => '<div class="kv-fila"><span class="k">' . e($k) . '</span><span class="v">' . e($v !== null && $v !== '' ? $v : '—') . '</span></div>';
$texto_largo = fn (string $rotulo, ?string $v, string $vacio) => '<div class="mt-3"><p class="small text-mute mb-1">' . e($rotulo) . '</p>'
    . ($v ? '<p class="mb-0">' . nl2br(e($v)) . '</p>' : '<p class="small text-mute mb-0">' . e($vacio) . '</p>') . '</div>';
$propio = $u['c_usuario'] === $admin['c_usuario'];
$activo = $u['estado'] === 'activo';
?>
<div class="encabezado-pagina">
    <div class="d-flex gap-3 align-items-center">
        <span class="avatar grande" aria-hidden="true"><?= e(iniciales($nombre_visible)) ?></span>
        <div>
            <h1 class="titulo-izquierda"><?= e($nombre_visible) ?></h1>
            <div class="mt-1 d-flex flex-wrap gap-2 align-items-center">
                <?= insignia('usuario', $u['estado']) ?>
                <span class="small text-mute"><?= e(nombre_rol($u['rol'])) ?></span>
                <span class="folio"><?= e($identificador) ?></span>
            </div>
        </div>
    </div>
    <div class="acciones">
        <a class="btn btn-secundario" href="<?= e(url('mod_admin/usuario_form.php?id=' . urlencode($u['c_usuario']))) ?>"><i class="bi bi-pencil" aria-hidden="true"></i>Editar</a>
        <?php if (!$propio): ?>
            <form method="post" action="<?= e(url('mod_admin/usuarios.php')) ?>" class="d-inline">
                <?= csrf_campo() ?>
                <input type="hidden" name="c_usuario" value="<?= e($u['c_usuario']) ?>">
                <input type="hidden" name="accion" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                <input type="hidden" name="volver" value="detalle">
                <?php if ($activo): ?>
                    <button type="submit" class="btn btn-terciario text-danger" data-confirmar="¿Desactivar a <?= e($u['nombre']) ?>? Ya no podrá iniciar sesión.">Desactivar</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-terciario">Activar</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8 d-grid gap-3 align-self-start">
        <?php if ($u['rol'] === 'alumno'): ?>
            <div class="card"><div class="card-header"><h2 class="h3 mb-0">Perfil académico</h2></div>
                <div class="card-body">
                    <?= $fila('Carrera', $u['carrera']) ?>
                    <?= $fila('Semestre', $u['semestre'] ? $u['semestre'] . '.° semestre' : null) ?>
                    <?= $texto_largo('Datos académicos', $u['datos_academicos'], 'El alumno no registró datos académicos adicionales.') ?>
                </div></div>

            <div class="card"><div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h2 class="h3 mb-0">Habilidades</h2><span class="small text-mute"><?= count($habilidades) ?> registradas</span></div>
                <?php if (!$habilidades): ?>
                    <div class="card-body"><p class="text-mute mb-0">El alumno todavía no registra habilidades en su perfil.</p></div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table tabla-datos mb-0">
                        <thead><tr><th scope="col">Habilidad</th><th scope="col">Tipo</th><th scope="col">Nivel</th><th scope="col">Descripción</th><th scope="col">Evidencia</th></tr></thead>
                        <tbody>
                        <?php foreach ($habilidades as $h): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($h['nombre_habilidad']) ?></td>
                                <td><?= $h['tipo'] === 'dura' ? 'Técnica' : 'Blanda' ?></td>
                                <td><?= e(NIVELES[$h['nivel']] ?? $h['nivel']) ?></td>
                                <td class="small"><?= $h['descripcion'] ? e($h['descripcion']) : '<span class="text-mute">—</span>' ?></td>
                                <td class="small"><?= $h['evidencia']
                                    ? '<a href="' . e($h['evidencia']) . '" target="_blank" rel="noopener noreferrer">Abrir<span class="visually-hidden"> evidencia de ' . e($h['nombre_habilidad']) . '</span> <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>'
                                    : '<span class="text-mute">—</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>

            <div class="card"><div class="card-header"><h2 class="h3 mb-0">Candidaturas</h2></div>
                <?php if (!$candidaturas): ?>
                    <div class="card-body"><p class="text-mute mb-0">El alumno no se ha postulado a ningún proyecto.</p></div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table tabla-datos mb-0">
                        <thead><tr><th scope="col">Proyecto</th><th scope="col">Fecha</th><th scope="col">Estado</th><th scope="col">Asignación</th></tr></thead>
                        <tbody>
                        <?php foreach ($candidaturas as $c): ?>
                            <tr>
                                <td class="col-proyecto"><a href="<?= e(url('mod_admin/candidatura.php?id=' . urlencode($c['c_candidatura']))) ?>"><?= e($c['titulo']) ?></a>
                                    <span class="d-block small text-mute"><?= e($c['razon_social']) ?></span></td>
                                <td class="text-nowrap"><?= e(fecha_corta($c['fecha'])) ?></td>
                                <td><?= insignia('candidatura', $c['estado']) ?></td>
                                <td><?= $c['estado_asignacion'] ? insignia('asignacion', $c['estado_asignacion']) : '<span class="text-mute">—</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>

        <?php elseif ($es_empresa): ?>
            <div class="card"><div class="card-header"><h2 class="h3 mb-0">Datos de la empresa</h2></div>
                <div class="card-body">
                    <?= $fila('Razón social', $u['razon_social']) ?>
                    <?= $fila('RFC', $u['rfc_empresa']) ?>
                    <?= $fila('Sector', $u['sector']) ?>
                    <?= $fila('Persona de contacto', $u['nombre']) ?>
                </div></div>

            <div class="card"><div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h2 class="h3 mb-0">Proyectos</h2><span class="small text-mute"><?= count($proyectos) ?> registrados</span></div>
                <?php if (!$proyectos): ?>
                    <div class="card-body"><p class="text-mute mb-0">La empresa todavía no registra proyectos.</p></div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table tabla-datos mb-0">
                        <thead><tr><th scope="col">Proyecto</th><th scope="col">Registro</th><th scope="col">Estado</th></tr></thead>
                        <tbody>
                        <?php foreach ($proyectos as $p): ?>
                            <tr>
                                <td><a href="<?= e(url('mod_admin/proyecto.php?id=' . urlencode($p['c_proyecto']))) ?>"><?= e($p['titulo']) ?></a>
                                    <span class="d-block small text-mute"><?= e($p['c_proyecto']) ?></span></td>
                                <td class="text-nowrap"><?= e(fecha_corta($p['fecha_registro'])) ?></td>
                                <td><?= insignia('proyecto', $p['estado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>

        <?php elseif ($u['rol'] === 'profesor'): ?>
            <div class="card"><div class="card-header"><h2 class="h3 mb-0">Perfil del profesor</h2></div>
                <div class="card-body">
                    <?= $fila('Número de empleado', $u['num_profesor']) ?>
                    <?= $fila('Especialidad', $u['especialidad']) ?>
                    <?= $texto_largo('Experiencia', $u['experiencia'], 'El profesor no registró su experiencia.') ?>
                </div></div>

            <div class="card"><div class="card-header"><h2 class="h3 mb-0">Solicitudes de dirección</h2></div>
                <?php if (!$direcciones): ?>
                    <div class="card-body"><p class="text-mute mb-0">El profesor no ha solicitado dirigir ningún proyecto.</p></div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table tabla-datos mb-0">
                        <thead><tr><th scope="col">Proyecto</th><th scope="col">Fecha</th><th scope="col">Estado</th><th scope="col">Dirección</th></tr></thead>
                        <tbody>
                        <?php foreach ($direcciones as $d): ?>
                            <tr>
                                <td><a href="<?= e(url('mod_admin/proyecto.php?id=' . urlencode($d['c_proyecto']))) ?>"><?= e($d['titulo']) ?></a></td>
                                <td class="text-nowrap"><?= e(fecha_corta($d['fecha'])) ?></td>
                                <td><?= insignia('direccion', $d['estado']) ?></td>
                                <td><?= $d['estado_asignacion'] ? insignia('asignacion', $d['estado_asignacion']) : '<span class="text-mute">—</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="card"><div class="card-header"><h2 class="h3 mb-0">Datos del administrador</h2></div>
                <div class="card-body">
                    <?= $fila('Número de empleado', $u['num_admin']) ?>
                    <?= $fila('Cargo', $u['cargo']) ?>
                </div></div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4 d-grid gap-3 align-self-start">
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Cuenta</h2></div>
            <div class="card-body">
                <?= $fila('Correo', $u['correo']) ?>
                <?= $fila('Teléfono', $u['telefono']) ?>
                <?= $fila('Rol', nombre_rol($u['rol'])) ?>
                <?= $fila($rotulo_id, $identificador) ?>
                <?= $fila('Código de usuario', $u['c_usuario']) ?>
                <?= $fila('Estado', nombre_estado('usuario', $u['estado'])) ?>
            </div></div>
        <a class="btn btn-terciario" href="<?= e(url('mod_admin/historial.php?usuario=' . urlencode($u['c_usuario']))) ?>">
            <i class="bi bi-clock-history" aria-hidden="true"></i>Ver sus acciones en el historial</a>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
