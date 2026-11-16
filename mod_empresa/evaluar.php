<?php
/** Pantalla E5 · Evaluar candidato (empresa). Cada evaluación queda registrada; la última es la vigente. */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('empresa');
$pdo = conexion();

$id = (string) ($_GET['id'] ?? '');
$c = obtener_candidatura($id);
if (!$c || $c['rfc_empresa'] !== $usuario['clave']) {
    error_http(404);
}
$evaluable = in_array($c['estado'], ['pendiente', 'en_revision'], true) && in_array($c['estado_proyecto'], ['publicado', 'en_curso'], true);
$criterios = $pdo->query('SELECT * FROM criterio ORDER BY peso DESC, nombre_criterio')->fetchAll();
$listos = criterios_listos();
$evaluaciones = evaluaciones_candidatura($id);
$previa = $evaluaciones[0] ?? null;

// Valores iniciales: los de la última evaluación, si la hay.
$valores = [];
$comentarios = [];
foreach ($previa['criterios'] ?? [] as $pc) {
    $valores[$pc['nombre_criterio']] = rtrim(rtrim($pc['puntuacion'], '0'), '.');
    $comentarios[$pc['nombre_criterio']] = (string) $pc['comentario'];
}
$general = (string) ($previa['comentarios'] ?? '');
$presel = (bool) ($previa['preseleccionado'] ?? false);
$errores = [];

if (es_post()) {
    verificar_csrf();
    if (!$evaluable || !$listos) {
        aviso('advertencia', $listos ? 'Esta candidatura ya no se puede evaluar.' : 'Los criterios de evaluación aún no suman 100 %.');
        redirigir('mod_empresa/evaluar.php?id=' . urlencode($id));
    }
    $total = 0.0;
    foreach ($criterios as $i => $cr) {
        $v = str_replace(',', '.', trim((string) ($_POST['punt'][$i] ?? '')));
        $valores[$cr['nombre_criterio']] = $v;
        $comentarios[$cr['nombre_criterio']] = mb_substr(limpiar($_POST['coment'][$i] ?? ''), 0, 500);
        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $v) || (float) $v > 100) {
            $errores["punt$i"] = 'Escribe una calificación de 0 a 100.';
        } else {
            $total += (float) $v * (float) $cr['peso'] / 100;
        }
    }
    $general = trim(str_replace("\r\n", "\n", (string) ($_POST['comentarios'] ?? '')));
    $presel = ($_POST['preseleccionado'] ?? '') === '1';
    if (mb_strlen($general) > 2000) {
        $errores['comentarios'] = 'Máximo 2000 caracteres.';
    }

    if (!$errores) {
        $total = round($total, 2);
        try {
            $pdo->beginTransaction();
            $folio = siguiente_folio('EVA');
            $pdo->prepare('INSERT INTO evaluacion_candidato (c_evaluacion, c_candidatura, rfc_empresa, puntuacion_total, comentarios, preseleccionado) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$folio, $id, $usuario['clave'], $total, $general ?: null, $presel ? 1 : 0]);
            $ins = $pdo->prepare('INSERT INTO evaluacion_criterio (c_evaluacion, nombre_criterio, puntuacion, comentario) VALUES (?, ?, ?, ?)');
            foreach ($criterios as $cr) {
                $ins->execute([$folio, $cr['nombre_criterio'], $valores[$cr['nombre_criterio']], $comentarios[$cr['nombre_criterio']] ?: null]);
            }
            // La primera evaluación pasa la candidatura a "en revisión" para que el Tec decida.
            $pdo->prepare("UPDATE solicitud_candidatura SET estado = 'en_revision' WHERE c_candidatura = ? AND estado = 'pendiente'")->execute([$id]);
            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log((string) $ex);
            $errores['_general'] = mensaje_error_bd($ex);
        }
        if (!$errores) {
            registrar_auditoria('evaluar', 'solicitud_candidatura', $id);
            notificar($c['c_usuario_alumno'], 'La empresa evaluó tu candidatura a «' . $c['titulo'] . '»' . ($presel ? ' y te preseleccionó.' : '.'));
            if (!$previa) {
                notificar_administradores('Hay una candidatura por decidir: ' . $c['nombre'] . ' en «' . $c['titulo'] . '».');
            }
            aviso('exito', 'Evaluación guardada. Puntuación total ponderada: ' . puntos($total) . '.');
            redirigir('mod_empresa/evaluar.php?id=' . urlencode($id));
        }
    }
}

