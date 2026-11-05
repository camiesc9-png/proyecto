<?php
/**
 * buscar.php
 * Permite buscar estudiantes según sus fortalezas, necesidades o materia de interés,
 * para iniciar una vinculación de intercambio de conocimientos.
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';
requerirSesion();

$pdo = obtenerConexion();
$tituloPagina = 'Buscar Compañeros';
$miEstudianteId = estudianteIdActual();

$busqueda = get('q');
$materiaFiltro = get('materia');
$fortalezaFiltro = get('fortaleza');

$materias = $pdo->query('SELECT id, nombre FROM materias ORDER BY nombre')->fetchAll();
$catalogoFortalezas = $pdo->query('SELECT id, nombre FROM fortalezas ORDER BY nombre')->fetchAll();

// ==========================================================
// BÚSQUEDA DE ESTUDIANTES QUE OFRECEN FORTALEZAS (posibles "profesores" pares)
// ==========================================================
$sql = "
    SELECT DISTINCT e.id, e.codigo, e.nombre, e.apellido, e.curso, e.biografia, i.nombre AS institucion_nombre
    FROM estudiantes e
    JOIN estudiante_fortalezas ef ON ef.estudiante_id = e.id
    LEFT JOIN materias m ON m.id = ef.materia_id
    LEFT JOIN fortalezas f ON f.id = ef.fortaleza_id
    LEFT JOIN instituciones i ON i.id = e.institucion_id
    WHERE 1=1
";
$parametros = [];
if ($miEstudianteId) {
    $sql .= ' AND e.id != ?';
    $parametros[] = $miEstudianteId;
}
if ($busqueda !== '') {
    $sql .= ' AND (e.nombre LIKE ? OR e.apellido LIKE ? OR f.nombre LIKE ? OR ef.descripcion LIKE ?)';
    $like = "%$busqueda%";
    array_push($parametros, $like, $like, $like, $like);
}
if ($materiaFiltro !== '') {
    $sql .= ' AND ef.materia_id = ?';
    $parametros[] = (int)$materiaFiltro;
}
if ($fortalezaFiltro !== '') {
    $sql .= ' AND ef.fortaleza_id = ?';
    $parametros[] = (int)$fortalezaFiltro;
}
$sql .= ' ORDER BY e.nombre LIMIT 60';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$resultados = $stmt->fetchAll();

// Cargar fortalezas y necesidades de cada estudiante encontrado (para las tarjetas)
$fortalezasPorEstudiante = [];
$necesidadesPorEstudiante = [];
if (!empty($resultados)) {
    $ids = array_column($resultados, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $stmtF = $pdo->prepare("SELECT ef.estudiante_id, f.nombre, ef.nivel FROM estudiante_fortalezas ef JOIN fortalezas f ON f.id = ef.fortaleza_id WHERE ef.estudiante_id IN ($marcadores)");
    $stmtF->execute($ids);
    foreach ($stmtF->fetchAll() as $fila) {
        $fortalezasPorEstudiante[$fila['estudiante_id']][] = $fila;
    }

    $stmtN = $pdo->prepare("SELECT en.estudiante_id, n.nombre, en.prioridad FROM estudiante_necesidades en JOIN necesidades_academicas n ON n.id = en.necesidad_id WHERE en.estudiante_id IN ($marcadores)");
    $stmtN->execute($ids);
    foreach ($stmtN->fetchAll() as $fila) {
        $necesidadesPorEstudiante[$fila['estudiante_id']][] = $fila;
    }
}

require __DIR__ . '/plantilla/encabezado.php';
?>

<div class="tarjeta">
    <div class="encabezado-tarjeta">
        <h2>🔍 Buscar compañeros para intercambio de conocimientos</h2>
    </div>
    <form method="GET" action="buscar.php" class="barra-filtros">
        <div class="campo-busqueda">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" name="q" placeholder="Buscar por nombre o habilidad..." value="<?= e($busqueda) ?>">
        </div>
        <select name="materia" onchange="this.form.submit()">
            <option value="">Todas las materias</option>
            <?php foreach ($materias as $m): ?>
                <option value="<?= (int)$m['id'] ?>" <?= $materiaFiltro === (string)$m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="fortaleza" onchange="this.form.submit()">
            <option value="">Todas las fortalezas</option>
            <?php foreach ($catalogoFortalezas as $f): ?>
                <option value="<?= (int)$f['id'] ?>" <?= $fortalezaFiltro === (string)$f['id'] ? 'selected' : '' ?>><?= e($f['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="boton boton-secundario">Buscar</button>
    </form>
</div>

<div class="grilla-perfiles">
    <?php if (empty($resultados)): ?>
        <div class="tarjeta"><p class="sin-resultados">No se encontraron estudiantes con esos criterios.</p></div>
    <?php endif; ?>
    <?php foreach ($resultados as $est): ?>
        <div class="tarjeta-perfil">
            <div class="cabecera-perfil">
                <div class="avatar-grande"><?= e(iniciales($est['nombre'] . ' ' . $est['apellido'])) ?></div>
                <div>
                    <h3><?= e($est['nombre'] . ' ' . $est['apellido']) ?></h3>
                    <small><?= e($est['curso']) ?> · <?= e($est['institucion_nombre'] ?? 'Sin institución') ?></small>
                </div>
            </div>
            <?php if (!empty($est['biografia'])): ?>
                <p style="font-size:12.5px;color:var(--texto-secundario);"><?= e(truncar($est['biografia'], 100)) ?></p>
            <?php endif; ?>

            <div class="seccion-perfil">
                <h4>Puede enseñar</h4>
                <div class="lista-chips">
                    <?php if (empty($fortalezasPorEstudiante[$est['id']])): ?>
                        <span style="font-size:12px;color:var(--texto-secundario);">Sin fortalezas registradas</span>
                    <?php endif; ?>
                    <?php foreach (($fortalezasPorEstudiante[$est['id']] ?? []) as $f): ?>
                        <span class="chip"><?= e($f['nombre']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="seccion-perfil">
                <h4>Desea aprender</h4>
                <div class="lista-chips">
                    <?php if (empty($necesidadesPorEstudiante[$est['id']])): ?>
                        <span style="font-size:12px;color:var(--texto-secundario);">Sin necesidades registradas</span>
                    <?php endif; ?>
                    <?php foreach (($necesidadesPorEstudiante[$est['id']] ?? []) as $n): ?>
                        <span class="chip chip-necesidad"><?= e($n['nombre']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (rolActual() === 'estudiante' && $miEstudianteId): ?>
                <div style="margin-top:14px;">
                    <a href="vinculaciones.php?accion=nuevo&ofertante_id=<?= (int)$est['id'] ?>" class="boton boton-primario boton-ancho">🤝 Solicitar vinculación</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/plantilla/pie.php'; ?>
