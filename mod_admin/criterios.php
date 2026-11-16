<?php
/** Pantalla D4 · Criterios de evaluación (los pesos deben sumar 100 %). */
require_once __DIR__ . '/../inc/inicio.php';
requiere_rol('administrador');
$pdo = conexion();

$form = ['original' => '', 'nombre' => '', 'descripcion' => '', 'peso' => ''];
$errores = [];

if (es_post()) {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    $form['original'] = (string) ($_POST['original'] ?? '');

    if ($accion === 'eliminar') {
        try {
            $s = $pdo->prepare('DELETE FROM criterio WHERE nombre_criterio = ?');
            $s->execute([$form['original']]);
            if ($s->rowCount()) {
                registrar_auditoria('eliminar', 'criterio', $form['original']);
                aviso('exito', 'Criterio eliminado. Revisa que los pesos sigan sumando 100 %.');
            }
        } catch (PDOException $ex) {
            aviso('peligro', 'No se puede borrar un criterio que ya se usó en evaluaciones; solo editarlo.');
        }
        redirigir('mod_admin/criterios.php');
    }

    $form['nombre']      = limpiar($_POST['nombre'] ?? '');
    $form['descripcion'] = limpiar($_POST['descripcion'] ?? '');
    $form['peso']        = str_replace(',', '.', trim((string) ($_POST['peso'] ?? '')));

    if ($form['nombre'] === '' || mb_strlen($form['nombre']) > 80) {
        $errores['nombre'] = 'Escribe el nombre (máximo 80 caracteres).';
    } else {
        $s = $pdo->prepare('SELECT COUNT(*) FROM criterio WHERE nombre_criterio = ? AND nombre_criterio <> ?');
        $s->execute([$form['nombre'], $form['original']]);
        if ($s->fetchColumn() > 0) {
            $errores['nombre'] = 'Ya existe un criterio con ese nombre.';
        }
    }
    if (!is_numeric($form['peso']) || (float) $form['peso'] <= 0 || (float) $form['peso'] > 100
        || !preg_match('/^\d{1,3}(\.\d{1,2})?$/', $form['peso'])) {
        $errores['peso'] = 'El peso es un porcentaje mayor que 0 y hasta 100, con máximo 2 decimales.';
    }
    if (mb_strlen($form['descripcion']) > 255) {
        $errores['descripcion'] = 'Máximo 255 caracteres.';
    }

    if (!$errores) {
        try {
            if ($form['original'] === '') {
                $pdo->prepare('INSERT INTO criterio (nombre_criterio, descripcion, peso) VALUES (?, ?, ?)')
                    ->execute([$form['nombre'], $form['descripcion'] ?: null, $form['peso']]);
                registrar_auditoria('crear', 'criterio', $form['nombre']);
                aviso('exito', 'Criterio agregado.');
            } else {
                $pdo->prepare('UPDATE criterio SET nombre_criterio = ?, descripcion = ?, peso = ? WHERE nombre_criterio = ?')
                    ->execute([$form['nombre'], $form['descripcion'] ?: null, $form['peso'], $form['original']]);
                registrar_auditoria('editar', 'criterio', $form['nombre']);
                aviso('exito', 'Criterio actualizado.');
            }
            redirigir('mod_admin/criterios.php');
        } catch (PDOException $ex) {
            error_log((string) $ex);
            $errores['_general'] = mensaje_error_bd($ex);
        }
    }
}

