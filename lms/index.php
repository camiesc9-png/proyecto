<?php
/**
 * index.php
 * Dashboard principal: estadísticas generales y gráficas de barras (CSS puro).
 * El contenido se adapta según el rol del usuario autenticado.
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';
requerirSesion();

$pdo = obtenerConexion();
$rol = rolActual();
$tituloPagina = 'Dashboard';

// ==========================================================
// ESTADÍSTICAS GENERALES (visibles para docente/administrador)
// ==========================================================
$totalEstudiantes    = (int)$pdo->query('SELECT COUNT(*) c FROM estudiantes')->fetch()['c'];
$totalInstituciones  = (int)$pdo->query('SELECT COUNT(*) c FROM instituciones')->fetch()['c'];
$totalMaterias       = (int)$pdo->query('SELECT COUNT(*) c FROM materias')->fetch()['c'];
$totalVinculaciones  = (int)$pdo->query('SELECT COUNT(*) c FROM vinculaciones')->fetch()['c'];
$vincPendientes      = (int)$pdo->query("SELECT COUNT(*) c FROM vinculaciones WHERE estado='pendiente'")->fetch()['c'];
$vincCompletadas     = (int)$pdo->query("SELECT COUNT(*) c FROM vinculaciones WHERE estado='completada'")->fetch()['c'];

// Materias con más solicitudes de vinculación (para la gráfica de barras)
$materiasPopulares = $pdo->query('
    SELECT m.nombre, COUNT(v.id) AS total
    FROM materias m
    LEFT JOIN vinculaciones v ON v.materia_id = m.id
    GROUP BY m.id, m.nombre
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 6
')->fetchAll();
$maxMateria = 1;
foreach ($materiasPopulares as $m) { $maxMateria = max($maxMateria, (int)$m['total']); }

// Fortalezas más ofrecidas por estudiantes (para la gráfica de barras)
$fortalezasPopulares = $pdo->query('
    SELECT f.nombre, COUNT(ef.id) AS total
    FROM fortalezas f
    LEFT JOIN estudiante_fortalezas ef ON ef.fortaleza_id = f.id
    GROUP BY f.id, f.nombre
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 6
')->fetchAll();
$maxFortaleza = 1;
foreach ($fortalezasPopulares as $f) { $maxFortaleza = max($maxFortaleza, (int)$f['total']); }

// Últimas vinculaciones registradas en el sistema
$ultimasVinculaciones = $pdo->query('
    SELECT v.*, 
           CONCAT(es.nombre, " ", es.apellido) AS solicitante,
           CONCAT(eo.nombre, " ", eo.apellido) AS ofertante,
           m.nombre AS materia
    FROM vinculaciones v
    JOIN estudiantes es ON es.id = v.estudiante_solicitante_id
    JOIN estudiantes eo ON eo.id = v.estudiante_ofertante_id
    LEFT JOIN materias m ON m.id = v.materia_id
    ORDER BY v.fecha_solicitud DESC
    LIMIT 6
')->fetchAll();

// ==========================================================
// ESTADÍSTICAS PERSONALES (para el rol estudiante)
// ==========================================================
$misFortalezas = $misNecesidades = $misSolicitudesEnviadas = $misSolicitudesRecibidas = 0;
if ($rol === 'estudiante' && estudianteIdActual()) {
    $eid = estudianteIdActual();
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM estudiante_fortalezas WHERE estudiante_id = ?'); $stmt->execute([$eid]); $misFortalezas = (int)$stmt->fetch()['c'];
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM estudiante_necesidades WHERE estudiante_id = ?'); $stmt->execute([$eid]); $misNecesidades = (int)$stmt->fetch()['c'];
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM vinculaciones WHERE estudiante_solicitante_id = ?'); $stmt->execute([$eid]); $misSolicitudesEnviadas = (int)$stmt->fetch()['c'];
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM vinculaciones WHERE estudiante_ofertante_id = ?'); $stmt->execute([$eid]); $misSolicitudesRecibidas = (int)$stmt->fetch()['c'];
}

require __DIR__ . '/plantilla/encabezado.php';
?>

<?php if ($rol === 'estudiante'): ?>
    <div class="grilla-estadisticas">
        <div class="tarjeta-estadistica">
            <div class="icono-estadistica icono-morado"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
            <div><div class="valor-estadistica"><?= $misFortalezas ?></div><div class="etiqueta-estadistica">Mis fortalezas registradas</div></div>
        </div>
        <div class="tarjeta-estadistica">
            <div class="icono-estadistica icono-amarillo"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg></div>
            <div><div class="valor-estadistica"><?= $misNecesidades ?></div><div class="etiqueta-estadistica">Mis necesidades académicas</div></div>
        </div>
        <div class="tarjeta-estadistica">
            <div class="icono-estadistica icono-azul"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/></svg></div>
            <div><div class="valor-estadistica"><?= $misSolicitudesEnviadas ?></div><div class="etiqueta-estadistica">Solicitudes que envié</div></div>
        </div>
        <div class="tarjeta-estadistica">
            <div class="icono-estadistica icono-verde"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg></div>
            <div><div class="valor-estadistica"><?= $misSolicitudesRecibidas ?></div><div class="etiqueta-estadistica">Solicitudes que recibí</div></div>
        </div>
    </div>
    <div class="caja-info">
        👋 Bienvenido/a. Completa tu <a href="perfil.php" style="color:var(--color-primario);font-weight:700;">perfil académico</a> con tus fortalezas y necesidades, y usa <a href="buscar.php" style="color:var(--color-primario);font-weight:700;">Buscar compañeros</a> para encontrar estudiantes con quienes intercambiar conocimientos.
    </div>
<?php else: ?>
    <div class="grilla-estadisticas">
        <div class="tarjeta-estadistica">
            <div class="icono-estadistica icono-azul"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m5-5a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg></div>
            <div><div class="valor-estadistica"><?= $totalEstudiantes ?></div><div class="etiqueta-estadistica">Estudiantes registrados</div></div>
        </div>
        <div class="tarjeta-estadistica">
            <div class="icono-estadistica icono-morado"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/></svg></div>
            <div><div class="valor-estadistica"><?= $totalInstituciones ?></div><div class="etiqueta-estadistica">Instituciones colaborativas</div></div>
        </div>
        <div class="tarjeta-estadistica">
            <div class="icono-estadistica icono-amarillo"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/></svg></div>
            <div><div class="valor-estadistica"><?= $totalVinculaciones ?></div><div class="etiqueta-estadistica">Vinculaciones totales</div></div>
        </div>
        <div class="tarjeta-estadistica">
            <div class="icono-estadistica icono-verde"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg></div>
            <div><div class="valor-estadistica"><?= $vincCompletadas ?></div><div class="etiqueta-estadistica">Intercambios completados</div></div>
        </div>
    </div>

    <div class="fila-formulario" style="align-items:start;">
        <div class="tarjeta-grafica">
            <h2>📚 Materias con más solicitudes de vinculación</h2>
            <div class="grafica-barras">
                <?php if (empty($materiasPopulares)): ?>
                    <p class="sin-resultados">Aún no hay suficientes datos.</p>
                <?php endif; ?>
                <?php foreach ($materiasPopulares as $m): ?>
                    <div class="fila-barra">
                        <span class="etiqueta-barra"><?= e($m['nombre']) ?></span>
                        <div class="pista-barra"><div class="relleno-barra" style="width: <?= (int)round(($m['total'] / $maxMateria) * 100) ?>%"></div></div>
                        <span class="valor-barra"><?= (int)$m['total'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tarjeta-grafica">
            <h2>💡 Fortalezas más ofrecidas</h2>
            <div class="grafica-barras">
                <?php if (empty($fortalezasPopulares)): ?>
                    <p class="sin-resultados">Aún no hay suficientes datos.</p>
                <?php endif; ?>
                <?php foreach ($fortalezasPopulares as $f): ?>
                    <div class="fila-barra">
                        <span class="etiqueta-barra"><?= e($f['nombre']) ?></span>
                        <div class="pista-barra"><div class="relleno-barra" style="width: <?= (int)round(($f['total'] / $maxFortaleza) * 100) ?>%"></div></div>
                        <span class="valor-barra"><?= (int)$f['total'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="tarjeta">
        <div class="encabezado-tarjeta">
            <h2>🔄 Últimas vinculaciones registradas</h2>
            <span class="badge badge-amarillo"><?= $vincPendientes ?> pendientes</span>
        </div>
        <div class="contenedor-tabla">
            <table class="tabla-datos">
                <thead>
                    <tr><th>Solicitante</th><th>Ofertante</th><th>Materia</th><th>Estado</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimasVinculaciones)): ?>
                        <tr><td colspan="5" class="sin-resultados">Todavía no se registraron vinculaciones.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ultimasVinculaciones as $v): ?>
                        <tr>
                            <td><?= e($v['solicitante']) ?></td>
                            <td><?= e($v['ofertante']) ?></td>
                            <td><?= e($v['materia'] ?? '—') ?></td>
                            <td><span class="<?= claseBadgeEstado($v['estado']) ?>"><?= e($v['estado']) ?></span></td>
                            <td><?= formatearFecha($v['fecha_solicitud'], true) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/plantilla/pie.php'; ?>
