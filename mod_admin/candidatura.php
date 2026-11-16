<?php
/**
 * Pantalla D10 · Decidir candidatura. Aprobar crea la autorización y la
 * asignación del alumno en una sola transacción; rechazar solo la autorización.
 */
require_once __DIR__ . '/../inc/inicio.php';
$admin = requiere_rol('administrador');
$pdo = conexion();

$id = (string) ($_GET['id'] ?? '');
$c = obtener_candidatura($id);
if (!$c) {
    error_http(404);
}
$p = obtener_proyecto($c['c_proyecto']);
$evaluaciones = evaluaciones_candidatura($id);
$ev = $evaluaciones[0] ?? null;
$vigentes = candidaturas_vigentes($c['matricula']);
$asignados = (int) $p['alumnos_asignados'];
$cupo_lleno = $p['cupo_alumnos'] && $asignados >= (int) $p['cupo_alumnos'];
$decidible = $c['estado'] === 'en_revision';
$proyecto_abierto = in_array($p['estado'], ['publicado', 'en_curso'], true);

$justificacion = '';
$errores = [];
if (es_post()) {
    verificar_csrf();
    $decision = ($_POST['decision'] ?? '') === 'aprobar' ? 'aprobar' : 'rechazar';
    $justificacion = trim(str_replace("\r\n", "\n", (string) ($_POST['justificacion'] ?? '')));
    if (!$decidible) {
        aviso('advertencia', 'Esta candidatura ya fue decidida.');
        redirigir('mod_admin/candidaturas.php');
    }
    if (mb_strlen($justificacion) < 10) {
        $errores['justificacion'] = 'Escribe la justificación (al menos 10 caracteres). La verá el alumno.';
    } elseif ($decision === 'aprobar' && (!$proyecto_abierto || $cupo_lleno)) {
        $errores['_general'] = $cupo_lleno ? 'El proyecto ya no tiene cupo.' : 'El proyecto ya no admite asignaciones.';
    }
    if (!$errores) {
        try {
            $pdo->beginTransaction();
            $s = $pdo->prepare("UPDATE solicitud_candidatura SET estado = ? WHERE c_candidatura = ? AND estado = 'en_revision'");
            $s->execute([$decision === 'aprobar' ? 'aprobada' : 'rechazada', $id]);
            if ($s->rowCount() !== 1) {
                throw new RuntimeException('ya_decidida');
            }
            $pdo->prepare('INSERT INTO autorizacion (c_autorizacion, num_empleado_admin, c_candidatura, decision, justificacion) VALUES (?, ?, ?, ?, ?)')
                ->execute([siguiente_folio('AUT'), $admin['clave'], $id, $decision === 'aprobar' ? 'aprobada' : 'rechazada', $justificacion]);
            if ($decision === 'aprobar') {
                // El trigger vuelve a revisar cupo y estado del proyecto (RN-07, RN-09).
                $pdo->prepare('INSERT INTO asignacion_alumno (c_asignacion_alumno, c_candidatura) VALUES (?, ?)')->execute([siguiente_folio('AAL'), $id]);
            }
            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log((string) $ex);
            $errores['_general'] = mensaje_error_bd($ex);
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            aviso('advertencia', 'Otro administrador ya decidió esta candidatura.');
            redirigir('mod_admin/candidaturas.php');
        }
        if (!$errores) {
            registrar_auditoria($decision === 'aprobar' ? 'asignar' : 'rechazar', 'solicitud_candidatura', $id);
            if ($decision === 'aprobar') {
                notificar($c['c_usuario_alumno'], '¡Fuiste asignado al proyecto «' . $c['titulo'] . '»!');
                notificar($c['c_usuario_empresa'], 'El Tec asignó a ' . $c['nombre'] . ' al proyecto «' . $c['titulo'] . '».');
                aviso('exito', 'Candidatura aprobada: ' . $c['nombre'] . ' quedó asignado al proyecto.');
            } else {
                notificar($c['c_usuario_alumno'], 'Tu candidatura a «' . $c['titulo'] . '» fue rechazada.');
                aviso('exito', 'Candidatura rechazada.');
            }
            redirigir('mod_admin/candidaturas.php');
        }
    }
}

