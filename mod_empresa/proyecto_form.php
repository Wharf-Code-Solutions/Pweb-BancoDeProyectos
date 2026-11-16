<?php
/**
 * Pantalla E2 · Nuevo / editar proyecto (empresa).
 * Solo se edita en borrador o devuelto. "Guardar borrador" acepta datos
 * incompletos; "Enviar a revisión" exige los campos marcados con **.
 */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('empresa');
$pdo = conexion();
$rfc = $usuario['clave'];

$id = isset($_GET['id']) ? (string) $_GET['id'] : null;
$p = null;
if ($id !== null) {
    $p = obtener_proyecto($id);
    // Control por propiedad: un proyecto ajeno se trata como inexistente.
    if (!$p || $p['rfc_empresa'] !== $rfc) {
        error_http(404);
    }
    if (!in_array($p['estado'], ['borrador', 'devuelto'], true)) {
        aviso('info', 'Este proyecto ya no se puede editar en su estado actual.');
        redirigir('mod_empresa/proyecto.php?id=' . urlencode($id));
    }
}

$campos_texto = ['titulo', 'descripcion', 'problematica', 'objetivos', 'justificacion', 'alcance',
                 'requisitos_academicos', 'duracion_estimada', 'modalidad', 'fecha_inicio', 'fecha_fin', 'recursos', 'cupo_alumnos'];
$d = array_fill_keys($campos_texto, '');
$reqs = [];
if ($p) {
    foreach ($campos_texto as $k) {
        $d[$k] = (string) ($p[$k] ?? '');
    }
    foreach (requisitos_proyecto($id) as $r) {
        $reqs[] = ['habilidad' => $r['nombre_habilidad'], 'nivel' => $r['nivel_minimo'], 'obligatorio' => (int) $r['obligatorio']];
    }
}

$habilidades = $pdo->query('SELECT nombre_habilidad, tipo FROM habilidad ORDER BY tipo, nombre_habilidad')->fetchAll();
$nombres_hab = array_column($habilidades, 'nombre_habilidad');
$errores = [];

