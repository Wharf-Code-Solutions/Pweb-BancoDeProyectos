<?php
/** Pantalla A2 · Mis habilidades (alumno). */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('alumno');
$pdo = conexion();
$matricula = $usuario['clave'];

$catalogo = $pdo->query('SELECT nombre_habilidad, tipo FROM habilidad ORDER BY tipo, nombre_habilidad')->fetchAll(PDO::FETCH_KEY_PAIR);
$form = ['original' => '', 'habilidad' => '', 'nivel' => 'basico', 'descripcion' => '', 'evidencia' => ''];
$errores = [];

if (es_post()) {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    $form['original'] = (string) ($_POST['original'] ?? '');

    if ($accion === 'quitar') {
        $s = $pdo->prepare('DELETE FROM perfil_habilidad WHERE matricula = ? AND nombre_habilidad = ?');
        $s->execute([$matricula, $form['original']]);
        if ($s->rowCount()) {
            registrar_auditoria('eliminar', 'perfil_habilidad', $matricula . '|' . $form['original']);
            aviso('exito', 'Habilidad quitada: ' . $form['original'] . '.');
        }
        redirigir('mod_alumno/habilidades.php');
    }

    $form['habilidad']   = (string) ($_POST['habilidad'] ?? $form['original']);
    $form['nivel']       = (string) ($_POST['nivel'] ?? '');
    $form['descripcion'] = limpiar($_POST['descripcion'] ?? '');
    $form['evidencia']   = trim((string) ($_POST['evidencia'] ?? ''));
    if ($form['original'] !== '') {
        $form['habilidad'] = $form['original']; // al editar no se cambia la habilidad, solo sus datos
    }

    if (!isset($catalogo[$form['habilidad']])) {
        $errores['habilidad'] = 'Elige una habilidad del catálogo.';
    } elseif ($form['original'] === '') {
        $s = $pdo->prepare('SELECT COUNT(*) FROM perfil_habilidad WHERE matricula = ? AND nombre_habilidad = ?');
        $s->execute([$matricula, $form['habilidad']]);
        if ($s->fetchColumn() > 0) {
            $errores['habilidad'] = 'Ya tienes esa habilidad. Edítala para cambiar su nivel.';
        }
    }
    if (!isset(NIVELES[$form['nivel']])) {
        $errores['nivel'] = 'Elige tu nivel.';
    }
    if (mb_strlen($form['descripcion']) > 255) {
        $errores['descripcion'] = 'Máximo 255 caracteres.';
    }
    if ($form['evidencia'] !== '' && (mb_strlen($form['evidencia']) > 500 || !preg_match('#^https?://#i', $form['evidencia']) || !filter_var($form['evidencia'], FILTER_VALIDATE_URL))) {
        $errores['evidencia'] = 'Escribe un enlace completo que empiece con https:// (máximo 500 caracteres).';
    }

    if (!$errores) {
        $valores = [$form['nivel'], $form['descripcion'] ?: null, $form['evidencia'] ?: null, $matricula, $form['habilidad']];
        if ($form['original'] === '') {
            $pdo->prepare('INSERT INTO perfil_habilidad (nivel, descripcion, evidencia, matricula, nombre_habilidad) VALUES (?, ?, ?, ?, ?)')->execute($valores);
            registrar_auditoria('crear', 'perfil_habilidad', $matricula . '|' . $form['habilidad']);
            aviso('exito', 'Habilidad agregada: ' . $form['habilidad'] . ' · ' . NIVELES[$form['nivel']] . '.');
        } else {
            $pdo->prepare('UPDATE perfil_habilidad SET nivel = ?, descripcion = ?, evidencia = ? WHERE matricula = ? AND nombre_habilidad = ?')->execute($valores);
            registrar_auditoria('editar', 'perfil_habilidad', $matricula . '|' . $form['habilidad']);
            aviso('exito', 'Habilidad actualizada: ' . $form['habilidad'] . ' · ' . NIVELES[$form['nivel']] . '.');
        }
        redirigir('mod_alumno/habilidades.php');
    }
}

