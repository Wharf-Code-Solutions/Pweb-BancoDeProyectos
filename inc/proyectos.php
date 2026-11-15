<?php
/**
 * Proyectos: consultas, reglas de estado y piezas de vista compartidas por
 * la empresa (E1–E3), el administrador (D5–D8) y, más adelante, alumnos y profesores.
 */

const MODALIDADES = ['presencial' => 'Presencial', 'remota' => 'Remota', 'hibrida' => 'Híbrida'];
const NIVELES = ['basico' => 'Básico', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'];

/** Campos que la base exige fuera de borrador (RN-02, ck_proyecto_completo). */
const CAMPOS_ENVIO = [
    'descripcion' => 'Descripción', 'problematica' => 'Problemática', 'objetivos' => 'Objetivos',
    'justificacion' => 'Justificación', 'alcance' => 'Alcance', 'duracion_estimada' => 'Duración estimada',
    'modalidad' => 'Modalidad', 'fecha_inicio' => 'Fecha de inicio', 'fecha_fin' => 'Fecha de fin',
];

/** Proyecto con datos de la empresa y del resumen (cupo, asignados, director), o null. */
function obtener_proyecto(string $c_proyecto): ?array
{
    $s = conexion()->prepare("SELECT p.*, e.razon_social, e.sector, e.c_usuario AS c_usuario_empresa,
            r.alumnos_asignados, r.candidaturas_en_proceso, r.num_empleado_director, ud.nombre AS director
        FROM proyecto p
        JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
        JOIN v_proyecto_resumen r ON r.c_proyecto = p.c_proyecto
        LEFT JOIN profesor pr ON pr.num_empleado = r.num_empleado_director
        LEFT JOIN usuario ud ON ud.c_usuario = pr.c_usuario
        WHERE p.c_proyecto = ?");
    $s->execute([$c_proyecto]);
    return $s->fetch() ?: null;
}

function requisitos_proyecto(string $c_proyecto): array
{
    $s = conexion()->prepare('SELECT r.nombre_habilidad, r.nivel_minimo, r.obligatorio, h.tipo
        FROM requisito_proyecto r JOIN habilidad h ON h.nombre_habilidad = r.nombre_habilidad
        WHERE r.c_proyecto = ? ORDER BY r.obligatorio DESC, h.tipo, r.nombre_habilidad');
    $s->execute([$c_proyecto]);
    return $s->fetchAll();
}

/** Decisiones del administrador sobre el proyecto, más recientes primero. */
function autorizaciones_proyecto(string $c_proyecto): array
{
    $s = conexion()->prepare('SELECT a.decision, a.fecha, a.justificacion, u.nombre AS administrador
        FROM autorizacion a
        JOIN administrador ad ON ad.num_empleado = a.num_empleado_admin
        JOIN usuario u ON u.c_usuario = ad.c_usuario
        WHERE a.c_proyecto = ? ORDER BY a.fecha DESC, a.c_autorizacion DESC');
    $s->execute([$c_proyecto]);
    return $s->fetchAll();
}

/** Última devolución (para mostrar las correcciones a la empresa), o null. */
function ultima_devolucion(string $c_proyecto): ?array
{
    foreach (autorizaciones_proyecto($c_proyecto) as $a) {
        if ($a['decision'] === 'devuelta') {
            return $a;
        }
    }
    return null;
}

/**
 * Línea de tiempo del proyecto: envíos (historial) y decisiones (autorizaciones),
 * en orden cronológico.
 */
function linea_tiempo_proyecto(string $c_proyecto): array
{
    $pdo = conexion();
    $s = $pdo->prepare("SELECT h.operacion, h.fecha, u.nombre, u.rol, e.razon_social
        FROM historial_auditoria h
        JOIN usuario u ON u.c_usuario = h.c_usuario
        LEFT JOIN empresa e ON e.c_usuario = u.c_usuario
        WHERE h.entidad_afectada = 'proyecto' AND h.registro_afectado = ? AND h.resultado = 'exito'
          AND h.operacion IN ('crear', 'enviar_revision', 'reenviar_revision', 'iniciar', 'finalizar', 'cancelar')");
    $s->execute([$c_proyecto]);
    $eventos = [];
    foreach ($s->fetchAll() as $h) {
        [$texto, $clase, $icono] = match ($h['operacion']) {
            'crear'             => ['Proyecto registrado como borrador', '', 'bi-pencil'],
            'enviar_revision'   => ['Enviado a revisión', '', 'bi-send'],
            'reenviar_revision' => ['Reenviado a revisión', '', 'bi-send'],
            'iniciar'           => ['Proyecto iniciado', 'exito', 'bi-play-fill'],
            'finalizar'         => ['Proyecto finalizado', 'exito', 'bi-flag'],
            'cancelar'          => ['Proyecto cancelado', 'peligro', 'bi-x-circle'],
        };
        $eventos[] = ['fecha' => $h['fecha'], 'texto' => $texto, 'clase' => $clase, 'icono' => $icono,
                      'quien' => $h['razon_social'] ?: $h['nombre'], 'justificacion' => null];
    }
    foreach (autorizaciones_proyecto($c_proyecto) as $a) {
        [$texto, $clase, $icono, $etiqueta] = match ($a['decision']) {
            'aprobada'  => ['Aprobado y publicado', 'exito', 'bi-check-lg', 'Comentario'],
            'devuelta'  => ['Devuelto para correcciones', 'advertencia', 'bi-arrow-return-left', 'Correcciones solicitadas'],
            'rechazada' => ['Rechazado', 'peligro', 'bi-x-lg', 'Motivo'],
        };
        $eventos[] = ['fecha' => $a['fecha'], 'texto' => $texto, 'clase' => $clase, 'icono' => $icono,
                      'quien' => $a['administrador'], 'justificacion' => [$etiqueta, $a['justificacion']]];
    }
    usort($eventos, fn ($x, $y) => strcmp($x['fecha'], $y['fecha']));
    return $eventos;
}

function html_linea_tiempo(array $eventos): string
{
    if (!$eventos) {
        return '<p class="text-mute small mb-0">Sin movimientos todavía.</p>';
    }
    $html = '<ol class="linea-tiempo">';
    foreach ($eventos as $ev) {
        $html .= '<li><span class="hito ' . $ev['clase'] . '"><i class="bi ' . $ev['icono'] . '" aria-hidden="true"></i></span>'
               . '<div class="evento">' . e($ev['texto']) . '</div>'
               . '<div class="cuando">' . e(fecha_hora($ev['fecha'])) . ' · ' . e($ev['quien']) . '</div>';
        if ($ev['justificacion']) {
            $html .= '<div class="justificacion ' . ($ev['clase'] === 'exito' ? '' : $ev['clase']) . '"><span class="quien">'
                   . e($ev['justificacion'][0]) . '</span>' . nl2br(e($ev['justificacion'][1])) . '</div>';
        }
        $html .= '</li>';
    }
    return $html . '</ol>';
}

/** "13 nov 2026, 18:42" */
function fecha_hora(string $fecha): string
{
    return fecha_corta($fecha) . ', ' . date('H:i', strtotime($fecha));
}

/** ¿Ya fue devuelto alguna vez? (para marcarlo como "Reenviado"). */
function es_reenviado(string $c_proyecto): bool
{
    $s = conexion()->prepare("SELECT COUNT(*) FROM autorizacion WHERE c_proyecto = ? AND decision = 'devuelta'");
    $s->execute([$c_proyecto]);
    return (int) $s->fetchColumn() > 0;
}

/** Bloque de contenido (descripción, objetivos, requisitos…) igual en E3, D6 y D8. */
function html_contenido_proyecto(array $p, array $requisitos): string
{
    $html = '<div class="bloque-texto">';
    $parrafo = function (string $titulo, ?string $texto) use (&$html): void {
        if ($texto !== null && trim($texto) !== '') {
            $html .= '<h3>' . e($titulo) . '</h3><p>' . nl2br(e($texto)) . '</p>';
        }
    };
    $parrafo('Descripción', $p['descripcion']);
    $parrafo('Problemática', $p['problematica']);
    if (trim((string) $p['objetivos']) !== '') {
        // Cada línea de los objetivos se muestra como un punto.
        $lineas = array_filter(array_map('trim', preg_split('/\R/', $p['objetivos'])));
        $html .= '<h3>Objetivos</h3>' . (count($lineas) > 1
            ? '<ul class="verificaciones">' . implode('', array_map(fn ($l) => '<li>' . e(ltrim($l, "-•* ")) . '</li>', $lineas)) . '</ul>'
            : '<p>' . e($p['objetivos']) . '</p>');
    }
    $parrafo('Justificación', $p['justificacion']);
    $parrafo('Alcance', $p['alcance']);
    $parrafo('Requisitos académicos', $p['requisitos_academicos']);
    if ($requisitos) {
        $html .= '<h3>Habilidades requeridas</h3><div class="d-flex flex-wrap gap-2">' . html_chips_requisitos($requisitos) . '</div>';
    }
    $parrafo('Recursos necesarios', $p['recursos']);
    if ($html === '<div class="bloque-texto">') {
        $html .= '<p class="text-mute mb-0">El proyecto aún no tiene descripción.</p>';
    }
    return $html . '</div>';
}

function html_chips_requisitos(array $requisitos): string
{
    $html = '';
    foreach ($requisitos as $r) {
        $nivel = '<span class="nivel">· ' . e(NIVELES[$r['nivel_minimo']] ?? $r['nivel_minimo']) . '</span>';
        $html .= $r['obligatorio']
            ? '<span class="chip obligatoria"><i class="bi bi-asterisk" aria-hidden="true"></i>' . e($r['nombre_habilidad']) . ' ' . $nivel . '<span class="visually-hidden"> (obligatoria)</span></span>'
            : '<span class="chip">' . e($r['nombre_habilidad']) . ' ' . $nivel . '</span>';
    }
    return $html;
}

/** Tarjeta "Resumen" (cupo, asignados, director, fechas, duración, modalidad). */
function html_resumen_proyecto(array $p): string
{
    $fila = fn (string $k, string $v) => '<div class="kv-fila"><span class="k">' . e($k) . '</span><span class="v">' . e($v) . '</span></div>';
    $asignados = (int) $p['alumnos_asignados'];
    return '<div class="card"><div class="card-header"><h2 class="h3 mb-0">Resumen</h2></div><div class="card-body">'
        . $fila('Cupo', $p['cupo_alumnos'] ? "$asignados de {$p['cupo_alumnos']} asignados" : 'Sin límite de cupo')
        . $fila('Alumnos asignados', (string) $asignados)
        . $fila('Director', $p['director'] ?? 'Sin director')
        . $fila('Inicio', $p['fecha_inicio'] ? fecha_corta($p['fecha_inicio']) : '—')
        . $fila('Fin', $p['fecha_fin'] ? fecha_corta($p['fecha_fin']) : '—')
        . $fila('Duración', $p['duracion_estimada'] ? $p['duracion_estimada'] . ' semanas' : '—')
        . $fila('Modalidad', MODALIDADES[$p['modalidad'] ?? ''] ?? '—')
        . '</div></div>';
}

/** Folio con botón de copiar. */
function html_folio(string $folio): string
{
    return '<span class="folio">' . e($folio) . '<button type="button" class="copiar" data-copiar="' . e($folio) . '" aria-label="Copiar ' . e($folio) . '"><i class="bi bi-copy" aria-hidden="true"></i></button></span>';
}

/**
 * Cierra un proyecto en curso o publicado (finalizar o cancelar) y, en la
 * misma transacción, cierra lo que dependía de él: asignaciones activas y
 * solicitudes que seguían abiertas.
 */
function cerrar_proyecto(string $c_proyecto, string $nuevo_estado): void
{
    $pdo = conexion();
    $cierre_asig = $nuevo_estado === 'finalizado' ? 'finalizada' : 'cancelada';
    $pdo->prepare("UPDATE proyecto SET estado = ? WHERE c_proyecto = ?")->execute([$nuevo_estado, $c_proyecto]);
    $pdo->prepare("UPDATE asignacion_alumno aa JOIN solicitud_candidatura sc ON sc.c_candidatura = aa.c_candidatura
                      SET aa.estado = ?, aa.fecha_cierre = CURDATE()
                    WHERE sc.c_proyecto = ? AND aa.estado = 'activa'")->execute([$cierre_asig, $c_proyecto]);
    $pdo->prepare("UPDATE asignacion_profesor ap JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion
                      SET ap.estado = ?, ap.fecha_cierre = CURDATE()
                    WHERE sd.c_proyecto = ? AND ap.estado = 'activa'")->execute([$cierre_asig, $c_proyecto]);
    $pdo->prepare("UPDATE solicitud_candidatura SET estado = 'cancelada'
                    WHERE c_proyecto = ? AND estado IN ('pendiente', 'en_revision')")->execute([$c_proyecto]);
    $pdo->prepare("UPDATE solicitud_direccion SET estado = 'cancelada'
                    WHERE c_proyecto = ? AND estado = 'pendiente'")->execute([$c_proyecto]);
}

/** Usuarios afectados por un proyecto (empresa, alumnos y director activos) para avisarles. */
function participantes_proyecto(string $c_proyecto): array
{
    $s = conexion()->prepare("SELECT e.c_usuario FROM proyecto p JOIN empresa e ON e.rfc_empresa = p.rfc_empresa WHERE p.c_proyecto = ?
        UNION SELECT a.c_usuario FROM solicitud_candidatura sc JOIN alumno a ON a.matricula = sc.matricula
               JOIN asignacion_alumno aa ON aa.c_candidatura = sc.c_candidatura
              WHERE sc.c_proyecto = ? AND aa.estado = 'activa'
        UNION SELECT pr.c_usuario FROM solicitud_direccion sd JOIN profesor pr ON pr.num_empleado = sd.num_empleado
               JOIN asignacion_profesor ap ON ap.c_solicitud_direccion = sd.c_solicitud_direccion
              WHERE sd.c_proyecto = ? AND ap.estado = 'activa'");
    $s->execute([$c_proyecto, $c_proyecto, $c_proyecto]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}
