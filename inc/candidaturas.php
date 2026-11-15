<?php
/**
 * Candidaturas: datos del candidato, evaluaciones de la empresa y piezas de
 * vista compartidas por E4/E5 (empresa) y D9/D10 (administrador).
 */

/** Candidatura con alumno, proyecto y empresa, o null. */
function obtener_candidatura(string $c_candidatura): ?array
{
    $s = conexion()->prepare('SELECT sc.*, al.carrera, al.semestre, al.datos_academicos, u.nombre, u.correo, u.c_usuario AS c_usuario_alumno,
            p.titulo, p.estado AS estado_proyecto, p.rfc_empresa, e.razon_social, e.c_usuario AS c_usuario_empresa
        FROM solicitud_candidatura sc
        JOIN alumno al ON al.matricula = sc.matricula JOIN usuario u ON u.c_usuario = al.c_usuario
        JOIN proyecto p ON p.c_proyecto = sc.c_proyecto JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
        WHERE sc.c_candidatura = ?');
    $s->execute([$c_candidatura]);
    return $s->fetch() ?: null;
}

/** Evaluaciones de una candidatura (más reciente primero), cada una con sus criterios. */
function evaluaciones_candidatura(string $c_candidatura): array
{
    $pdo = conexion();
    $s = $pdo->prepare('SELECT ev.*, u.nombre AS evaluador FROM evaluacion_candidato ev
        JOIN empresa e ON e.rfc_empresa = ev.rfc_empresa JOIN usuario u ON u.c_usuario = e.c_usuario
        WHERE ev.c_candidatura = ? ORDER BY ev.fecha DESC, ev.c_evaluacion DESC');
    $s->execute([$c_candidatura]);
    $lista = $s->fetchAll();
    $c = $pdo->prepare('SELECT ec.*, cr.peso FROM evaluacion_criterio ec LEFT JOIN criterio cr ON cr.nombre_criterio = ec.nombre_criterio
        WHERE ec.c_evaluacion = ? ORDER BY cr.peso DESC, ec.nombre_criterio');
    foreach ($lista as &$ev) {
        $c->execute([$ev['c_evaluacion']]);
        $ev['criterios'] = $c->fetchAll();
    }
    return $lista;
}

/** ¿Los pesos del catálogo de criterios suman 100? (sin eso no se puede evaluar) */
function criterios_listos(): bool
{
    $suma = (float) conexion()->query('SELECT COALESCE(SUM(peso), 0) FROM criterio')->fetchColumn();
    return abs($suma - 100) < 0.001;
}

/** Perfil del alumno con nivel, descripción y evidencia. */
function perfil_alumno_completo(string $matricula): array
{
    $s = conexion()->prepare('SELECT ph.*, h.tipo FROM perfil_habilidad ph JOIN habilidad h ON h.nombre_habilidad = ph.nombre_habilidad
        WHERE ph.matricula = ? ORDER BY h.tipo, ph.nombre_habilidad');
    $s->execute([$matricula]);
    return $s->fetchAll();
}

/** Número con dos decimales: 89.00 */
function puntos(float|string|null $n): string
{
    return $n === null ? '—' : number_format((float) $n, 2, '.', '');
}

function html_perfil_candidato(array $c): string
{
    $html = '<div class="card"><div class="card-header"><h2 class="h3 mb-0">Perfil del alumno</h2></div><div class="card-body">'
        . '<div class="d-flex gap-3 align-items-center mb-3"><span class="avatar" style="width:52px;height:52px;font-size:1.1rem">' . e(iniciales($c['nombre'])) . '</span>'
        . '<div><strong class="d-block fs-5 text-navy">' . e($c['nombre']) . '</strong><span class="folio">' . e($c['matricula']) . '</span> '
        . '<span class="small text-mute">· ' . e($c['carrera']) . ' · ' . (int) $c['semestre'] . '.° semestre</span></div></div>';
    $html .= $c['datos_academicos']
        ? '<p class="small mb-0"><strong>Datos académicos:</strong> ' . nl2br(e($c['datos_academicos'])) . '</p>'
        : '<p class="small text-mute mb-0">El alumno no registró datos académicos adicionales.</p>';
    return $html . '</div></div>';
}

/** Tabla requisito por requisito + otras habilidades + evidencias. */
function html_comparativa(array $requisitos, array $perfil): string
{
    $mapa = array_column($perfil, 'nivel', 'nombre_habilidad');
    $c = coincidencia($requisitos, $mapa);
    $html = '<div class="card"><div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h3 mb-0">Comparativa de habilidades</h2>'
          . str_replace('Cumples', 'Cumple', html_coincidencia($c)) . '</div><div class="card-body">';
    if ($requisitos) {
        $html .= '<div class="table-responsive"><table class="table tabla-datos mb-0"><thead><tr><th scope="col">Habilidad</th><th scope="col">Mínimo</th><th scope="col">Alumno</th><th scope="col">Resultado</th></tr></thead><tbody>';
        foreach ($requisitos as $r) {
            $tiene = $mapa[$r['nombre_habilidad']] ?? null;
            $res = $tiene === null ? '<span class="text-mute"><i class="bi bi-circle" aria-hidden="true"></i> No la tiene</span>'
                : (ORDEN_NIVEL[$tiene] >= ORDEN_NIVEL[$r['nivel_minimo']]
                    ? '<span class="fw-semibold text-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Cumple</span>'
                    : '<span class="fw-semibold text-warning"><i class="bi bi-dash-circle-fill" aria-hidden="true"></i> Nivel menor</span>');
            $html .= '<tr><td>' . e($r['nombre_habilidad']) . ($r['obligatorio'] ? ' <i class="bi bi-asterisk text-primary" aria-hidden="true"></i><span class="visually-hidden"> (obligatoria)</span>' : '') . '</td>'
                   . '<td>' . e(NIVELES[$r['nivel_minimo']]) . '</td><td>' . e($tiene ? NIVELES[$tiene] : '—') . '</td><td>' . $res . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    } else {
        $html .= '<p class="text-mute mb-0">El proyecto no pide habilidades específicas.</p>';
    }
    $pedidas = array_column($requisitos, 'nombre_habilidad');
    $otras = array_filter($perfil, fn ($h) => !in_array($h['nombre_habilidad'], $pedidas, true));
    if ($otras) {
        $html .= '<p class="small text-mute mt-3 mb-2">Otras habilidades del alumno</p><div class="d-flex flex-wrap gap-2">';
        foreach ($otras as $h) {
            $html .= '<span class="chip">' . e($h['nombre_habilidad']) . ' <span class="nivel">· ' . e(NIVELES[$h['nivel']]) . '</span></span>';
        }
        $html .= '</div>';
    }
    $evidencias = array_filter($perfil, fn ($h) => $h['evidencia']);
    if ($evidencias) {
        $enlaces = array_map(fn ($h) => '<a href="' . e($h['evidencia']) . '" target="_blank" rel="noopener noreferrer">' . e($h['nombre_habilidad']) . '</a>', $evidencias);
        $html .= '<p class="small mt-3 mb-0"><i class="bi bi-link-45deg" aria-hidden="true"></i> Evidencias: ' . implode(' · ', $enlaces) . '</p>';
    }
    return $html . '</div></div>';
}

/** Detalle de una evaluación (tabla por criterio + comentario general). */
function html_evaluacion(array $ev): string
{
    $html = '<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><span class="small text-mute">' . e($ev['evaluador']) . ' · ' . e(fecha_corta($ev['fecha'])) . '</span>'
          . '<span>Total: <span class="puntuacion">' . puntos($ev['puntuacion_total']) . '</span></span></div>'
          . '<div class="table-responsive"><table class="table tabla-datos mb-3"><thead><tr><th scope="col">Criterio</th><th scope="col">Peso</th><th scope="col">Calificación</th><th scope="col">Comentario</th></tr></thead><tbody>';
    foreach ($ev['criterios'] as $c) {
        $html .= '<tr><td>' . e($c['nombre_criterio']) . '</td><td>' . ($c['peso'] !== null ? rtrim(rtrim($c['peso'], '0'), '.') . ' %' : '—') . '</td>'
               . '<td>' . rtrim(rtrim($c['puntuacion'], '0'), '.') . '</td><td class="small">' . e($c['comentario'] ?? '') . '</td></tr>';
    }
    $html .= '</tbody></table></div>';
    if ($ev['comentarios']) {
        $html .= '<div class="justificacion"><span class="quien">Comentario general de la empresa</span>' . nl2br(e($ev['comentarios'])) . '</div>';
    }
    return $html;
}

function insignia_preseleccion(): string
{
    return '<span class="estado estado-preseleccion"><i class="bi bi-star-fill" aria-hidden="true"></i>Preseleccionada</span>';
}