$titulo = 'Evaluar candidato';
$menu_activo = 'mod_empresa/proyectos.php';
$migas = [['Panel', 'mod_empresa/index.php'], ['Mis proyectos', 'mod_empresa/proyectos.php'], [$c['titulo'], 'mod_empresa/proyecto.php?id=' . urlencode($c['c_proyecto'])],
          ['Candidatos', 'mod_empresa/candidatos.php?id=' . urlencode($c['c_proyecto'])], [$c['nombre'], null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$suma_pesos = array_sum(array_map(fn ($cr) => (float) $cr['peso'], $criterios));
?>
<div class="encabezado-pagina">
    <div>
        <span class="etiqueta">Evaluar candidato</span>
        <h1 class="titulo-izquierda"><?= e($c['nombre']) ?></h1>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
            <?= insignia('candidatura', $c['estado']) ?><?= $presel && $previa ? insignia_preseleccion() : '' ?>
            <span class="folio"><?= e($id) ?></span><span class="small text-mute"><?= e($c['titulo']) ?></span>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6 d-grid gap-3 align-self-start">
        <?= html_perfil_candidato($c) ?>
        <?= html_comparativa(requisitos_proyecto($c['c_proyecto']), perfil_alumno_completo($c['matricula'])) ?>
        <?php if ($evaluaciones): ?>
            <div class="card"><div class="card-header"><h2 class="h3 mb-0">Evaluaciones anteriores</h2></div>
                <div class="card-body"><ol class="linea-tiempo">
                    <?php foreach ($evaluaciones as $n => $ev): ?>
                        <li><span class="hito exito"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
                            <div class="evento">Evaluación <?= count($evaluaciones) - $n ?> · <span class="puntuacion"><?= puntos($ev['puntuacion_total']) ?></span>
                                <?= $ev['preseleccionado'] ? '<span class="etiqueta-dato"><i class="bi bi-star" aria-hidden="true"></i>Preseleccionado</span>' : '' ?></div>
                            <div class="cuando"><?= e(fecha_hora($ev['fecha'])) ?> · <?= e($ev['evaluador']) ?></div>
                            <?php if ($ev['comentarios']): ?><div class="justificacion"><span class="quien">Comentario general</span><?= nl2br(e($ev['comentarios'])) ?></div><?php endif; ?></li>
                    <?php endforeach; ?>
                </ol></div></div>
        <?php endif; ?>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2"><h2 class="h3 mb-0">Evaluación</h2>
                <span class="small text-mute">Pesos: <?= rtrim(rtrim(number_format($suma_pesos, 2, '.', ''), '0'), '.') ?> %</span></div>
            <div class="card-body">
                <?php if (!$evaluable): ?>
                    <p class="text-mute mb-0">Esta candidatura está «<?= e(nombre_estado('candidatura', $c['estado'])) ?>» y ya no se puede evaluar.</p>
                <?php elseif (!$listos): ?>
                    <div class="alert alert-warning mb-0" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <div class="alert-cuerpo">Por ahora no se puede evaluar: los pesos de los criterios del Tec no suman 100 %.</div></div>
                <?php else: ?>
                    <?= alerta_errores($errores) ?>
                    <form method="post" novalidate id="form-evaluacion">
                        <?= csrf_campo() ?>
                        <?php foreach ($criterios as $i => $cr): $err = $errores["punt$i"] ?? null; ?>
                            <div class="criterio">
                                <div class="d-flex justify-content-between gap-2"><strong><?= e($cr['nombre_criterio']) ?></strong>
                                    <span class="etiqueta-dato">Peso <?= rtrim(rtrim($cr['peso'], '0'), '.') ?> %</span></div>
                                <?php if ($cr['descripcion']): ?><p class="small text-mute mb-2"><?= e($cr['descripcion']) ?></p><?php endif; ?>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <label class="form-label" for="c<?= $i ?>">Calificación<span class="visually-hidden"> de <?= e($cr['nombre_criterio']) ?></span><span class="req" aria-hidden="true">*</span></label>
                                        <input class="form-control calificacion<?= $err ? ' is-invalid' : '' ?>" id="c<?= $i ?>" name="punt[<?= $i ?>]" type="number" min="0" max="100" step="0.01"
                                            data-peso="<?= e($cr['peso']) ?>" value="<?= e($valores[$cr['nombre_criterio']] ?? '') ?>" placeholder="0 a 100" inputmode="decimal" aria-describedby="c<?= $i ?>-a<?= $err ? " c{$i}-e" : '' ?>"<?= $err ? ' aria-invalid="true"' : '' ?> required>
                                        <div class="form-text" id="c<?= $i ?>-a">De 0 a 100</div>
                                        <?php if ($err): ?><div class="invalid-feedback" id="c<?= $i ?>-e"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><?= e($err) ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-8">
                                        <label class="form-label" for="m<?= $i ?>">Comentario<span class="visually-hidden"> de <?= e($cr['nombre_criterio']) ?></span> <span class="fw-normal text-mute">(opcional)</span></label>
                                        <input class="form-control" id="m<?= $i ?>" name="coment[<?= $i ?>]" type="text" maxlength="500" placeholder="Ej. Buen manejo de bases de datos" value="<?= e($comentarios[$cr['nombre_criterio']] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?= campo_area('comentarios', 'Comentarios generales', $general, ['placeholder' => 'Fortalezas, áreas de mejora y recomendación final', 'maxlength' => 2000, 'opcional' => true, 'filas' => 3, 'error' => $errores['comentarios'] ?? null, 'clase_contenedor' => 'mt-2']) ?>
                        <div class="resaltado mt-3"><div class="form-check">
                            <input class="form-check-input" type="checkbox" id="preseleccionado" name="preseleccionado" value="1"<?= $presel ? ' checked' : '' ?> aria-describedby="pre-a">
                            <label class="form-check-label" for="preseleccionado"><strong>Preseleccionar candidato</strong></label></div>
                            <div class="form-text" id="pre-a">La preselección es una recomendación; la asignación la autoriza el Tec.</div></div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                            <div><span class="small text-mute d-block">Puntuación total ponderada</span>
                                <span class="puntuacion grande" id="total">—</span> <span class="small text-mute">se guarda al enviar</span></div>
                            <div class="d-flex gap-2">
                                <a class="btn btn-terciario" href="<?= e(url('mod_empresa/candidatos.php?id=' . urlencode($c['c_proyecto']))) ?>">Cancelar</a>
                                <button type="submit" class="btn btn-principal"><i class="bi bi-floppy" aria-hidden="true"></i>Guardar evaluación</button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
  const f = document.getElementById('form-evaluacion');
  if (!f) return;
  const total = document.getElementById('total');
  const calcular = () => {
    let suma = 0, completo = true;
    f.querySelectorAll('.calificacion').forEach((i) => {
      const v = parseFloat(i.value);
      if (isNaN(v)) completo = false; else suma += Math.min(Math.max(v, 0), 100) * parseFloat(i.dataset.peso) / 100;
    });
    total.textContent = completo ? suma.toFixed(2) : '—';
  };
  f.addEventListener('input', calcular);
  calcular();
})();
</script>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
