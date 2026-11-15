<?php
/**
 * Participación de alumnos y profesores: cargas (límite de 3), coincidencia
 * de habilidades y piezas de vista (indicador de cupo, chips, tarjeta de proyecto).
 */

const MAX_CANDIDATURAS = 3;   // RN-03
const MAX_DIRECCIONES = 3;    // RN-05
const ORDEN_NIVEL = ['basico' => 1, 'intermedio' => 2, 'avanzado' => 3];

/** Candidaturas vigentes del alumno (pendiente, en revisión o aprobada con asignación activa). */
function candidaturas_vigentes(string $matricula): int
{
    $s = conexion()->prepare('SELECT COUNT(*) FROM v_candidatura_vigente WHERE matricula = ?');
    $s->execute([$matricula]);
    return (int) $s->fetchColumn();
}

/** Proyectos que el profesor dirige con asignación activa. */
function direcciones_activas(string $num_empleado): int
{
    $s = conexion()->prepare('SELECT proyectos_activos FROM v_carga_profesor WHERE num_empleado = ?');
    $s->execute([$num_empleado]);
    return (int) $s->fetchColumn();
}

/** [habilidad => nivel] del alumno. */
function perfil_alumno(string $matricula): array
{
    $s = conexion()->prepare('SELECT nombre_habilidad, nivel FROM perfil_habilidad WHERE matricula = ?');
    $s->execute([$matricula]);
    return $s->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Compara lo que pide el proyecto con el perfil del alumno.
 * Devuelve ['chips' => html, 'obligatorias' => n, 'cumplidas' => n].
 */
function coincidencia(array $requisitos, array $perfil): array
{
    $html = '';
    $obligatorias = 0;
    $cumplidas = 0;
    foreach ($requisitos as $r) {
        $tiene = $perfil[$r['nombre_habilidad']] ?? null;
        $estado = $tiene === null ? 'falta'
            : (ORDEN_NIVEL[$tiene] >= ORDEN_NIVEL[$r['nivel_minimo']] ? 'cumple' : 'menor');
        if ($r['obligatorio']) {
            $obligatorias++;
            $cumplidas += $estado === 'cumple' ? 1 : 0;
        }
        [$icono, $nivel, $lector] = match ($estado) {
            'cumple' => ['bi-check-circle-fill', NIVELES[$r['nivel_minimo']], 'cumple'],
            'menor'  => ['bi-dash-circle-fill', 'tienes ' . NIVELES[$tiene], 'nivel menor'],
            'falta'  => ['bi-circle', 'no la tienes', 'no la tiene'],
        };
        $html .= '<span class="chip ' . $estado . '"><i class="bi ' . $icono . '" aria-hidden="true"></i>' . e($r['nombre_habilidad'])
               . ' <span class="nivel">· ' . e($nivel) . '</span><span class="visually-hidden">: ' . $lector . ($r['obligatorio'] ? ', obligatoria' : '') . '</span>'
               . ($r['obligatorio'] ? '<i class="bi bi-asterisk" aria-hidden="true"></i>' : '') . '</span>';
    }
    return ['chips' => $html, 'obligatorias' => $obligatorias, 'cumplidas' => $cumplidas];
}

function html_coincidencia(array $c): string
{
    if ($c['obligatorias'] === 0) {
        return '<span class="coincidencia ok"><i class="bi bi-check2-all" aria-hidden="true"></i>Sin habilidades obligatorias</span>';
    }
    $ok = $c['cumplidas'] === $c['obligatorias'];
    return '<span class="coincidencia ' . ($ok ? 'ok' : 'parcial') . '"><i class="bi ' . ($ok ? 'bi-check2-all' : 'bi-dash-circle') . '" aria-hidden="true"></i>'
         . "Cumples {$c['cumplidas']} de {$c['obligatorias']} obligatoria" . ($c['obligatorias'] > 1 ? 's' : '') . '</span>';
}

/** Indicador de cupo personal: "Candidaturas vigentes: 2 de 3 ●●○". */
function html_cupo(string $texto, int $usados, int $max, string $resto, string $icono = 'bi-send'): string
{
    $puntos = '';
    for ($i = 1; $i <= $max; $i++) {
        $puntos .= '<span' . ($i <= $usados ? ' class="usado"' : '') . '></span>';
    }
    return '<div class="cupo' . ($max > 0 && $usados >= $max ? ' lleno' : '') . '"><span class="cupo-icono"><i class="bi ' . $icono . '" aria-hidden="true"></i></span>'
         . '<span class="cupo-texto">' . e($texto) . "</span><span class=\"puntos\" aria-hidden=\"true\">$puntos</span>"
         . '<span class="cupo-resto">' . e($resto) . '</span></div>';
}

/** Rango de fechas corto: "19 ene – 8 may 2027". */
function rango_fechas(?string $inicio, ?string $fin): string
{
    if (!$inicio || !$fin) {
        return '—';
    }
    $a = fecha_corta($inicio);
    $b = fecha_corta($fin);
    if (substr($a, -4) === substr($b, -4)) {
        $a = substr($a, 0, -5);
    }
    return "$a – $b";
}

/**
 * Proyectos visibles para alumnos y profesores (publicados o en curso),
 * con filtros comunes. $extra agrega condiciones propias de cada rol.
 */
function proyectos_disponibles(array $filtros, array $extra = [], array $params_extra = []): array
{
    $donde = ["r.estado IN ('publicado', 'en_curso')"];
    $params = [];
    if ($filtros['q'] !== '') {
        $donde[] = '(r.titulo LIKE ? OR r.razon_social LIKE ? OR p.descripcion LIKE ? OR r.c_proyecto LIKE ?)';
        $like = '%' . addcslashes($filtros['q'], '%_\\') . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($filtros['modalidad'] !== '') {
        $donde[] = 'r.modalidad = ?';
        $params[] = $filtros['modalidad'];
    }
    if ($filtros['habilidad'] !== '') {
        $donde[] = 'EXISTS (SELECT 1 FROM requisito_proyecto rq WHERE rq.c_proyecto = r.c_proyecto AND rq.nombre_habilidad = ?)';
        $params[] = $filtros['habilidad'];
    }
    $s = conexion()->prepare('SELECT r.*, p.duracion_estimada, e.sector, ud.nombre AS director
        FROM v_proyecto_resumen r
        JOIN proyecto p ON p.c_proyecto = r.c_proyecto
        JOIN empresa e ON e.rfc_empresa = r.rfc_empresa
        LEFT JOIN profesor pr ON pr.num_empleado = r.num_empleado_director
        LEFT JOIN usuario ud ON ud.c_usuario = pr.c_usuario
        WHERE ' . implode(' AND ', array_merge($donde, $extra)) . '
        ORDER BY r.estado = \'publicado\' DESC, r.fecha_inicio, r.c_proyecto DESC');
    $s->execute(array_merge($params, $params_extra));
    return $s->fetchAll();
}

/** Tarjeta de proyecto (A3 y R2). $chips y $pie ya vienen en HTML. */
function html_tarjeta_proyecto(array $p, string $chips, string $pie): string
{
    $icono_mod = ['presencial' => 'bi-geo-alt', 'remota' => 'bi-wifi', 'hibrida' => 'bi-shuffle'][$p['modalidad']] ?? 'bi-geo-alt';
    $cupo = $p['cupo_alumnos'] ? 'Cupo: ' . (int) $p['alumnos_asignados'] . ' de ' . (int) $p['cupo_alumnos'] : 'Sin límite de cupo';
    return '<article class="tarjeta-proyecto"><div class="cabeza"><div><h3>' . e($p['titulo']) . '</h3>'
        . '<div class="empresa">' . e($p['razon_social']) . ' · ' . e($p['sector']) . '</div></div>' . insignia('proyecto', $p['estado']) . '</div>'
        . '<div class="datos"><span><i class="bi ' . $icono_mod . '" aria-hidden="true"></i>' . e(MODALIDADES[$p['modalidad']] ?? '') . '</span>'
        . '<span><i class="bi bi-hourglass-split" aria-hidden="true"></i>' . (int) $p['duracion_estimada'] . ' semanas</span>'
        . '<span><i class="bi bi-calendar-event" aria-hidden="true"></i>' . e(rango_fechas($p['fecha_inicio'], $p['fecha_fin'])) . '</span>'
        . '<span><i class="bi bi-people" aria-hidden="true"></i>' . e($cupo) . '</span></div>'
        . ($chips !== '' ? '<div class="chips">' . $chips . '</div>' : '')
        . '<div class="pie">' . $pie . '</div></article>';
}

/** Filtros comunes de A3 y R2 leídos de la URL. */
function filtros_proyectos(): array
{
    return [
        'q'         => limpiar($_GET['q'] ?? ''),
        'modalidad' => isset(MODALIDADES[$_GET['modalidad'] ?? '']) ? $_GET['modalidad'] : '',
        'habilidad' => limpiar($_GET['habilidad'] ?? ''),
    ];
}

/** Formulario de filtros de A3 y R2. $casilla = [nombre, texto, marcada]. */
function html_filtros_proyectos(array $filtros, array $casilla): string
{
    $habs = conexion()->query("SELECT nombre_habilidad FROM habilidad WHERE tipo = 'dura' ORDER BY nombre_habilidad")->fetchAll(PDO::FETCH_COLUMN);
    $html = '<form class="card mb-4" role="search" method="get"><div class="card-body d-flex flex-wrap gap-3 align-items-end">'
        . '<div class="flex-grow-1"><label class="form-label small mb-1" for="q">Buscar</label><input class="form-control" id="q" name="q" type="search" placeholder="Título, empresa o palabra clave" value="' . e($filtros['q']) . '"></div>'
        . '<div><label class="form-label small mb-1" for="modalidad">Modalidad</label><select class="form-select" id="modalidad" name="modalidad"><option value="">Todas</option>';
    foreach (MODALIDADES as $k => $t) {
        $html .= '<option value="' . $k . '"' . ($filtros['modalidad'] === $k ? ' selected' : '') . '>' . $t . '</option>';
    }
    $html .= '</select></div><div><label class="form-label small mb-1" for="habilidad">Habilidad</label><select class="form-select" id="habilidad" name="habilidad"><option value="">Todas</option>';
    foreach ($habs as $h) {
        $html .= '<option' . ($filtros['habilidad'] === $h ? ' selected' : '') . '>' . e($h) . '</option>';
    }
    [$nombre, $texto, $marcada] = $casilla;
    $html .= '</select></div><div class="pb-2"><input type="hidden" name="filtrado" value="1"><div class="form-check"><input class="form-check-input" type="checkbox" id="' . $nombre . '" name="' . $nombre . '" value="1"' . ($marcada ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="' . $nombre . '">' . e($texto) . '</label></div></div>'
        . '<button type="submit" class="btn btn-secundario"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</button></div></form>';
    return $html;
}

/** Botón deshabilitado con su motivo (accesible). */
function html_accion_bloqueada(string $texto, string $icono, string $motivo, string $id = 'motivo'): string
{
    return '<div class="accion-bloqueada"><button type="button" class="btn btn-principal" disabled aria-describedby="' . $id . '"><i class="bi ' . $icono . '" aria-hidden="true"></i>' . e($texto) . '</button>'
         . '<span class="motivo" id="' . $id . '"><i class="bi bi-info-circle" aria-hidden="true"></i>' . e($motivo) . '</span></div>';
}