if (es_post()) {
    verificar_csrf();
    $enviar = ($_POST['accion'] ?? '') === 'enviar';

    foreach ($campos_texto as $k) {
        $v = (string) ($_POST[$k] ?? '');
        // Los textos largos conservan sus saltos de línea; los cortos se limpian.
        $d[$k] = in_array($k, ['titulo', 'modalidad', 'duracion_estimada', 'fecha_inicio', 'fecha_fin', 'cupo_alumnos'], true)
            ? limpiar($v) : trim(str_replace("\r\n", "\n", $v));
    }

    // Habilidades requeridas (filas dinámicas)
    $reqs = [];
    foreach ((array) ($_POST['req'] ?? []) as $fila) {
        if (!is_array($fila) || ($fila['habilidad'] ?? '') === '') {
            continue;
        }
        $reqs[] = ['habilidad' => (string) $fila['habilidad'], 'nivel' => (string) ($fila['nivel'] ?? ''),
                   'obligatorio' => ($fila['obligatorio'] ?? '1') === '1' ? 1 : 0];
    }

    // Siempre obligatorio
    if ($d['titulo'] === '') {
        $errores['titulo'] = 'El título es obligatorio.';
    } elseif (mb_strlen($d['titulo']) > 150) {
        $errores['titulo'] = 'Máximo 150 caracteres.';
    }
    // Textos largos: la columna TEXT admite 65 535 bytes; se limita a 5000 caracteres por campo.
    foreach (['descripcion', 'problematica', 'objetivos', 'justificacion', 'alcance', 'requisitos_academicos', 'recursos'] as $k) {
        if (mb_strlen($d[$k]) > 5000) {
            $errores[$k] = 'Máximo 5000 caracteres.';
        }
    }
    // Formato (si se escribió algo)
    if ($d['duracion_estimada'] !== '' && (!ctype_digit($d['duracion_estimada']) || (int) $d['duracion_estimada'] < 1 || (int) $d['duracion_estimada'] > 260)) {
        $errores['duracion_estimada'] = 'Escribe la duración en semanas (de 1 a 260).';
    }
    if ($d['modalidad'] !== '' && !isset(MODALIDADES[$d['modalidad']])) {
        $errores['modalidad'] = 'Elige una modalidad.';
    }
    foreach (['fecha_inicio', 'fecha_fin'] as $f) {
        if ($d[$f] !== '' && !es_fecha($d[$f])) {
            $errores[$f] = 'Escribe una fecha válida.';
        }
    }
    if (!isset($errores['fecha_inicio'], $errores['fecha_fin']) && $d['fecha_inicio'] !== '' && $d['fecha_fin'] !== '' && $d['fecha_fin'] < $d['fecha_inicio']) {
        $errores['fecha_fin'] = 'La fecha de fin debe ser igual o posterior a la de inicio.';
    }
    if ($d['cupo_alumnos'] !== '' && (!ctype_digit($d['cupo_alumnos']) || (int) $d['cupo_alumnos'] < 1 || (int) $d['cupo_alumnos'] > 50)) {
        $errores['cupo_alumnos'] = 'El cupo es un número de 1 a 50, o déjalo vacío.';
    }
    $vistas = [];
    foreach ($reqs as $i => $r) {
        if (!in_array($r['habilidad'], $nombres_hab, true) || !isset(NIVELES[$r['nivel']])) {
            $errores['req'] = 'Revisa las habilidades requeridas: elige la habilidad y su nivel.';
        } elseif (isset($vistas[$r['habilidad']])) {
            $errores['req'] = 'La habilidad «' . $r['habilidad'] . '» está repetida.';
        }
        $vistas[$r['habilidad']] = true;
    }

    // Para enviar (o si ya estaba devuelto, porque la base exige datos completos fuera de borrador)
    $exigir_completo = $enviar || ($p && $p['estado'] === 'devuelto');
    $faltan = [];
    if ($exigir_completo) {
        foreach (CAMPOS_ENVIO as $k => $nombre) {
            if ($d[$k] === '' && !isset($errores[$k])) {
                $errores[$k] = "Completa «$nombre» para enviar a revisión.";
                $faltan[] = $nombre;
            }
        }
        if ($enviar && $d['fecha_inicio'] !== '' && !isset($errores['fecha_inicio']) && $d['fecha_inicio'] < date('Y-m-d')) {
            $errores['fecha_inicio'] = 'La fecha de inicio no puede ser anterior a hoy.';
        }
    }

    if (!$errores) {
        $nuevo_estado = $enviar ? 'en_revision' : ($p['estado'] ?? 'borrador');
        $valores = [];
        foreach ($campos_texto as $k) {
            $valores[$k] = $d[$k] === '' ? null : $d[$k];
        }
        try {
            $pdo->beginTransaction();
            if ($p) {
                $c_proyecto = $id;
                $sets = implode(', ', array_map(fn ($k) => "$k = :$k", $campos_texto));
                $s = $pdo->prepare("UPDATE proyecto SET $sets, estado = :estado WHERE c_proyecto = :id AND rfc_empresa = :rfc
                                     AND estado IN ('borrador', 'devuelto')");
                $s->execute($valores + ['estado' => $nuevo_estado, 'id' => $c_proyecto, 'rfc' => $rfc]);
                $pdo->prepare('DELETE FROM requisito_proyecto WHERE c_proyecto = ?')->execute([$c_proyecto]);
            } else {
                $c_proyecto = siguiente_folio('PRY');
                $cols = implode(', ', $campos_texto);
                $marcas = implode(', ', array_map(fn ($k) => ":$k", $campos_texto));
                $pdo->prepare("INSERT INTO proyecto (c_proyecto, rfc_empresa, $cols, estado) VALUES (:id, :rfc, $marcas, :estado)")
                    ->execute($valores + ['id' => $c_proyecto, 'rfc' => $rfc, 'estado' => $nuevo_estado]);
            }
            $ins = $pdo->prepare('INSERT INTO requisito_proyecto (c_proyecto, nombre_habilidad, nivel_minimo, obligatorio) VALUES (?, ?, ?, ?)');
            foreach ($reqs as $r) {
                $ins->execute([$c_proyecto, $r['habilidad'], $r['nivel'], $r['obligatorio']]);
            }
            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log((string) $ex);
            $errores['_general'] = mensaje_error_bd($ex);
        }

        if (!$errores) {
            if (!$p) {
                registrar_auditoria('crear', 'proyecto', $c_proyecto);
            }
            if ($enviar) {
                $reenvio = $p && $p['estado'] === 'devuelto';
                registrar_auditoria($reenvio ? 'reenviar_revision' : 'enviar_revision', 'proyecto', $c_proyecto);
                notificar($usuario['c_usuario'], 'El proyecto «' . $d['titulo'] . '» se envió a revisión.');
                notificar_administradores(($reenvio ? 'Se reenvió a revisión' : 'Hay un proyecto nuevo por revisar') . ': «' . $d['titulo'] . '» de ' . $usuario['subtitulo'] . '.');
                aviso('exito', 'Proyecto enviado a revisión. Te avisaremos cuando el Tec lo revise.');
                redirigir('mod_empresa/proyecto.php?id=' . urlencode($c_proyecto));
            }
            if ($p) {
                registrar_auditoria('editar', 'proyecto', $c_proyecto);
            }
            aviso('exito', 'Borrador guardado. Puedes seguir editándolo y enviarlo cuando esté completo.');
            redirigir('mod_empresa/proyecto_form.php?id=' . urlencode($c_proyecto));
        }
    }
}

$devolucion = ($p && $p['estado'] === 'devuelto') ? ultima_devolucion($id) : null;
if (!$reqs) {
    $reqs[] = ['habilidad' => '', 'nivel' => 'basico', 'obligatorio' => 1];
}

$titulo = $p ? 'Editar proyecto' : 'Nuevo proyecto';
$menu_activo = $p ? 'mod_empresa/proyectos.php' : 'mod_empresa/proyecto_form.php';
$migas = [['Panel', 'mod_empresa/index.php'], ['Mis proyectos', 'mod_empresa/proyectos.php']];
if ($p) {
    $migas[] = [$p['titulo'], 'mod_empresa/proyecto.php?id=' . urlencode($id)];
    $migas[] = ['Editar', null];
} else {
    $migas[] = ['Nuevo proyecto', null];
}
require __DIR__ . '/../menumain/app_encabezado.php';

$err = fn (string $k) => $errores[$k] ?? null;
$pasos = [
    's1' => ['Datos generales', ['titulo']],
    's2' => ['Descripción', ['descripcion', 'problematica', 'objetivos', 'justificacion', 'alcance']],
    's3' => ['Requisitos', []],
    's4' => ['Planeación', ['duracion_estimada', 'modalidad', 'fecha_inicio', 'fecha_fin']],
];

/** Una fila de habilidad requerida. $i es el índice del arreglo req[]. */
$fila_hab = function (string|int $i, array $r) use ($habilidades): string {
    $sel = '<option value="">Selecciona…</option>';
    $grupo = null;
    foreach ($habilidades as $h) {
        if ($grupo !== $h['tipo']) {
            $sel .= ($grupo ? '</optgroup>' : '') . '<optgroup label="' . ($h['tipo'] === 'dura' ? 'Técnicas' : 'Socioemocionales') . '">';
            $grupo = $h['tipo'];
        }
        $sel .= '<option' . ($h['nombre_habilidad'] === $r['habilidad'] ? ' selected' : '') . '>' . e($h['nombre_habilidad']) . '</option>';
    }
    $sel .= $grupo ? '</optgroup>' : '';
    $niv = '';
    foreach (NIVELES as $k => $t) {
        $niv .= '<option value="' . $k . '"' . ($k === $r['nivel'] ? ' selected' : '') . '>' . $t . '</option>';
    }
    return '<div class="fila-habilidad">'
        . '<div><label class="form-label" for="h' . $i . '">Habilidad</label><select class="form-select" id="h' . $i . '" name="req[' . $i . '][habilidad]">' . $sel . '</select></div>'
        . '<div><label class="form-label" for="n' . $i . '">Nivel mínimo</label><select class="form-select" id="n' . $i . '" name="req[' . $i . '][nivel]">' . $niv . '</select></div>'
        . '<fieldset><legend class="form-label">Requisito</legend><div class="d-flex gap-3" style="min-height:44px;align-items:center">'
        . '<div class="form-check mb-0"><input class="form-check-input" type="radio" name="req[' . $i . '][obligatorio]" id="o' . $i . 'a" value="1"' . ($r['obligatorio'] ? ' checked' : '') . '><label class="form-check-label" for="o' . $i . 'a">Obligatoria</label></div>'
        . '<div class="form-check mb-0"><input class="form-check-input" type="radio" name="req[' . $i . '][obligatorio]" id="o' . $i . 'b" value="0"' . (!$r['obligatorio'] ? ' checked' : '') . '><label class="form-check-label" for="o' . $i . 'b">Deseable</label></div>'
        . '</div></fieldset>'
        . '<button type="button" class="btn btn-terciario quitar-habilidad"><i class="bi bi-trash" aria-hidden="true"></i>Quitar</button>'
        . '</div>';
};
?>
<div class="encabezado-pagina">
    <div>
        <span class="etiqueta"><?= $p ? 'Editar proyecto · ' . e($id) : 'Nuevo proyecto' ?></span>
        <h1 class="titulo-izquierda"><?= e($p ? $p['titulo'] : 'Nuevo proyecto') ?></h1>
        <?php if ($p): ?><div class="mt-2 d-flex gap-2 align-items-center"><?= insignia('proyecto', $p['estado']) ?></div><?php endif; ?>
    </div>
</div>

<?php if ($devolucion): ?>
    <div class="alert alert-warning" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div class="alert-cuerpo"><span class="alert-titulo">Correcciones solicitadas por el Tec</span><?= nl2br(e($devolucion['justificacion'])) ?>
            <span class="d-block small text-mute mt-1"><?= e($devolucion['administrador']) ?> · <?= e(fecha_hora($devolucion['fecha'])) ?></span></div>
    </div>
<?php endif; ?>
<?php if ($errores): ?>
    <div class="alert alert-danger" role="alert"><i class="bi bi-x-octagon-fill" aria-hidden="true"></i>
        <div class="alert-cuerpo"><span class="alert-titulo"><?= isset($errores['_general']) ? 'No se pudo guardar' : 'Faltan datos' ?></span>
            <?= e($errores['_general'] ?? (!empty($faltan) ? 'Para enviar a revisión completa los campos marcados.' : 'Revisa los campos marcados.')) ?></div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-3 d-none d-lg-block">
        <nav aria-label="Secciones del formulario" style="position:sticky;top:80px">
            <ul class="mapa-pasos">
                <?php $n = 0; foreach ($pasos as $ancla => [$nombre, $campos]): $n++;
                    $con_error = array_intersect_key($errores, array_flip($campos)) || ($ancla === 's3' && isset($errores['req']));
                    $listo = !$con_error && $campos && !in_array('', array_map(fn ($k) => $d[$k], $campos), true); ?>
                    <li><a href="#<?= $ancla ?>"<?= $listo ? ' class="listo"' : '' ?>>
                        <span class="n"><?= $listo ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : $n ?></span><?= e($nombre) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <p class="leyenda-req mt-3"><span class="text-danger fw-bold">*</span> Obligatorio siempre<br>
                <span class="text-navy fw-bold">**</span> Obligatorio para enviar a revisión. Puedes guardar el borrador incompleto.</p>
        </nav>
    </div>
    <div class="col-lg-9">
        <form method="post" novalidate id="form-proyecto">
            <?= csrf_campo() ?>
            <section class="seccion-form" id="s1" aria-labelledby="s1-t">
                <h2 id="s1-t"><span class="num">1</span>Datos generales</h2>
                <?= campo('titulo', 'Título del proyecto', $d['titulo'], ['placeholder' => 'Ej. Sistema de control de inventarios para almacén', 'requerido' => true, 'maxlength' => 150, 'ayuda' => 'Máximo 150 caracteres.', 'error' => $err('titulo')]) ?>
            </section>

            <section class="seccion-form" id="s2" aria-labelledby="s2-t">
                <h2 id="s2-t"><span class="num">2</span>Descripción del proyecto</h2>
                <div class="d-grid gap-3">
                    <?= campo_area('descripcion', 'Descripción', $d['descripcion'], ['placeholder' => 'De qué trata el proyecto, en pocas líneas', 'maxlength' => 5000, 'req_envio' => true, 'filas' => 3, 'error' => $err('descripcion')]) ?>
                    <?= campo_area('problematica', 'Problemática', $d['problematica'], ['placeholder' => 'Qué problema tiene hoy la empresa', 'maxlength' => 5000, 'req_envio' => true, 'filas' => 3, 'error' => $err('problematica')]) ?>
                    <?= campo_area('objetivos', 'Objetivos', $d['objetivos'], ['placeholder' => 'Un objetivo por línea', 'maxlength' => 5000, 'req_envio' => true, 'filas' => 3, 'error' => $err('objetivos'), 'ayuda' => 'Escribe un objetivo por línea.']) ?>
                    <?= campo_area('justificacion', 'Justificación', $d['justificacion'], ['placeholder' => 'Por qué es importante resolverlo', 'maxlength' => 5000, 'req_envio' => true, 'filas' => 2, 'error' => $err('justificacion')]) ?>
                    <?= campo_area('alcance', 'Alcance', $d['alcance'], ['placeholder' => 'Qué incluye y qué no incluye el proyecto', 'maxlength' => 5000, 'req_envio' => true, 'filas' => 2, 'error' => $err('alcance')]) ?>
                </div>
            </section>

            <section class="seccion-form" id="s3" aria-labelledby="s3-t">
                <h2 id="s3-t"><span class="num">3</span>Requisitos</h2>
                <?= campo_area('requisitos_academicos', 'Requisitos académicos', $d['requisitos_academicos'], ['placeholder' => 'Ej. Alumnos de 7.° semestre en adelante', 'maxlength' => 5000, 'opcional' => true, 'filas' => 2]) ?>
                <h3 class="h5 mt-4 mb-0">Habilidades requeridas</h3>
                <p class="form-text mt-1 mb-2">Las obligatorias se usan para mostrar la coincidencia a los alumnos.</p>
                <?php if ($err('req')): ?><div class="invalid-feedback d-flex mb-2"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i><?= e($err('req')) ?></div><?php endif; ?>
                <div id="lista-habilidades">
                    <?php foreach (array_values($reqs) as $i => $r) echo $fila_hab($i, $r); ?>
                </div>
                <template id="plantilla-habilidad"><?= $fila_hab('__i__', ['habilidad' => '', 'nivel' => 'basico', 'obligatorio' => 1]) ?></template>
                <div class="pt-2 border-top">
                    <button type="button" class="btn btn-secundario btn-sm" id="agregar-habilidad"><i class="bi bi-plus-lg" aria-hidden="true"></i>Agregar habilidad</button>
                </div>
            </section>

            <section class="seccion-form" id="s4" aria-labelledby="s4-t">
                <h2 id="s4-t"><span class="num">4</span>Planeación</h2>
                <div class="row g-3">
                    <?= campo('duracion_estimada', 'Duración estimada (semanas)', $d['duracion_estimada'], ['placeholder' => 'Ej. 16', 'tipo' => 'number', 'min' => 1, 'max' => 260, 'req_envio' => true, 'error' => $err('duracion_estimada'), 'clase_contenedor' => 'col-md-6']) ?>
                    <?= campo_select('modalidad', 'Modalidad', $d['modalidad'], MODALIDADES, ['req_envio' => true, 'error' => $err('modalidad'), 'clase_contenedor' => 'col-md-6']) ?>
                    <?= campo('fecha_inicio', 'Fecha de inicio', $d['fecha_inicio'], ['tipo' => 'date', 'req_envio' => true, 'error' => $err('fecha_inicio'), 'clase_contenedor' => 'col-md-6']) ?>
                    <?= campo('fecha_fin', 'Fecha de fin', $d['fecha_fin'], ['tipo' => 'date', 'req_envio' => true, 'error' => $err('fecha_fin'), 'clase_contenedor' => 'col-md-6']) ?>
                    <?= campo_area('recursos', 'Recursos necesarios', $d['recursos'], ['placeholder' => 'Equipo, software, licencias o espacios que se requieren', 'maxlength' => 5000, 'opcional' => true, 'filas' => 2, 'clase_contenedor' => 'col-12']) ?>
                    <?= campo('cupo_alumnos', 'Cupo de alumnos', $d['cupo_alumnos'], ['placeholder' => 'Sin límite', 'tipo' => 'number', 'min' => 1, 'max' => 50, 'opcional' => true,
                        'ayuda' => 'Déjalo vacío para «Sin límite de cupo».', 'error' => $err('cupo_alumnos'), 'clase_contenedor' => 'col-md-6']) ?>
                </div>
            </section>

            <div class="barra-acciones-form">
                <a class="btn btn-terciario" href="<?= e(url($p ? 'mod_empresa/proyecto.php?id=' . urlencode($id) : 'mod_empresa/proyectos.php')) ?>">Cancelar</a>
                <button type="submit" name="accion" value="guardar" class="btn btn-secundario"><i class="bi bi-floppy" aria-hidden="true"></i>Guardar <?= ($p['estado'] ?? 'borrador') === 'devuelto' ? 'cambios' : 'borrador' ?></button>
                <button type="submit" name="accion" value="enviar" class="btn btn-principal"><i class="bi bi-send" aria-hidden="true"></i><?= ($p['estado'] ?? '') === 'devuelto' ? 'Reenviar a revisión' : 'Enviar a revisión' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
  const lista = document.getElementById('lista-habilidades');
  const plantilla = document.getElementById('plantilla-habilidad').innerHTML;
  let siguiente = lista.children.length;
  document.getElementById('agregar-habilidad').addEventListener('click', () => {
    lista.insertAdjacentHTML('beforeend', plantilla.replaceAll('__i__', 'n' + siguiente++));
    lista.lastElementChild.querySelector('select').focus();
  });
  lista.addEventListener('click', (ev) => {
    const b = ev.target.closest('.quitar-habilidad');
    if (b) b.closest('.fila-habilidad').remove();
  });
})();
</script>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
