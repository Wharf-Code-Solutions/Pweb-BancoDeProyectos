<?php
/** Pantalla D6 · Revisar proyecto: aprobar y publicar, devolver o rechazar. */
require_once __DIR__ . '/../inc/inicio.php';
$admin = requiere_rol('administrador');
$pdo = conexion();

$id = (string) ($_GET['id'] ?? '');
$p = obtener_proyecto($id);
if (!$p) {
    error_http(404);
}
if ($p['estado'] !== 'en_revision') {
    aviso('info', 'Este proyecto ya no está en revisión.');
    redirigir('mod_admin/proyecto.php?id=' . urlencode($id));
}

$decisiones = [
    'aprobar'  => ['aprobada', 'publicado', 'Aprobar y publicar', 'Los alumnos y profesores podrán verlo.', 'exito', 'bi-check-circle', 'btn-aprobar'],
    'devolver' => ['devuelta', 'devuelto', 'Devolver para correcciones', 'La empresa podrá editarlo y reenviarlo.', 'advertencia', 'bi-arrow-return-left', 'btn-devolver'],
    'rechazar' => ['rechazada', 'rechazado', 'Rechazar', 'El proyecto se cierra y no podrá reenviarse.', 'peligro', 'bi-x-circle', 'btn-rechazar'],
];
$decision = '';
$justificacion = '';
$errores = [];

if (es_post()) {
    verificar_csrf();
    $decision = (string) ($_POST['decision'] ?? '');
    $justificacion = trim(str_replace("\r\n", "\n", (string) ($_POST['justificacion'] ?? '')));
    if (!isset($decisiones[$decision])) {
        $errores['decision'] = 'Elige una resolución.';
    } elseif ($decision !== 'aprobar' && mb_strlen($justificacion) < 10) {
        $errores['justificacion'] = $decision === 'devolver'
            ? 'Explica a la empresa qué debe corregir (al menos 10 caracteres).'
            : 'Explica el motivo del rechazo (al menos 10 caracteres).';
    }

    if (!$errores) {
        [$valor_decision, $nuevo_estado] = $decisiones[$decision];
        $texto = $justificacion !== '' ? $justificacion : 'Proyecto aprobado y publicado.';
        try {
            $pdo->beginTransaction();
            // La condición sobre el estado evita decidir dos veces si dos administradores revisan a la vez.
            $s = $pdo->prepare("UPDATE proyecto SET estado = ? WHERE c_proyecto = ? AND estado = 'en_revision'");
            $s->execute([$nuevo_estado, $id]);
            if ($s->rowCount() !== 1) {
                throw new RuntimeException('ya_decidido');
            }
            $pdo->prepare('INSERT INTO autorizacion (c_autorizacion, num_empleado_admin, c_proyecto, decision, justificacion) VALUES (?, ?, ?, ?, ?)')
                ->execute([siguiente_folio('AUT'), $admin['clave'], $id, $valor_decision, $texto]);
            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log((string) $ex);
            $errores['_general'] = mensaje_error_bd($ex);
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            aviso('advertencia', 'Otro administrador ya decidió sobre este proyecto.');
            redirigir('mod_admin/proyecto.php?id=' . urlencode($id));
        }

        if (!$errores) {
            $operacion = ['aprobar' => 'aprobar', 'devolver' => 'devolver', 'rechazar' => 'rechazar'][$decision];
            registrar_auditoria($operacion, 'proyecto', $id);
            $mensaje = match ($decision) {
                'aprobar'  => "Tu proyecto «{$p['titulo']}» fue aprobado y publicado.",
                'devolver' => "Tu proyecto «{$p['titulo']}» fue devuelto para correcciones.",
                'rechazar' => "Tu proyecto «{$p['titulo']}» fue rechazado.",
            };
            notificar($p['c_usuario_empresa'], $mensaje);
            aviso('exito', match ($decision) {
                'aprobar'  => 'Proyecto aprobado y publicado.',
                'devolver' => 'Proyecto devuelto a la empresa para correcciones.',
                'rechazar' => 'Proyecto rechazado.',
            });
            redirigir('mod_admin/revisar_proyectos.php');
        }
    }
}

$reenviado = es_reenviado($id);
$eventos = linea_tiempo_proyecto($id);
$enviado = null;
foreach (array_reverse($eventos) as $ev) {
    if (str_contains($ev['texto'], 'nviado a revisión')) {
        $enviado = $ev['fecha'];
        break;
    }
}

