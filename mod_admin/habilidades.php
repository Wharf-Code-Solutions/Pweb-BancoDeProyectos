<?php
/** Pantalla D3 · Catálogo de habilidades. */
require_once __DIR__ . '/../inc/inicio.php';
requiere_rol('administrador');
$pdo = conexion();

$form = ['original' => '', 'nombre' => '', 'tipo' => 'dura', 'descripcion' => ''];
$errores = [];

if (es_post()) {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    $form['original'] = (string) ($_POST['original'] ?? '');

    if ($accion === 'eliminar') {
        try {
            $s = $pdo->prepare('DELETE FROM habilidad WHERE nombre_habilidad = ?');
            $s->execute([$form['original']]);
            if ($s->rowCount()) {
                registrar_auditoria('eliminar', 'habilidad', $form['original']);
                aviso('exito', 'Habilidad eliminada.');
            }
        } catch (PDOException $ex) {
            aviso('peligro', 'No se puede borrar una habilidad en uso; solo editarla.');
        }
        redirigir('mod_admin/habilidades.php');
    }

    $form['nombre']      = limpiar($_POST['nombre'] ?? '');
    $form['tipo']        = in_array($_POST['tipo'] ?? '', ['dura', 'blanda'], true) ? $_POST['tipo'] : '';
    $form['descripcion'] = limpiar($_POST['descripcion'] ?? '');

    if ($form['nombre'] === '' || mb_strlen($form['nombre']) > 80) {
        $errores['nombre'] = 'Escribe el nombre (máximo 80 caracteres).';
    } else {
        $s = $pdo->prepare('SELECT COUNT(*) FROM habilidad WHERE nombre_habilidad = ? AND nombre_habilidad <> ?');
        $s->execute([$form['nombre'], $form['original']]);
        if ($s->fetchColumn() > 0) {
            $errores['nombre'] = 'Ya existe una habilidad con ese nombre.';
        }
    }
    if ($form['tipo'] === '') {
        $errores['tipo'] = 'Elige el tipo.';
    }
    if (mb_strlen($form['descripcion']) > 255) {
        $errores['descripcion'] = 'Máximo 255 caracteres.';
    }

    if (!$errores) {
        try {
            if ($form['original'] === '') {
                $pdo->prepare('INSERT INTO habilidad (nombre_habilidad, tipo, descripcion) VALUES (?, ?, ?)')
                    ->execute([$form['nombre'], $form['tipo'], $form['descripcion'] ?: null]);
                registrar_auditoria('crear', 'habilidad', $form['nombre']);
                aviso('exito', 'Habilidad agregada.');
            } else {
                // Renombrar actualiza en cascada perfiles de alumnos y requisitos de proyectos.
                $pdo->prepare('UPDATE habilidad SET nombre_habilidad = ?, tipo = ?, descripcion = ? WHERE nombre_habilidad = ?')
                    ->execute([$form['nombre'], $form['tipo'], $form['descripcion'] ?: null, $form['original']]);
                registrar_auditoria('editar', 'habilidad', $form['nombre']);
                aviso('exito', 'Habilidad actualizada.');
            }
            redirigir('mod_admin/habilidades.php');
        } catch (PDOException $ex) {
            error_log((string) $ex);
            $errores['_general'] = mensaje_error_bd($ex);
        }
    }
}