$s = $pdo->prepare('SELECT ph.*, h.tipo FROM perfil_habilidad ph JOIN habilidad h ON h.nombre_habilidad = ph.nombre_habilidad
    WHERE ph.matricula = ? ORDER BY FIELD(ph.nivel, \'avanzado\', \'intermedio\', \'basico\'), ph.nombre_habilidad');
$s->execute([$matricula]);
$mias = $s->fetchAll();
$grupos = ['dura' => 'Técnicas', 'blanda' => 'Socioemocionales'];
$clase_nivel = ['basico' => 'neutro', 'intermedio' => 'info', 'avanzado' => 'primario'];

$titulo = 'Mis habilidades';
$menu_activo = 'mod_alumno/habilidades.php';
$migas = [['Panel', 'mod_alumno/index.php'], ['Mis habilidades', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div><h1 class="titulo-izquierda">Mis habilidades</h1></div>
    <div class="acciones"><button type="button" class="btn btn-principal" data-bs-toggle="modal" data-bs-target="#modal-habilidad">Agregar habilidad</button></div>
</div>

<?php foreach ($grupos as $tipo => $nombre_grupo): $lista = array_values(array_filter($mias, fn ($h) => $h['tipo'] === $tipo)); ?>
    <h2 class="h3 mb-3"><?= e($nombre_grupo) ?> <span class="small text-mute fw-normal">· <?= count($lista) ?></span></h2>
    <?php if (!$lista): ?>
        <div class="vacio mb-5">
            <h3>Aún no registras habilidades <?= $tipo === 'dura' ? 'técnicas' : 'socioemocionales' ?></h3>
            <p><?= $tipo === 'dura' ? 'Agrega los lenguajes y herramientas que dominas; las empresas las comparan con lo que piden sus proyectos.'
                : 'Agrega habilidades como trabajo en equipo o comunicación; muchas empresas las piden como deseables.' ?></p>
            <button type="button" class="btn btn-secundario" data-bs-toggle="modal" data-bs-target="#modal-habilidad" data-tipo="<?= $tipo ?>">Agregar habilidad <?= $tipo === 'dura' ? 'técnica' : 'socioemocional' ?></button>
        </div>
    <?php else: ?>
        <div class="row g-3 mb-5">
            <?php foreach ($lista as $h): ?>
                <div class="col-md-6 col-lg-4"><div class="card h-100"><div class="card-body d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between gap-2 align-items-start"><strong class="text-navy"><?= e($h['nombre_habilidad']) ?></strong>
                        <span class="estado estado-<?= $clase_nivel[$h['nivel']] ?>"><?= e(NIVELES[$h['nivel']]) ?></span></div>
                    <?php if ($h['descripcion']): ?><p class="small text-mute mb-0"><?= e($h['descripcion']) ?></p><?php endif; ?>
                    <?php if ($h['evidencia']): ?>
                        <a href="<?= e($h['evidencia']) ?>" class="small text-break" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('#^https?://(www\.)?#i', '', $h['evidencia'])) ?></a>
                    <?php else: ?><span class="small text-mute">Sin evidencia</span><?php endif; ?>
                    <div class="d-flex gap-2 mt-auto pt-2">
                        <button type="button" class="btn btn-secundario btn-sm" data-bs-toggle="modal" data-bs-target="#modal-habilidad"
                            data-original="<?= e($h['nombre_habilidad']) ?>" data-nivel="<?= e($h['nivel']) ?>" data-descripcion="<?= e($h['descripcion'] ?? '') ?>" data-evidencia="<?= e($h['evidencia'] ?? '') ?>">
                            Editar</button>
                        <form method="post" class="d-inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="quitar"><input type="hidden" name="original" value="<?= e($h['nombre_habilidad']) ?>">
                            <button type="submit" class="btn btn-rechazar-contorno btn-sm" data-confirmar="¿Quitar «<?= e($h['nombre_habilidad']) ?>» de tu perfil?">Quitar</button></form>
                    </div>
                </div></div></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

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
                <div id="bloque-selector">
                    <label class="form-label" for="habilidad">Habilidad<span class="req" aria-hidden="true">*</span></label>
                    <select class="form-select<?= isset($errores['habilidad']) ? ' is-invalid' : '' ?>" id="habilidad" name="habilidad">
                        <option value="">Selecciona…</option>
                        <?php foreach ($grupos as $tipo => $nombre_grupo): ?>
                            <optgroup label="<?= e($nombre_grupo) ?>" data-tipo="<?= $tipo ?>">
                                <?php foreach ($catalogo as $nom => $t): if ($t !== $tipo) continue; ?>
                                    <option<?= $form['habilidad'] === $nom ? ' selected' : '' ?>><?= e($nom) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['habilidad'])): ?><div class="invalid-feedback"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><?= e($errores['habilidad']) ?></div><?php endif; ?>
                </div>
                <?= campo_select('nivel', 'Nivel', $form['nivel'], NIVELES, ['requerido' => true, 'error' => $errores['nivel'] ?? null]) ?>
                <?= campo('descripcion', 'Descripción', $form['descripcion'], ['placeholder' => 'Ej. Desarrollé una API REST para la tienda escolar', 'opcional' => true, 'maxlength' => 255, 'error' => $errores['descripcion'] ?? null,
                    'ayuda' => 'Qué has hecho con esta habilidad.']) ?>
                <?= campo('evidencia', 'Evidencia', $form['evidencia'], ['placeholder' => 'https://github.com/usuario/proyecto', 'tipo' => 'url', 'opcional' => true, 'maxlength' => 500, 'error' => $errores['evidencia'] ?? null,
                    'ayuda' => 'Enlace a un repositorio, constancia o portafolio.']) ?>
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
  const f = modal.querySelector('form');
  const ajustar = (editar) => {
    document.getElementById('bloque-selector').hidden = editar;
    modal.querySelector('.modal-title').textContent = editar ? 'Editar habilidad: ' + f.original.value : 'Agregar habilidad';
  };
  modal.addEventListener('show.bs.modal', (ev) => {
    const b = ev.relatedTarget;
    if (!b) { ajustar(f.original.value !== ''); return; }
    const editar = b.dataset.original !== undefined;
    f.original.value = editar ? b.dataset.original : '';
    f.habilidad.value = '';
    f.nivel.value = editar ? b.dataset.nivel : 'basico';
    f.descripcion.value = editar ? b.dataset.descripcion : '';
    f.evidencia.value = editar ? b.dataset.evidencia : '';
    // Si se abrió desde "Agregar habilidad socioemocional", se muestra primero ese grupo.
    f.habilidad.querySelectorAll('optgroup').forEach((g) => { g.hidden = !!b.dataset.tipo && g.dataset.tipo !== b.dataset.tipo; });
    f.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    ajustar(editar);
  });
  <?php if ($errores): ?>bootstrap.Modal.getOrCreateInstance(modal).show();<?php endif; ?>
});
</script>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