$titulo = 'Revisar proyecto';
$menu_activo = 'mod_admin/revisar_proyectos.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Revisión', null], ['Proyectos por revisar', 'mod_admin/revisar_proyectos.php'], ['Revisar', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
?>
<div class="encabezado-pagina">
    <div>
        <h1 class="titulo-izquierda"><?= e($p['titulo']) ?></h1>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
            <?= insignia('proyecto', $p['estado']) ?>
            <?php if ($reenviado): ?><span class="etiqueta-reenviado">Reenviado</span><?php endif; ?>
            <?= html_folio($id) ?>
            <span class="small text-mute"><?= e($p['razon_social']) ?> · <?= e($p['sector']) ?><?= $enviado ? ' · enviado el ' . e(fecha_hora($enviado)) : '' ?></span>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8 d-grid gap-3 align-self-start">
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Contenido del proyecto</h2></div>
            <div class="card-body"><?= html_contenido_proyecto($p, requisitos_proyecto($id)) ?></div></div>
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Planeación</h2></div>
            <div class="card-body">
                <dl class="lista-datos">
                    <div><dt>Duración</dt><dd><?= (int) $p['duracion_estimada'] ?> semanas</dd></div>
                    <div><dt>Modalidad</dt><dd><?= e(MODALIDADES[$p['modalidad']] ?? '—') ?></dd></div>
                    <div><dt>Fechas</dt><dd><?= e(fecha_corta($p['fecha_inicio'])) ?> – <?= e(fecha_corta($p['fecha_fin'])) ?></dd></div>
                    <div><dt>Cupo</dt><dd><?= $p['cupo_alumnos'] ? (int) $p['cupo_alumnos'] . ' alumnos' : 'Sin límite de cupo' ?></dd></div>
                    <div><dt>Recursos</dt><dd><?= e($p['recursos'] ?: '—') ?></dd></div>
                </dl>
            </div></div>
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Historial de revisiones</h2></div>
            <div class="card-body"><?= html_linea_tiempo($eventos) ?></div></div>
    </div>

    <div class="col-lg-4">
        <div class="panel-decision">
            <div class="cab">Decisión</div>
            <form method="post" class="p-3 d-grid gap-3" id="form-decision" novalidate>
                <?= csrf_campo() ?>
                <?= alerta_errores(isset($errores['_general']) ? $errores : []) ?>
                <fieldset>
                    <legend class="form-label">Resolución<span class="req" aria-hidden="true">*</span></legend>
                    <div class="d-grid gap-2">
                        <?php foreach ($decisiones as $clave => [, , $nombre, $detalle, $color, $icono]): ?>
                            <label class="card p-3 d-flex flex-row gap-2 align-items-start opcion-decision" data-color="<?= $color ?>" for="d-<?= $clave ?>" style="cursor:pointer">
                                <input class="form-check-input mt-1" type="radio" name="decision" id="d-<?= $clave ?>" value="<?= $clave ?>"<?= $decision === $clave ? ' checked' : '' ?>>
                                <span><strong class="d-block" style="color:var(--<?= $color ?>)"><i class="bi <?= $icono ?>" aria-hidden="true"></i> <?= e($nombre) ?></strong>
                                    <span class="small text-mute"><?= e($detalle) ?></span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errores['decision'])): ?><div class="invalid-feedback d-flex mt-2"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><?= e($errores['decision']) ?></div><?php endif; ?>
                </fieldset>
                <?= campo_area('justificacion', 'Justificación', $justificacion, ['placeholder' => 'Explica a la empresa el motivo de tu resolución', 'maxlength' => 3000, 'filas' => 5, 'error' => $errores['justificacion'] ?? null,
                    'ayuda' => 'Obligatoria para devolver o rechazar. La empresa la verá destacada.']) ?>
                <button type="submit" class="btn btn-principal w-100" id="boton-decision" disabled>Elige una resolución</button>
                <p class="small text-mute mb-0">Se pedirá confirmación antes de guardar. La decisión no se puede modificar.</p>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
  const datos = <?= json_encode(array_map(fn ($d) => ['texto' => $d[2], 'clase' => $d[6], 'icono' => $d[5], 'color' => $d[4]], $decisiones), JSON_UNESCAPED_UNICODE) ?>;
  const tituloProyecto = <?= json_encode($p['titulo'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const form = document.getElementById('form-decision');
  const boton = document.getElementById('boton-decision');
  const etiqueta = form.querySelector('label[for=justificacion]');
  const actualizar = () => {
    const elegido = form.querySelector('input[name=decision]:checked');
    form.querySelectorAll('.opcion-decision').forEach((l) => {
      const activo = elegido && l.htmlFor === elegido.id;
      l.style.border = activo ? `2px solid var(--${l.dataset.color})` : '';
      l.style.background = activo ? `var(--${l.dataset.color}-bg)` : '';
    });
    if (!elegido) return;
    const d = datos[elegido.value];
    boton.disabled = false;
    boton.className = 'btn w-100 ' + d.clase;
    boton.innerHTML = `<i class="bi ${d.icono}" aria-hidden="true"></i>${d.texto}`;
    etiqueta.firstChild.textContent = elegido.value === 'devolver' ? 'Correcciones solicitadas a la empresa'
      : elegido.value === 'rechazar' ? 'Motivo del rechazo' : 'Comentario para la empresa (opcional)';
  };
  form.addEventListener('change', actualizar);
  form.addEventListener('submit', (ev) => {
    const elegido = form.querySelector('input[name=decision]:checked');
    if (elegido && !confirm(`¿${datos[elegido.value].texto} «${tituloProyecto}»? Esta decisión no se puede modificar.`)) ev.preventDefault();
  });
  actualizar();
})();
</script>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