$titulo = 'Decidir candidatura';
$menu_activo = 'mod_admin/candidaturas.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Revisión', null], ['Candidaturas por decidir', 'mod_admin/candidaturas.php'], [$c['nombre'], null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$sugerida = $ev ? ($ev['preseleccionado'] ? 'La empresa lo preseleccionó con ' . puntos($ev['puntuacion_total']) . '.' : 'Evaluado por la empresa con ' . puntos($ev['puntuacion_total']) . '.') : '';
?>
<div class="encabezado-pagina">
    <div>
        <span class="etiqueta">Decidir candidatura</span>
        <h1 class="titulo-izquierda"><?= e($c['nombre']) ?></h1>
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
            <?= insignia('candidatura', $c['estado']) ?><?= $ev && $ev['preseleccionado'] ? insignia_preseleccion() : '' ?>
            <span class="folio"><?= e($id) ?></span>
            <a class="small" href="<?= e(url('mod_admin/proyecto.php?id=' . urlencode($c['c_proyecto']))) ?>"><?= e($c['titulo']) ?></a>
        </div>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-8 d-grid gap-3 align-self-start">
        <?= html_perfil_candidato($c) ?>
        <?= html_comparativa(requisitos_proyecto($c['c_proyecto']), perfil_alumno_completo($c['matricula'])) ?>
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Evaluación de la empresa</h2></div>
            <div class="card-body"><?= $ev ? html_evaluacion($ev) : '<p class="text-mute mb-0">La empresa aún no evalúa esta candidatura.</p>' ?></div></div>
    </div>
    <div class="col-lg-4 d-grid gap-3 align-self-start">
        <div class="card"><div class="card-header"><h2 class="h3 mb-0">Contexto</h2></div>
            <div class="card-body d-grid gap-2">
                <?= $p['cupo_alumnos']
                    ? html_cupo("Cupo: $asignados de {$p['cupo_alumnos']} asignados", $asignados, (int) $p['cupo_alumnos'],
                        $cupo_lleno ? 'Sin lugares' : ((int) $p['cupo_alumnos'] - $asignados === 1 ? 'Queda 1 lugar' : 'Quedan ' . ((int) $p['cupo_alumnos'] - $asignados) . ' lugares'), 'bi-people')
                    : html_cupo("Alumnos asignados: $asignados", 0, 0, 'Sin límite de cupo', 'bi-people') ?>
                <?= html_cupo("Candidaturas del alumno: $vigentes de " . MAX_CANDIDATURAS, $vigentes, MAX_CANDIDATURAS, 'vigentes') ?>
            </div></div>
        <?php if ($decidible): ?>
        <div class="panel-decision">
            <div class="cab">Decisión</div>
            <form method="post" class="p-3 d-grid gap-3" novalidate>
                <?= csrf_campo() ?>
                <?= alerta_errores(isset($errores['_general']) ? $errores : []) ?>
                <?= campo_area('justificacion', 'Justificación', $justificacion ?: $sugerida, ['placeholder' => 'Motivo de la decisión (mínimo 10 caracteres)', 'minlength' => 10, 'maxlength' => 3000, 'requerido' => true, 'filas' => 4, 'error' => $errores['justificacion'] ?? null,
                    'ayuda' => 'Obligatoria. Quedará en el historial y la verá el alumno.']) ?>
                <div class="d-grid gap-2">
                    <?php if ($cupo_lleno || !$proyecto_abierto): ?>
                        <div class="accion-bloqueada"><button type="button" class="btn btn-aprobar" disabled aria-describedby="bloq"><i class="bi bi-check-circle" aria-hidden="true"></i>Aprobar y asignar</button>
                            <span class="motivo" id="bloq"><i class="bi bi-info-circle" aria-hidden="true"></i><?= $cupo_lleno ? 'El proyecto ya no tiene cupo.' : 'El proyecto ya no admite asignaciones.' ?></span></div>
                    <?php else: ?>
                        <button type="submit" name="decision" value="aprobar" class="btn btn-aprobar" data-confirmar="¿Aprobar la candidatura y asignar a <?= e($c['nombre']) ?> al proyecto? No se puede modificar."><i class="bi bi-check-circle" aria-hidden="true"></i>Aprobar y asignar</button>
                    <?php endif; ?>
                    <button type="submit" name="decision" value="rechazar" class="btn btn-rechazar" data-confirmar="¿Rechazar la candidatura de <?= e($c['nombre']) ?>? No se puede modificar."><i class="bi bi-x-circle" aria-hidden="true"></i>Rechazar</button>
                </div>
            </form>
        </div>
        <?php else: ?>
            <div class="card"><div class="card-body"><p class="mb-0">Esta candidatura está «<?= e(nombre_estado('candidatura', $c['estado'])) ?>».
                <?= $c['estado'] === 'pendiente' ? 'Se podrá decidir cuando la empresa la evalúe.' : '' ?></p></div></div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