$criterios = $pdo->query('SELECT c.nombre_criterio, c.descripcion, c.peso,
        (SELECT COUNT(*) FROM evaluacion_criterio ec WHERE ec.nombre_criterio = c.nombre_criterio) AS usos
    FROM criterio c ORDER BY c.peso DESC, c.nombre_criterio')->fetchAll();
$suma = round(array_sum(array_map(fn ($c) => (float) $c['peso'], $criterios)), 2);
$pct = fn (float $n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') . ' %';

$titulo = 'Criterios de evaluación';
$menu_activo = 'mod_admin/criterios.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Catálogos', null], ['Criterios', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div><h1 class="titulo-izquierda">Criterios de evaluación</h1></div>
    <div class="acciones">
        <button type="button" class="btn btn-principal" data-bs-toggle="modal" data-bs-target="#modal-criterio">
            Agregar criterio</button>
    </div>
</div>

<?php if (abs($suma - 100) < 0.001): ?>
    <div class="suma-pesos ok mb-3"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Suma actual: 100 %</span></div>
<?php else: ?>
    <div class="suma-pesos falta mb-3"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <span>Suma actual: <?= $pct($suma) ?> · <?= $suma < 100 ? 'faltan ' . $pct(100 - $suma) : 'sobran ' . $pct($suma - 100) ?></span></div>
    <div class="alert alert-warning" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div class="alert-cuerpo">Las empresas no podrán evaluar hasta que los pesos sumen 100 %.</div></div>
<?php endif; ?>

<div class="tabla-marco">
    <?php if (!$criterios): ?>
        <p class="p-3 mb-0 text-mute">Aún no hay criterios. Agrega al menos uno para que las empresas puedan evaluar.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tabla-datos mb-0">
            <thead><tr><th scope="col">Criterio</th><th scope="col">Descripción</th><th scope="col">Peso</th><th scope="col" class="acciones">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($criterios as $c): ?>
                <tr>
                    <td><strong><?= e($c['nombre_criterio']) ?></strong></td>
                    <td><?= e($c['descripcion'] ?? '') ?></td>
                    <td><strong><?= $pct((float) $c['peso']) ?></strong></td>
                    <td class="acciones text-nowrap">
                        <button type="button" class="btn btn-secundario btn-sm" data-bs-toggle="modal" data-bs-target="#modal-criterio"
                            data-original="<?= e($c['nombre_criterio']) ?>" data-descripcion="<?= e($c['descripcion'] ?? '') ?>" data-peso="<?= e(rtrim(rtrim($c['peso'], '0'), '.')) ?>">
                            Editar</button>
                        <?php if (!$c['usos']): ?>
                            <form method="post" class="d-inline"><?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="original" value="<?= e($c['nombre_criterio']) ?>">
                                <button type="submit" class="btn btn-rechazar-contorno btn-sm" data-confirmar="¿Eliminar el criterio «<?= e($c['nombre_criterio']) ?>»?">
                                    <span class="visually-hidden">Eliminar</span></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <div class="pie-tabla"><span><?= count($criterios) ?> criterio<?= count($criterios) === 1 ? '' : 's' ?></span><strong>Total: <?= $pct($suma) ?></strong></div>
</div>

<div class="modal fade" id="modal-criterio" tabindex="-1" aria-labelledby="modal-criterio-t" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post" novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="original" value="<?= e($form['original']) ?>">
            <div class="modal-header">
                <h2 class="modal-title h3" id="modal-criterio-t"><?= $form['original'] !== '' ? 'Editar criterio' : 'Agregar criterio' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body d-grid gap-3">
                <?= alerta_errores(isset($errores['_general']) ? $errores : []) ?>
                <?= campo('nombre', 'Nombre', $form['nombre'] ?: $form['original'], ['placeholder' => 'Ej. Dominio técnico', 'requerido' => true, 'maxlength' => 80, 'error' => $errores['nombre'] ?? null]) ?>
                <?= campo('descripcion', 'Descripción', $form['descripcion'], ['placeholder' => 'Qué se califica con este criterio', 'opcional' => true, 'maxlength' => 255, 'error' => $errores['descripcion'] ?? null]) ?>
                <?= campo('peso', 'Peso (%)', $form['peso'], ['placeholder' => 'Ej. 25', 'inputmode' => 'decimal', 'requerido' => true, 'maxlength' => 6, 'error' => $errores['peso'] ?? null,
                    'ayuda' => 'Porcentaje del total. Todos los criterios deben sumar 100 %.']) ?>
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
  const modal = document.getElementById('modal-criterio');
  modal.addEventListener('show.bs.modal', (ev) => {
    const b = ev.relatedTarget;
    if (!b) return;
    const f = modal.querySelector('form');
    const editar = b.dataset.original !== undefined;
    f.original.value = editar ? b.dataset.original : '';
    f.nombre.value = editar ? b.dataset.original : '';
    f.descripcion.value = editar ? b.dataset.descripcion : '';
    f.peso.value = editar ? b.dataset.peso : '';
    f.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    modal.querySelector('.modal-title').textContent = editar ? 'Editar criterio' : 'Agregar criterio';
  });
  <?php if ($errores): ?>bootstrap.Modal.getOrCreateInstance(modal).show();<?php endif; ?>
});
</script>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
