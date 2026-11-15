<?php
/** Pantalla D1 · Usuarios (lista, filtros, activar y desactivar). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('administrador');
$pdo = conexion();

$filtros = [
    'q'      => limpiar($_GET['q'] ?? ''),
    'rol'    => in_array($_GET['rol'] ?? '', ROLES, true) ? $_GET['rol'] : '',
    'estado' => in_array($_GET['estado'] ?? '', ['activo', 'inactivo'], true) ? $_GET['estado'] : '',
];

// Activar / desactivar
if (es_post()) {
    verificar_csrf();
    $id = (string) ($_POST['c_usuario'] ?? '');
    $nuevo = ($_POST['accion'] ?? '') === 'activar' ? 'activo' : 'inactivo';
    if ($id === $usuario['c_usuario']) {
        aviso('peligro', 'No puedes desactivar tu propia cuenta.');
    } else {
        $s = $pdo->prepare('UPDATE usuario SET estado = ? WHERE c_usuario = ?');
        $s->execute([$nuevo, $id]);
        if ($s->rowCount()) {
            registrar_auditoria($nuevo === 'activo' ? 'activar' : 'desactivar', 'usuario', $id);
            aviso('exito', $nuevo === 'activo'
                ? 'Usuario activado. Ya puede iniciar sesión.'
                : 'Usuario desactivado. Ya no podrá iniciar sesión; su información se conserva.');
        }
    }
    redirigir(url_lista('mod_admin/usuarios.php', $filtros + ['pagina' => (int) ($_GET['pagina'] ?? 1)]));
}

// Identificador de cada rol: matrícula, RFC o número de empleado.
$desde_sql = "FROM usuario u
    LEFT JOIN alumno a        ON a.c_usuario = u.c_usuario
    LEFT JOIN empresa em      ON em.c_usuario = u.c_usuario
    LEFT JOIN profesor p      ON p.c_usuario = u.c_usuario
    LEFT JOIN administrador d ON d.c_usuario = u.c_usuario";
$donde = [];
$params = [];
if ($filtros['q'] !== '') {
    $donde[] = '(u.nombre LIKE ? OR u.correo LIKE ? OR a.matricula LIKE ? OR em.rfc_empresa LIKE ? OR em.razon_social LIKE ? OR p.num_empleado LIKE ? OR d.num_empleado LIKE ?)';
    $like = '%' . addcslashes($filtros['q'], '%_\\') . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}
if ($filtros['rol'] !== '') {
    $donde[] = 'u.rol = ?';
    $params[] = $filtros['rol'];
}
if ($filtros['estado'] !== '') {
    $donde[] = 'u.estado = ?';
    $params[] = $filtros['estado'];
}
$where = $donde ? ' WHERE ' . implode(' AND ', $donde) : '';

$s = $pdo->prepare("SELECT COUNT(*) $desde_sql $where");
$s->execute($params);
$pag = paginar((int) $s->fetchColumn(), 20);

$s = $pdo->prepare("SELECT u.c_usuario, u.nombre, u.correo, u.rol, u.estado, em.razon_social,
        COALESCE(a.matricula, em.rfc_empresa, p.num_empleado, d.num_empleado) AS identificador
    $desde_sql $where
    ORDER BY u.estado, u.nombre
    LIMIT {$pag['por_pagina']} OFFSET {$pag['desde']}");
$s->execute($params);
$lista = $s->fetchAll();

$titulo = 'Usuarios';
$menu_activo = 'mod_admin/usuarios.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Gestión', null], ['Usuarios', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Administración</span><h1 class="titulo-izquierda">Usuarios</h1></div>
    <div class="acciones"><a class="btn btn-principal" href="<?= e(url('mod_admin/usuario_form.php')) ?>"><i class="bi bi-person-plus" aria-hidden="true"></i>Nuevo usuario</a></div>
</div>

<div class="tabla-marco tabla-responsiva-tarjetas">
    <form class="filtros" role="search" method="get">
        <div class="flex-grow-1">
            <label class="form-label small mb-1" for="q">Buscar</label>
            <input class="form-control" id="q" name="q" type="search" placeholder="Nombre, correo o identificador" value="<?= e($filtros['q']) ?>">
        </div>
        <div>
            <label class="form-label small mb-1" for="rol">Rol</label>
            <select class="form-select" id="rol" name="rol">
                <option value="">Todos</option>
                <?php foreach (ROLES as $r): ?><option value="<?= $r ?>"<?= $filtros['rol'] === $r ? ' selected' : '' ?>><?= e(nombre_rol($r)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-1" for="estado">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <option value="activo"<?= $filtros['estado'] === 'activo' ? ' selected' : '' ?>>Activo</option>
                <option value="inactivo"<?= $filtros['estado'] === 'inactivo' ? ' selected' : '' ?>>Inactivo</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secundario"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button>
        <?php if (array_filter($filtros)): ?>
            <a class="btn btn-terciario" href="<?= e(url('mod_admin/usuarios.php')) ?>">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php
    $accion_form = e(url_lista('mod_admin/usuarios.php', $filtros + ['pagina' => $pag['pagina']]));
    $botones = function (array $u) use ($usuario, $accion_form): string {
        $html = '<a class="btn btn-secundario btn-sm" href="' . e(url('mod_admin/usuario_form.php?id=' . urlencode($u['c_usuario']))) . '">Editar</a> ';
        if ($u['c_usuario'] === $usuario['c_usuario']) {
            return $html;
        }
        $activo = $u['estado'] === 'activo';
        $html .= '<form method="post" action="' . $accion_form . '" class="d-inline">' . csrf_campo()
               . '<input type="hidden" name="c_usuario" value="' . e($u['c_usuario']) . '">'
               . '<input type="hidden" name="accion" value="' . ($activo ? 'desactivar' : 'activar') . '">'
               . ($activo
                   ? '<button type="submit" class="btn btn-rechazar-contorno btn-sm" data-confirmar="¿Desactivar a ' . e($u['nombre']) . '? Ya no podrá iniciar sesión.">Desactivar</button>'
                   : '<button type="submit" class="btn btn-secundario btn-sm">Activar</button>')
               . '</form>';
        return $html;
    };
    ?>

    <?php if (!$lista): ?>
        <div class="vacio p-4">
            <div class="vacio-icono"><i class="bi bi-people" aria-hidden="true"></i></div>
            <h3>No hay usuarios con esos filtros</h3>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table tabla-datos table-striped mb-0">
                <thead><tr>
                    <th scope="col">Nombre</th><th scope="col">Correo</th><th scope="col">Rol</th>
                    <th scope="col">Identificador</th><th scope="col">Estado</th><th scope="col" class="acciones">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($lista as $u): ?>
                    <tr>
                        <td><strong><?= e($u['rol'] === 'empresa' && $u['razon_social'] ? $u['razon_social'] : $u['nombre']) ?></strong>
                            <?php if ($u['rol'] === 'empresa'): ?><span class="d-block small text-mute"><?= e($u['nombre']) ?></span><?php endif; ?></td>
                        <td><span class="small"><?= e($u['correo']) ?></span></td>
                        <td><span class="etiqueta-dato"><?= e(nombre_rol($u['rol'])) ?></span></td>
                        <td><span class="folio"><?= e($u['identificador'] ?? '—') ?></span></td>
                        <td><?= insignia('usuario', $u['estado']) ?></td>
                        <td class="acciones text-nowrap"><?= $botones($u) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="lista-tarjetas">
            <?php foreach ($lista as $u): ?>
                <div class="fila-tarjeta">
                    <span class="titulo"><?= e($u['rol'] === 'empresa' && $u['razon_social'] ? $u['razon_social'] : $u['nombre']) ?></span>
                    <span class="meta"><?= e(nombre_rol($u['rol'])) ?> · <?= e($u['identificador'] ?? '—') ?> · <?= e($u['correo']) ?></span>
                    <span><?= insignia('usuario', $u['estado']) ?></span>
                    <div><?= $botones($u) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="pie-tabla"><?= pie_paginacion($pag, count($lista), 'mod_admin/usuarios.php', $filtros) ?></div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