$habilidades = $pdo->query('SELECT h.nombre_habilidad, h.tipo, h.descripcion,
        (SELECT COUNT(*) FROM perfil_habilidad ph WHERE ph.nombre_habilidad = h.nombre_habilidad) AS alumnos,
        (SELECT COUNT(*) FROM requisito_proyecto rp WHERE rp.nombre_habilidad = h.nombre_habilidad) AS proyectos
    FROM habilidad h ORDER BY h.nombre_habilidad')->fetchAll();
$grupos = [
    'dura'   => ['Técnicas (duras)', array_values(array_filter($habilidades, fn ($h) => $h['tipo'] === 'dura'))],
    'blanda' => ['Socioemocionales (blandas)', array_values(array_filter($habilidades, fn ($h) => $h['tipo'] === 'blanda'))],
];

$titulo = 'Catálogo de habilidades';
$menu_activo = 'mod_admin/habilidades.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Catálogos', null], ['Habilidades', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Catálogos</span><h1 class="titulo-izquierda">Catálogo de habilidades</h1></div>
    <div class="acciones">
        <button type="button" class="btn btn-principal" data-bs-toggle="modal" data-bs-target="#modal-habilidad" data-nuevo>
            <i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar habilidad</button>
    </div>
</div>

<?php foreach ($grupos as [$nombre_grupo, $lista]): ?>
    <h2 class="h3 mb-3 mt-4"><?= e($nombre_grupo) ?> <span class="small text-mute fw-normal">· <?= count($lista) ?></span></h2>
    <div class="tabla-marco">
        <?php if (!$lista): ?>
            <p class="p-3 mb-0 text-mute">Aún no hay habilidades de este tipo.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table tabla-datos table-striped mb-0">
                <thead><tr>
                    <th scope="col">Habilidad</th><th scope="col">Descripción</th>
                    <th scope="col">Alumnos que la tienen</th><th scope="col">Proyectos que la piden</th>
                    <th scope="col" class="acciones">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($lista as $h): $en_uso = $h['alumnos'] + $h['proyectos'] > 0; ?>
                    <tr>
                        <td><strong><?= e($h['nombre_habilidad']) ?></strong></td>
                        <td><span class="small"><?= e($h['descripcion'] ?? '') ?></span></td>
                        <td><?= (int) $h['alumnos'] ?></td>
                        <td><?= (int) $h['proyectos'] ?></td>
                        <td class="acciones text-nowrap">
                            <button type="button" class="btn btn-secundario btn-sm" data-bs-toggle="modal" data-bs-target="#modal-habilidad"
                                data-original="<?= e($h['nombre_habilidad']) ?>" data-tipo="<?= e($h['tipo']) ?>" data-descripcion="<?= e($h['descripcion'] ?? '') ?>">
                                <i class="bi bi-pencil" aria-hidden="true"></i>Editar</button>
                            <?php if (!$en_uso): ?>
                                <form method="post" class="d-inline"><?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="original" value="<?= e($h['nombre_habilidad']) ?>">
                                    <button type="submit" class="btn btn-rechazar-contorno btn-sm" data-confirmar="¿Eliminar la habilidad «<?= e($h['nombre_habilidad']) ?>»?">
                                        <i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">Eliminar</span></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <div class="pie-tabla"><span>No se puede borrar una habilidad en uso; solo editarla.</span></div>
    </div>
<?php endforeach; ?>

<div class="mt-4">
    <div class="alert alert-info" role="alert"><i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div class="alert-cuerpo">Al renombrar una habilidad, el cambio se refleja en todos los perfiles de alumnos y proyectos que la usan.</div></div>
</div>

<div class="modal fade" id="modal-habilidad" tabindex="-1" aria-labelledby="modal-habilidad-t" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post" novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="original" value="<?= e($form['original']) ?>">
            <div class="modal-header">
                <h2 class="modal-title h3" id="modal-habilidad-t"><?= $form['original'] !== '' ? 'Editar habilidad' : 'Agregar habilidad' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body d-grid gap-3">
                <?= alerta_errores(isset($errores['_general']) ? $errores : []) ?>
                <?= campo('nombre', 'Nombre', $form['nombre'] ?: $form['original'], ['placeholder' => 'Ej. JavaScript o Trabajo en equipo', 'requerido' => true, 'maxlength' => 80, 'error' => $errores['nombre'] ?? null]) ?>
                <?= campo_select('tipo', 'Tipo', $form['tipo'], ['dura' => 'Técnica (dura)', 'blanda' => 'Socioemocional (blanda)'], ['requerido' => true, 'error' => $errores['tipo'] ?? null]) ?>
                <?= campo('descripcion', 'Descripción', $form['descripcion'], ['placeholder' => 'Breve descripción de la habilidad', 'opcional' => true, 'maxlength' => 255, 'error' => $errores['descripcion'] ?? null]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-terciario" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-principal">Guardar</button>
            </div>
        </form>
    </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('modal-habilidad');
  modal.addEventListener('show.bs.modal', (ev) => {
    const b = ev.relatedTarget;
    if (!b) return; // se abrió por un error de validación: conserva lo escrito
    const f = modal.querySelector('form');
    const editar = b.dataset.original !== undefined;
    f.original.value = editar ? b.dataset.original : '';
    f.nombre.value = editar ? b.dataset.original : '';
    f.tipo.value = editar ? b.dataset.tipo : 'dura';
    f.descripcion.value = editar ? b.dataset.descripcion : '';
    f.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    modal.querySelector('.modal-title').textContent = editar ? 'Editar habilidad' : 'Agregar habilidad';
  });
  <?php if ($errores): ?>bootstrap.Modal.getOrCreateInstance(modal).show();<?php endif; ?>
});
</script>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
