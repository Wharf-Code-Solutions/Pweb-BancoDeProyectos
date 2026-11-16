<?php
/** Pantalla A0 · Panel del alumno. */
require_once __DIR__ . '/../inc/inicio.php';
$usuario = requiere_rol('alumno');
$pdo = conexion();
$matricula = $usuario['clave'];

$vigentes = candidaturas_vigentes($matricula);
$s = $pdo->prepare("SELECT SUM(sc.estado = 'pendiente') AS pendientes, SUM(ev.preseleccionado = 1 AND sc.estado IN ('pendiente', 'en_revision')) AS preseleccionado
    FROM solicitud_candidatura sc LEFT JOIN evaluacion_candidato ev ON ev.c_candidatura = sc.c_candidatura WHERE sc.matricula = ?");
$s->execute([$matricula]);
$cuentas = array_map('intval', $s->fetch());

$s = $pdo->prepare("SELECT sc.c_proyecto, sc.fecha, sc.estado, p.titulo, e.razon_social, ev.preseleccionado
    FROM solicitud_candidatura sc JOIN proyecto p ON p.c_proyecto = sc.c_proyecto JOIN empresa e ON e.rfc_empresa = p.rfc_empresa
    LEFT JOIN evaluacion_candidato ev ON ev.c_candidatura = sc.c_candidatura
    WHERE sc.matricula = ? ORDER BY sc.fecha DESC LIMIT 4");
$s->execute([$matricula]);
$recientes = $s->fetchAll();

// Asignación activa: proyecto, empresa y director.
$s = $pdo->prepare("SELECT p.c_proyecto, p.titulo, p.estado, r.razon_social, ud.nombre AS director, p.fecha_inicio, p.fecha_fin
    FROM asignacion_alumno aa JOIN solicitud_candidatura sc ON sc.c_candidatura = aa.c_candidatura
    JOIN proyecto p ON p.c_proyecto = sc.c_proyecto JOIN v_proyecto_resumen r ON r.c_proyecto = p.c_proyecto
    LEFT JOIN profesor pr ON pr.num_empleado = r.num_empleado_director LEFT JOIN usuario ud ON ud.c_usuario = pr.c_usuario
    WHERE sc.matricula = ? AND aa.estado = 'activa'");
$s->execute([$matricula]);
$asignaciones = $s->fetchAll();

$s = $pdo->prepare('SELECT h.tipo, COUNT(*) FROM perfil_habilidad ph JOIN habilidad h ON h.nombre_habilidad = ph.nombre_habilidad WHERE ph.matricula = ? GROUP BY h.tipo');
$s->execute([$matricula]);
$por_tipo = $s->fetchAll(PDO::FETCH_KEY_PAIR);
$falta = match (true) {
    empty($por_tipo['dura']) && empty($por_tipo['blanda']) => 'Aún no registras habilidades.',
    empty($por_tipo['dura'])   => 'Te faltan habilidades técnicas.',
    empty($por_tipo['blanda']) => 'Te faltan habilidades socioemocionales.',
    default => null,
};

$titulo = 'Panel';
$menu_activo = 'mod_alumno/index.php';
$migas = [['Panel', null]];
require __DIR__ . '/../menumain/app_encabezado.php';
$fila = fn (string $k, string $v) => '<div class="kv-fila"><span class="k">' . e($k) . '</span><span class="v">' . e($v) . '</span></div>';
?>
<div class="encabezado-pagina">
    <div><h1 class="titulo-izquierda">Hola, <?= e(explode(' ', $usuario['nombre'])[0]) ?></h1></div>
    <div class="acciones"><a class="btn btn-principal" href="<?= e(url('mod_alumno/proyectos.php')) ?>">Ver proyectos disponibles</a></div>
</div>
<?php if ($falta): ?>
    <div class="alert alert-info" role="alert"><i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div class="alert-cuerpo"><a href="<?= e(url('mod_alumno/habilidades.php')) ?>" class="fw-bold">Completa tus habilidades</a> para que las empresas conozcan tu perfil. <?= e($falta) ?></div></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="indicador"><span class="rotulo">Candidaturas vigentes</span>
        <span class="valor"><?= $vigentes ?> <small>de <?= MAX_CANDIDATURAS ?></small></span>
        <div class="progress" role="progressbar" aria-label="Candidaturas vigentes" aria-valuenow="<?= $vigentes ?>" aria-valuemin="0" aria-valuemax="<?= MAX_CANDIDATURAS ?>">
            <div class="progress-bar" style="width:<?= round($vigentes / MAX_CANDIDATURAS * 100) ?>%"></div></div>
        <a class="ver" href="<?= e(url('mod_alumno/solicitudes.php')) ?>">Ver mis solicitudes </a></div></div>
    <div class="col-md-4"><div class="indicador"><span class="rotulo">Pendientes de evaluación</span><span class="valor"><?= $cuentas['pendientes'] ?></span>
        <a class="ver" href="<?= e(url('mod_alumno/solicitudes.php')) ?>">Ver </a></div></div>
    <div class="col-md-4"><div class="indicador"><span class="rotulo">Preseleccionado por la empresa</span><span class="valor"><?= $cuentas['preseleccionado'] ?></span>
        <a class="ver" href="<?= e(url('mod_alumno/solicitudes.php')) ?>">Ver </a></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2"><h2 class="h3 mb-0">Mis solicitudes recientes</h2>
                <a href="<?= e(url('mod_alumno/solicitudes.php')) ?>" class="small fw-semibold">Ver todas</a></div>
            <div class="card-body">
                <?php if (!$recientes): ?>
                    <p class="text-mute mb-0">Aún no te postulas a ningún proyecto.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recientes as $r): ?>
                            <div class="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2 align-items-center">
                                <div><a class="fw-bold d-block text-decoration-none" href="<?= e(url('mod_alumno/proyecto.php?id=' . urlencode($r['c_proyecto']))) ?>"><?= e($r['titulo']) ?></a>
                                    <span class="small text-mute"><?= e($r['razon_social']) ?> · <?= e(fecha_corta($r['fecha'])) ?></span></div>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <?php if ($r['preseleccionado']): ?><span class="etiqueta-dato">Preseleccionado</span><?php endif; ?>
                                    <?= insignia('candidatura', $r['estado']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h2 class="h3 mb-0">Asignación activa</h2></div>
            <div class="card-body">
                <?php if (!$asignaciones): ?>
                    <div class="vacio"><h3>Aún no tienes una asignación activa</h3>
                        <p>Cuando el Tec apruebe una de tus candidaturas, aquí verás el proyecto, la empresa y tu director.</p></div>
                <?php else: foreach ($asignaciones as $a): ?>
                    <a class="fw-bold d-block mb-2" href="<?= e(url('mod_alumno/proyecto.php?id=' . urlencode($a['c_proyecto']))) ?>"><?= e($a['titulo']) ?></a>
                    <?= $fila('Estado', nombre_estado('proyecto', $a['estado'])) ?><?= $fila('Empresa', $a['razon_social']) ?>
                    <?= $fila('Director', $a['director'] ?? 'Por asignar') ?><?= $fila('Fechas', rango_fechas($a['fecha_inicio'], $a['fecha_fin'])) ?>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../menumain/app_pie.php'; ?>
