<?php
/**
 * Pantalla D13 · Reportes (RF-41): proyectos, alumnos, profesores y empresas,
 * con filtros, impresión y exportación a CSV (?csv=1).
 */
require_once __DIR__ . '/../inc/inicio.php';
requiere_rol('administrador');
$pdo = conexion();

$pestanas = ['proyectos' => 'Proyectos', 'alumnos' => 'Alumnos', 'profesores' => 'Profesores', 'empresas' => 'Empresas'];
$pestana = isset($pestanas[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'proyectos';

// Periodos escolares: enero–junio y agosto–enero, según las fechas de inicio registradas.
$periodos = [];
$anios = $pdo->query('SELECT DISTINCT YEAR(COALESCE(fecha_inicio, fecha_registro)) FROM proyecto ORDER BY 1 DESC')->fetchAll(PDO::FETCH_COLUMN) ?: [date('Y')];
foreach ($anios as $a) {
    $a = (int) $a;
    $periodos["$a-2"] = ["ago $a – ene " . ($a + 1), "$a-07-01", ($a + 1) . '-01-31'];
    $periodos["$a-1"] = ["ene – jun $a", "$a-01-01", "$a-06-30"];
}
$filtros = [
    'tab'     => $pestana,
    'estado'  => isset(ESTADOS['proyecto'][$_GET['estado'] ?? '']) ? $_GET['estado'] : '',
    'sector'  => in_array($_GET['sector'] ?? '', SECTORES_EMPRESA, true) ? $_GET['sector'] : '',
    'periodo' => isset($periodos[$_GET['periodo'] ?? '']) ? $_GET['periodo'] : '',
];

// Condiciones sobre proyectos (alias r = v_proyecto_resumen, e = empresa). Los borradores no cuentan.
$donde = ["r.estado <> 'borrador'"];
$params = [];
if ($filtros['estado']) {
    $donde[] = 'r.estado = ?';
    $params[] = $filtros['estado'];
}
if ($filtros['sector']) {
    $donde[] = 'e.sector = ?';
    $params[] = $filtros['sector'];
}
if ($filtros['periodo']) {
    [, $desde, $hasta] = $periodos[$filtros['periodo']];
    $donde[] = 'COALESCE(r.fecha_inicio, DATE(p.fecha_registro)) BETWEEN ? AND ?';
    array_push($params, $desde, $hasta);
}
$where = implode(' AND ', $donde);
$base = "FROM v_proyecto_resumen r JOIN proyecto p ON p.c_proyecto = r.c_proyecto JOIN empresa e ON e.rfc_empresa = r.rfc_empresa";
$consulta = function (string $sql, array $p = []) use ($pdo): array {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return $s->fetchAll();
};

// Cada pestaña define encabezados y filas (texto plano: sirven para la tabla y para el CSV).
switch ($pestana) {
    case 'proyectos':
        $encabezados = ['Folio', 'Proyecto', 'Empresa', 'Sector', 'Estado', 'Cupo', 'Asignados', 'Director', 'En proceso'];
        $datos = $consulta("SELECT r.c_proyecto, r.titulo, r.razon_social, e.sector, r.estado, r.cupo_alumnos, r.alumnos_asignados, ud.nombre AS director, r.candidaturas_en_proceso
            $base LEFT JOIN profesor pr ON pr.num_empleado = r.num_empleado_director LEFT JOIN usuario ud ON ud.c_usuario = pr.c_usuario
            WHERE $where ORDER BY FIELD(r.estado, 'en_curso', 'publicado', 'en_revision', 'devuelto', 'finalizado', 'rechazado', 'cancelado'), r.titulo", $params);
        $filas = array_map(fn ($x) => [$x['c_proyecto'], $x['titulo'], $x['razon_social'], $x['sector'], ['estado', 'proyecto', $x['estado']],
            $x['cupo_alumnos'] ?? 'Sin límite', $x['alumnos_asignados'], $x['director'] ?? '—', $x['candidaturas_en_proceso']], $datos);
        $cuenta = array_count_values(array_column($datos, 'estado'));
        $indicadores = ['Publicados' => $cuenta['publicado'] ?? 0, 'En curso' => $cuenta['en_curso'] ?? 0, 'Finalizados' => $cuenta['finalizado'] ?? 0,
                        'Alumnos asignados' => array_sum(array_map('intval', array_column($datos, 'alumnos_asignados')))];
        break;

    case 'alumnos':
        $encabezados = ['Matrícula', 'Alumno', 'Carrera', 'Semestre', 'Candidaturas vigentes', 'Candidaturas totales', 'Asignación activa'];
        // Alumnos que se postularon a proyectos que cumplen los filtros (o todos, sin filtros).
        $sql = "SELECT a.matricula, u.nombre, a.carrera, a.semestre, ca.candidaturas_vigentes,
                (SELECT COUNT(*) FROM solicitud_candidatura sc WHERE sc.matricula = a.matricula) AS totales,
                (SELECT GROUP_CONCAT(p.titulo SEPARATOR ', ') FROM asignacion_alumno aa JOIN solicitud_candidatura sc ON sc.c_candidatura = aa.c_candidatura
                  JOIN proyecto p ON p.c_proyecto = sc.c_proyecto WHERE sc.matricula = a.matricula AND aa.estado = 'activa') AS asignacion
            FROM alumno a JOIN usuario u ON u.c_usuario = a.c_usuario JOIN v_carga_alumno ca ON ca.matricula = a.matricula";
        $hay_filtro = $filtros['estado'] || $filtros['sector'] || $filtros['periodo'];
        $datos = $hay_filtro
            ? $consulta("$sql WHERE EXISTS (SELECT 1 FROM solicitud_candidatura sc JOIN v_proyecto_resumen r ON r.c_proyecto = sc.c_proyecto
                JOIN proyecto p ON p.c_proyecto = r.c_proyecto JOIN empresa e ON e.rfc_empresa = r.rfc_empresa WHERE sc.matricula = a.matricula AND $where) ORDER BY u.nombre", $params)
            : $consulta("$sql ORDER BY u.nombre");
        $filas = array_map(fn ($x) => [$x['matricula'], $x['nombre'], $x['carrera'], $x['semestre'], $x['candidaturas_vigentes'] . ' de ' . MAX_CANDIDATURAS, $x['totales'], $x['asignacion'] ?? '—'], $datos);
        $indicadores = ['Alumnos' => count($datos), 'Con asignación activa' => count(array_filter($datos, fn ($x) => $x['asignacion'])),
                        'Sin candidaturas vigentes' => count(array_filter($datos, fn ($x) => !(int) $x['candidaturas_vigentes'])),
                        'Con 3 vigentes' => count(array_filter($datos, fn ($x) => (int) $x['candidaturas_vigentes'] >= MAX_CANDIDATURAS))];
        break;

    case 'profesores':
        $encabezados = ['Núm. de empleado', 'Profesor', 'Especialidad', 'Proyectos activos', 'Disponibles', 'Solicitudes pendientes', 'Proyectos dirigidos (total)'];
        $datos = $consulta("SELECT vc.*, (SELECT COUNT(*) FROM solicitud_direccion sd WHERE sd.num_empleado = vc.num_empleado AND sd.estado = 'pendiente') AS pendientes,
                (SELECT COUNT(*) FROM asignacion_profesor ap JOIN solicitud_direccion sd ON sd.c_solicitud_direccion = ap.c_solicitud_direccion WHERE sd.num_empleado = vc.num_empleado) AS total
            FROM v_carga_profesor vc ORDER BY vc.proyectos_activos DESC, vc.nombre");
        $filas = array_map(fn ($x) => [$x['num_empleado'], $x['nombre'], $x['especialidad'], $x['proyectos_activos'] . ' de ' . MAX_DIRECCIONES, $x['proyectos_disponibles'], $x['pendientes'], $x['total']], $datos);
        $indicadores = ['Profesores' => count($datos), 'Dirigiendo' => count(array_filter($datos, fn ($x) => (int) $x['proyectos_activos'] > 0)),
                        'Con carga completa' => count(array_filter($datos, fn ($x) => (int) $x['proyectos_disponibles'] <= 0)),
                        'Solicitudes pendientes' => array_sum(array_map('intval', array_column($datos, 'pendientes')))];
        break;

    default: // empresas
        $encabezados = ['RFC', 'Empresa', 'Sector', 'Proyectos', 'Publicados', 'En curso', 'Finalizados', 'Alumnos asignados'];
        $datos = $consulta("SELECT e.rfc_empresa, e.razon_social, e.sector, COUNT(r.c_proyecto) AS total,
                SUM(r.estado = 'publicado') AS publicados, SUM(r.estado = 'en_curso') AS en_curso, SUM(r.estado = 'finalizado') AS finalizados,
                SUM(r.alumnos_asignados) AS asignados
            $base WHERE $where GROUP BY e.rfc_empresa, e.razon_social, e.sector ORDER BY total DESC, e.razon_social", $params);
        $filas = array_map(fn ($x) => [$x['rfc_empresa'], $x['razon_social'], $x['sector'], $x['total'], (int) $x['publicados'], (int) $x['en_curso'], (int) $x['finalizados'], (int) $x['asignados']], $datos);
        $indicadores = ['Empresas con proyectos' => count($datos), 'Proyectos' => array_sum(array_map('intval', array_column($datos, 'total'))),
                        'En curso' => array_sum(array_map('intval', array_column($datos, 'en_curso'))),
                        'Alumnos asignados' => array_sum(array_map('intval', array_column($datos, 'asignados')))];
}

$texto_celda = fn ($v) => is_array($v) ? nombre_estado($v[1], $v[2]) : (string) $v;

// Exportación a CSV (UTF-8 con BOM para que Excel respete los acentos).
if (($_GET['csv'] ?? '') === '1') {
    registrar_auditoria('exportar', 'reporte', $pestana);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_' . $pestana . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $encabezados);
    foreach ($filas as $f) {
        // Evita que una celda que empieza con = + - @ se interprete como fórmula en Excel.
        fputcsv($out, array_map(fn ($v) => preg_replace('/^([=+\-@])/', "'$1", $texto_celda($v)), $f));
    }
    exit;
}

$titulo = 'Reportes';
$menu_activo = 'mod_admin/reportes.php';
$migas = [['Panel', 'mod_admin/index.php'], ['Consulta', null], ['Reportes', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$sin_tab = array_diff_key($filtros, ['tab' => 1]);
?>
<div class="encabezado-pagina">
    <div><span class="etiqueta">Consulta</span><h1 class="titulo-izquierda">Reportes</h1></div>
    <div class="acciones">
        <a class="btn btn-secundario" href="<?= e(url_lista('mod_admin/reportes.php', $filtros + ['csv' => '1'])) ?>"><i class="bi bi-filetype-csv" aria-hidden="true"></i>Exportar CSV</a>
        <button type="button" class="btn btn-principal" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i>Imprimir</button>
    </div>
</div>
<form class="card mb-3 filtros" role="search" method="get">
    <div class="card-body d-flex flex-wrap gap-3 align-items-end">
        <input type="hidden" name="tab" value="<?= e($pestana) ?>">
        <div><label class="form-label small mb-1" for="estado">Estado del proyecto</label>
            <select class="form-select" id="estado" name="estado"><option value="">Todos</option>
                <?php foreach (ESTADOS['proyecto'] as $k => [$t]): if ($k === 'borrador') continue; ?><option value="<?= $k ?>"<?= $filtros['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label small mb-1" for="sector">Sector</label>
            <select class="form-select" id="sector" name="sector"><option value="">Todos</option>
                <?php foreach (SECTORES_EMPRESA as $sec): ?><option<?= $filtros['sector'] === $sec ? ' selected' : '' ?>><?= e($sec) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label small mb-1" for="periodo">Periodo (inicio del proyecto)</label>
            <select class="form-select" id="periodo" name="periodo"><option value="">Todos</option>
                <?php foreach ($periodos as $k => [$t]): ?><option value="<?= $k ?>"<?= $filtros['periodo'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <button type="submit" class="btn btn-secundario"><i class="bi bi-funnel" aria-hidden="true"></i>Aplicar</button>
        <?php if (array_filter($sin_tab)): ?><a class="btn btn-terciario" href="<?= e(url('mod_admin/reportes.php?tab=' . $pestana)) ?>">Limpiar</a><?php endif; ?>
    </div>
</form>
<?php if ($pestana === 'profesores' && array_filter($sin_tab)): ?>
    <p class="small text-mute">El reporte de profesores muestra la carga actual y no usa los filtros de proyecto.</p>
<?php endif; ?>
<ul class="nav nav-tabs mb-0">
    <?php foreach ($pestanas as $t => $nombre): ?>
        <li class="nav-item"><a class="nav-link<?= $pestana === $t ? ' active" aria-current="page' : '' ?>" href="<?= e(url_lista('mod_admin/reportes.php', ['tab' => $t] + $sin_tab)) ?>"><?= e($nombre) ?></a></li>
    <?php endforeach; ?>
</ul>
<div class="bg-white border border-top-0 p-3" style="border-radius:0 0 8px 8px">
    <div class="row g-3 mb-4">
        <?php foreach ($indicadores as $rotulo => $valor): ?>
            <div class="col-6 col-lg-3"><div class="indicador"><span class="rotulo"><?= e($rotulo) ?></span><span class="valor"><?= (int) $valor ?></span></div></div>
        <?php endforeach; ?>
    </div>
    <div class="tabla-marco">
        <?php if (!$filas): ?>
            <p class="p-3 mb-0 text-mute">No hay datos para estos filtros.</p>
        <?php else: ?>
        <div class="table-responsive"><table class="table tabla-datos table-striped mb-0">
            <thead><tr><?php foreach ($encabezados as $h): ?><th scope="col"><?= e($h) ?></th><?php endforeach; ?></tr></thead>
            <tbody><?php foreach ($filas as $f): ?>
                <tr><?php foreach ($f as $v): ?><td><?= is_array($v) ? insignia($v[1], $v[2]) : e((string) $v) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
        <div class="pie-tabla"><span><?= $filtros['periodo'] ? 'Periodo: ' . e($periodos[$filtros['periodo']][0]) . ' · ' : '' ?><?= count($filas) ?> registro<?= count($filas) === 1 ? '' : 's' ?> · generado el <?= e(fecha_hora(date('Y-m-d H:i:s'))) ?></span></div>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
