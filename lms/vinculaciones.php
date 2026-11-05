<?php
/**
 * vinculaciones.php
 * Núcleo del sistema: gestiona las solicitudes de vinculación entre estudiantes
 * para el intercambio de conocimientos (una fortaleza de uno cubre una necesidad de otro).
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';
requerirSesion();

$pdo = obtenerConexion();
$tituloPagina = 'Vinculaciones';
$rol = rolActual();
$miEstudianteId = estudianteIdActual();
$accion = get('accion', 'listar');

// ==========================================================
// CREAR NUEVA SOLICITUD DE VINCULACIÓN
// ==========================================================
if ($accion === 'nuevo') {
    requerirRol(['estudiante']);
    $ofertanteId = (int)get('ofertante_id');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!tokenCSRFValido()) { flashError('Token de seguridad inválido.'); redirigir('vinculaciones.php'); }
        $ofertanteId = (int)post('ofertante_id');
        $materiaId = post('materia_id') !== '' ? (int)post('materia_id') : null;
        $fortalezaId = post('fortaleza_id') !== '' ? (int)post('fortaleza_id') : null;
        $necesidadId = post('necesidad_id') !== '' ? (int)post('necesidad_id') : null;
        $mensaje = post('mensaje');

        if ($ofertanteId <= 0 || $ofertanteId === $miEstudianteId) {
            flashError('Debe seleccionar un compañero válido para vincularse.');
            redirigir('vinculaciones.php');
        }

        $stmt = $pdo->prepare('INSERT INTO vinculaciones (estudiante_solicitante_id, estudiante_ofertante_id, materia_id, fortaleza_id, necesidad_id, mensaje) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$miEstudianteId, $ofertanteId, $materiaId, $fortalezaId, $necesidadId, $mensaje]);

        flashExito('Solicitud de vinculación enviada correctamente.');
        redirigir('vinculaciones.php');
    }

    // Datos del estudiante ofertante seleccionado, sus fortalezas
    $stmt = $pdo->prepare('SELECT e.*, i.nombre AS institucion_nombre FROM estudiantes e LEFT JOIN instituciones i ON i.id = e.institucion_id WHERE e.id = ?');
    $stmt->execute([$ofertanteId]);
    $ofertante = $stmt->fetch();
    if (!$ofertante) { flashError('El estudiante seleccionado no existe.'); redirigir('buscar.php'); }

    $stmt = $pdo->prepare('SELECT ef.id, ef.materia_id, ef.nivel, f.id AS fortaleza_id, f.nombre AS fortaleza_nombre, m.nombre AS materia_nombre FROM estudiante_fortalezas ef JOIN fortalezas f ON f.id = ef.fortaleza_id LEFT JOIN materias m ON m.id = ef.materia_id WHERE ef.estudiante_id = ?');
    $stmt->execute([$ofertanteId]);
    $fortalezasOfertante = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT en.id, n.id AS necesidad_id, n.nombre FROM estudiante_necesidades en JOIN necesidades_academicas n ON n.id = en.necesidad_id WHERE en.estudiante_id = ?');
    $stmt->execute([$miEstudianteId]);
    $misNecesidades = $stmt->fetchAll();

    $materias = $pdo->query('SELECT id, nombre FROM materias ORDER BY nombre')->fetchAll();

    require __DIR__ . '/plantilla/encabezado.php';
    ?>
    <div class="tarjeta" style="max-width:650px;">
        <div class="encabezado-tarjeta">
            <h2>🤝 Solicitar vinculación con <?= e($ofertante['nombre'] . ' ' . $ofertante['apellido']) ?></h2>
            <a href="buscar.php" class="boton boton-secundario">← Volver</a>
        </div>
        <div class="caja-info"><strong><?= e($ofertante['curso']) ?></strong> · <?= e($ofertante['institucion_nombre'] ?? 'Sin institución') ?></div>

        <?php if (empty($fortalezasOfertante)): ?>
            <p class="sin-resultados">Este estudiante todavía no registró fortalezas para enseñar.</p>
        <?php else: ?>
        <form method="POST" action="vinculaciones.php?accion=nuevo" class="formulario">
            <?= campoCSRF() ?>
            <input type="hidden" name="ofertante_id" value="<?= (int)$ofertanteId ?>">

            <label for="fortaleza_id">¿Qué fortaleza de este compañero te interesa? *</label>
            <select id="fortaleza_id" name="fortaleza_id" required onchange="const opt=this.options[this.selectedIndex]; document.getElementById('materia_id').value = opt.dataset.materia || '';">
                <option value="">Seleccione...</option>
                <?php foreach ($fortalezasOfertante as $f): ?>
                    <option value="<?= (int)$f['fortaleza_id'] ?>" data-materia="<?= (int)($f['materia_id'] ?? 0) ?>">
                        <?= e($f['fortaleza_nombre']) ?><?= $f['materia_nombre'] ? ' — ' . e($f['materia_nombre']) : '' ?> (<?= e($f['nivel']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="materia_id">Materia relacionada</label>
            <select id="materia_id" name="materia_id">
                <option value="">— No aplica —</option>
                <?php foreach ($materias as $m): ?>
                    <option value="<?= (int)$m['id'] ?>"><?= e($m['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="necesidad_id">¿Cuál de tus necesidades académicas cubre esta vinculación?</label>
            <select id="necesidad_id" name="necesidad_id">
                <option value="">— No aplica —</option>
                <?php foreach ($misNecesidades as $n): ?>
                    <option value="<?= (int)$n['necesidad_id'] ?>"><?= e($n['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($misNecesidades)): ?>
                <p class="texto-ayuda">No tienes necesidades registradas aún. Puedes <a href="perfil.php" style="color:var(--color-primario);">agregarlas aquí</a>.</p>
            <?php endif; ?>

            <label for="mensaje">Mensaje para tu compañero *</label>
            <textarea id="mensaje" name="mensaje" required maxlength="500" placeholder="Ej. Hola, ¿podrías ayudarme con ecuaciones de segundo grado?"></textarea>

            <div class="acciones-formulario">
                <button type="submit" class="boton boton-primario">📨 Enviar solicitud</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php
    require __DIR__ . '/plantilla/pie.php';
    exit;
}

// ==========================================================
// RESPONDER SOLICITUD (aceptar / rechazar) — solo el ofertante
// ==========================================================
if ($accion === 'responder') {
    requerirRol(['estudiante']);
    $vinculacionId = (int)get('id');
    $respuesta = get('respuesta'); // aceptada | rechazada

    if (!in_array($respuesta, ['aceptada', 'rechazada'], true)) {
        flashError('Respuesta no válida.');
        redirigir('vinculaciones.php');
    }
    $stmt = $pdo->prepare('SELECT * FROM vinculaciones WHERE id = ? AND estudiante_ofertante_id = ?');
    $stmt->execute([$vinculacionId, $miEstudianteId]);
    $vinc = $stmt->fetch();
    if (!$vinc) {
        flashError('No tiene permiso para responder esta solicitud.');
        redirigir('vinculaciones.php');
    }
    $stmt = $pdo->prepare('UPDATE vinculaciones SET estado = ?, fecha_respuesta = NOW() WHERE id = ?');
    $stmt->execute([$respuesta, $vinculacionId]);
    flashExito($respuesta === 'aceptada' ? 'Vinculación aceptada. ¡Coordinen el intercambio!' : 'Vinculación rechazada.');
    redirigir('vinculaciones.php');
}

// ==========================================================
// COMPLETAR Y CALIFICAR — solo el solicitante, si estaba aceptada
// ==========================================================
if ($accion === 'completar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requerirRol(['estudiante']);
    if (!tokenCSRFValido()) { flashError('Token de seguridad inválido.'); redirigir('vinculaciones.php'); }
    $vinculacionId = (int)post('id');
    $calificacion = max(1, min(5, (int)post('calificacion')));

    $stmt = $pdo->prepare("SELECT * FROM vinculaciones WHERE id = ? AND estudiante_solicitante_id = ? AND estado = 'aceptada'");
    $stmt->execute([$vinculacionId, $miEstudianteId]);
    if (!$stmt->fetch()) {
        flashError('No se puede completar esta vinculación.');
        redirigir('vinculaciones.php');
    }
    $stmt = $pdo->prepare("UPDATE vinculaciones SET estado = 'completada', calificacion = ? WHERE id = ?");
    $stmt->execute([$calificacion, $vinculacionId]);
    flashExito('¡Intercambio marcado como completado! Gracias por calificar.');
    redirigir('vinculaciones.php');
}

// ==========================================================
// ELIMINAR (solo administrador)
// ==========================================================
if ($accion === 'eliminar') {
    requerirRol(['administrador']);
    $vinculacionId = (int)get('id');
    $pdo->prepare('DELETE FROM vinculaciones WHERE id = ?')->execute([$vinculacionId]);
    flashExito('Vinculación eliminada.');
    redirigir('vinculaciones.php');
}

// ==========================================================
// LISTADO
// ==========================================================
$filtroEstado = get('estado');
$sqlBase = '
    SELECT v.*, 
           CONCAT(es.nombre," ",es.apellido) AS solicitante_nombre, es.id AS solicitante_id,
           CONCAT(eo.nombre," ",eo.apellido) AS ofertante_nombre, eo.id AS ofertante_id,
           m.nombre AS materia_nombre, f.nombre AS fortaleza_nombre, n.nombre AS necesidad_nombre
    FROM vinculaciones v
    JOIN estudiantes es ON es.id = v.estudiante_solicitante_id
    JOIN estudiantes eo ON eo.id = v.estudiante_ofertante_id
    LEFT JOIN materias m ON m.id = v.materia_id
    LEFT JOIN fortalezas f ON f.id = v.fortaleza_id
    LEFT JOIN necesidades_academicas n ON n.id = v.necesidad_id
    WHERE 1=1
';
$parametros = [];

if ($rol === 'estudiante') {
    $sqlBase .= ' AND (v.estudiante_solicitante_id = ? OR v.estudiante_ofertante_id = ?)';
    array_push($parametros, $miEstudianteId, $miEstudianteId);
}
if ($filtroEstado !== '' && in_array($filtroEstado, ['pendiente', 'aceptada', 'rechazada', 'completada'], true)) {
    $sqlBase .= ' AND v.estado = ?';
    $parametros[] = $filtroEstado;
}
$sqlBase .= ' ORDER BY v.fecha_solicitud DESC';

$stmt = $pdo->prepare($sqlBase);
$stmt->execute($parametros);
$vinculaciones = $stmt->fetchAll();

require __DIR__ . '/plantilla/encabezado.php';
?>

<div class="tarjeta">
    <div class="encabezado-tarjeta">
        <h2>🔄 <?= $rol === 'estudiante' ? 'Mis vinculaciones' : 'Todas las vinculaciones' ?> (<?= count($vinculaciones) ?>)</h2>
        <?php if ($rol === 'estudiante'): ?>
            <a href="buscar.php" class="boton boton-primario">➕ Nueva solicitud</a>
        <?php endif; ?>
    </div>

    <form method="GET" action="vinculaciones.php" class="barra-filtros">
        <select name="estado" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="aceptada" <?= $filtroEstado === 'aceptada' ? 'selected' : '' ?>>Aceptada</option>
            <option value="completada" <?= $filtroEstado === 'completada' ? 'selected' : '' ?>>Completada</option>
            <option value="rechazada" <?= $filtroEstado === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
        </select>
    </form>

    <div class="contenedor-tabla">
        <table class="tabla-datos">
            <thead>
                <tr><th>Solicitante</th><th>Ofertante</th><th>Materia / Fortaleza</th><th>Mensaje</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (empty($vinculaciones)): ?>
                    <tr><td colspan="7" class="sin-resultados">No hay vinculaciones para mostrar.</td></tr>
                <?php endif; ?>
                <?php foreach ($vinculaciones as $v): ?>
                    <tr>
                        <td><?= e($v['solicitante_nombre']) ?></td>
                        <td><?= e($v['ofertante_nombre']) ?></td>
                        <td>
                            <?= e($v['materia_nombre'] ?? '—') ?>
                            <?php if ($v['fortaleza_nombre']): ?><br><small style="color:var(--texto-secundario);"><?= e($v['fortaleza_nombre']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e(truncar($v['mensaje'] ?? '', 50)) ?></td>
                        <td>
                            <span class="<?= claseBadgeEstado($v['estado']) ?>"><?= e($v['estado']) ?></span>
                            <?php if ($v['estado'] === 'completada' && $v['calificacion']): ?>
                                <br><small>⭐ <?= (int)$v['calificacion'] ?>/5</small>
                            <?php endif; ?>
                        </td>
                        <td><?= formatearFecha($v['fecha_solicitud'], true) ?></td>
                        <td class="celda-acciones">
                            <?php if ($rol === 'estudiante' && $v['estado'] === 'pendiente' && (int)$v['ofertante_id'] === $miEstudianteId): ?>
                                <a href="vinculaciones.php?accion=responder&id=<?= (int)$v['id'] ?>&respuesta=aceptada" class="boton boton-exito boton-chico">Aceptar</a>
                                <a href="vinculaciones.php?accion=responder&id=<?= (int)$v['id'] ?>&respuesta=rechazada" class="boton boton-peligro boton-chico">Rechazar</a>
                            <?php endif; ?>
                            <?php if ($rol === 'estudiante' && $v['estado'] === 'aceptada' && (int)$v['solicitante_id'] === $miEstudianteId): ?>
                                <details>
                                    <summary class="boton boton-secundario boton-chico" style="cursor:pointer;display:inline-flex;">Completar</summary>
                                    <form method="POST" action="vinculaciones.php?accion=completar" style="margin-top:8px;display:flex;gap:6px;align-items:center;">
                                        <?= campoCSRF() ?>
                                        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                                        <select name="calificacion" class="boton-chico" style="padding:6px;">
                                            <option value="5">⭐⭐⭐⭐⭐</option>
                                            <option value="4">⭐⭐⭐⭐</option>
                                            <option value="3">⭐⭐⭐</option>
                                            <option value="2">⭐⭐</option>
                                            <option value="1">⭐</option>
                                        </select>
                                        <button type="submit" class="boton boton-exito boton-chico">Confirmar</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                            <?php if ($rol === 'administrador'): ?>
                                <form method="GET" action="vinculaciones.php" class="confirmar-eliminacion" data-mensaje="¿Eliminar esta vinculación?">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                                    <button type="submit" class="boton boton-peligro boton-chico">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/plantilla/pie.php'; ?>
