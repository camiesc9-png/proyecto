<?php
/**
 * perfil.php
 * Permite al estudiante autenticado generar y mantener su perfil académico:
 * datos personales, fortalezas que puede enseñar y necesidades académicas que desea cubrir.
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';
requerirRol(['estudiante']);

$pdo = obtenerConexion();
$tituloPagina = 'Mi Perfil Académico';
$estudianteId = estudianteIdActual();

if (!$estudianteId) {
    flashError('Su cuenta no tiene un perfil de estudiante asociado. Contacte al administrador.');
    redirigir('index.php');
}

$accion = get('accion', '');

// ==========================================================
// ACTUALIZAR DATOS PERSONALES / BIOGRAFÍA
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'datos') {
    if (!tokenCSRFValido()) { flashError('Token de seguridad inválido.'); redirigir('perfil.php'); }
    $curso = post('curso');
    $telefono = post('telefono');
    $biografia = post('biografia');
    if ($curso === '') {
        flashError('El curso es obligatorio.');
    } else {
        $stmt = $pdo->prepare('UPDATE estudiantes SET curso=?, telefono=?, biografia=? WHERE id=?');
        $stmt->execute([$curso, $telefono, $biografia, $estudianteId]);
        flashExito('Datos personales actualizados.');
    }
    redirigir('perfil.php');
}

// ==========================================================
// AGREGAR FORTALEZA
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'agregar_fortaleza') {
    if (!tokenCSRFValido()) { flashError('Token de seguridad inválido.'); redirigir('perfil.php'); }
    $fortalezaId = (int)post('fortaleza_id');
    $materiaId = post('materia_id') !== '' ? (int)post('materia_id') : null;
    $nivel = post('nivel');
    $descripcion = post('descripcion');

    if ($fortalezaId <= 0 || !in_array($nivel, ['básico', 'intermedio', 'avanzado'], true)) {
        flashError('Seleccione una fortaleza y un nivel válido.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO estudiante_fortalezas (estudiante_id, fortaleza_id, materia_id, nivel, descripcion) VALUES (?,?,?,?,?)');
        $stmt->execute([$estudianteId, $fortalezaId, $materiaId, $nivel, $descripcion]);
        flashExito('Fortaleza agregada a su perfil académico.');
    }
    redirigir('perfil.php');
}

// ==========================================================
// AGREGAR NECESIDAD
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'agregar_necesidad') {
    if (!tokenCSRFValido()) { flashError('Token de seguridad inválido.'); redirigir('perfil.php'); }
    $necesidadId = (int)post('necesidad_id');
    $materiaId = post('materia_id') !== '' ? (int)post('materia_id') : null;
    $prioridad = post('prioridad');
    $descripcion = post('descripcion');

    if ($necesidadId <= 0 || !in_array($prioridad, ['baja', 'media', 'alta'], true)) {
        flashError('Seleccione una necesidad y una prioridad válida.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO estudiante_necesidades (estudiante_id, necesidad_id, materia_id, prioridad, descripcion) VALUES (?,?,?,?,?)');
        $stmt->execute([$estudianteId, $necesidadId, $materiaId, $prioridad, $descripcion]);
        flashExito('Necesidad académica agregada a su perfil.');
    }
    redirigir('perfil.php');
}

// ==========================================================
// ELIMINAR FORTALEZA / NECESIDAD PROPIA
// ==========================================================
if ($accion === 'eliminar_fortaleza') {
    $itemId = (int)get('id');
    $stmt = $pdo->prepare('DELETE FROM estudiante_fortalezas WHERE id = ? AND estudiante_id = ?');
    $stmt->execute([$itemId, $estudianteId]);
    flashExito('Fortaleza eliminada de su perfil.');
    redirigir('perfil.php');
}
if ($accion === 'eliminar_necesidad') {
    $itemId = (int)get('id');
    $stmt = $pdo->prepare('DELETE FROM estudiante_necesidades WHERE id = ? AND estudiante_id = ?');
    $stmt->execute([$itemId, $estudianteId]);
    flashExito('Necesidad eliminada de su perfil.');
    redirigir('perfil.php');
}

// ==========================================================
// DATOS PARA MOSTRAR
// ==========================================================
$stmt = $pdo->prepare('
    SELECT e.*, u.email, i.nombre AS institucion_nombre
    FROM estudiantes e
    JOIN usuarios u ON u.id = e.usuario_id
    LEFT JOIN instituciones i ON i.id = e.institucion_id
    WHERE e.id = ?
');
$stmt->execute([$estudianteId]);
$miPerfil = $stmt->fetch();

$materias = $pdo->query('SELECT id, nombre FROM materias ORDER BY nombre')->fetchAll();
$catalogoFortalezas = $pdo->query('SELECT id, nombre FROM fortalezas ORDER BY nombre')->fetchAll();
$catalogoNecesidades = $pdo->query('SELECT id, nombre FROM necesidades_academicas ORDER BY nombre')->fetchAll();

$stmt = $pdo->prepare('
    SELECT ef.*, f.nombre AS fortaleza_nombre, m.nombre AS materia_nombre
    FROM estudiante_fortalezas ef
    JOIN fortalezas f ON f.id = ef.fortaleza_id
    LEFT JOIN materias m ON m.id = ef.materia_id
    WHERE ef.estudiante_id = ? ORDER BY ef.fecha_registro DESC
');
$stmt->execute([$estudianteId]);
$misFortalezas = $stmt->fetchAll();

$stmt = $pdo->prepare('
    SELECT en.*, n.nombre AS necesidad_nombre, m.nombre AS materia_nombre
    FROM estudiante_necesidades en
    JOIN necesidades_academicas n ON n.id = en.necesidad_id
    LEFT JOIN materias m ON m.id = en.materia_id
    WHERE en.estudiante_id = ? ORDER BY en.fecha_registro DESC
');
$stmt->execute([$estudianteId]);
$misNecesidades = $stmt->fetchAll();

require __DIR__ . '/plantilla/encabezado.php';
?>

<div class="tarjeta">
    <div class="encabezado-tarjeta">
        <h2>🧑‍🎓 <?= e($miPerfil['nombre'] . ' ' . $miPerfil['apellido']) ?></h2>
        <span class="badge badge-gris"><?= e($miPerfil['codigo']) ?></span>
    </div>
    <div class="caja-info">
        <strong>Institución:</strong> <?= e($miPerfil['institucion_nombre'] ?? '—') ?> ·
        <strong>Correo:</strong> <?= e($miPerfil['email']) ?>
    </div>
    <form method="POST" action="perfil.php?accion=datos" class="formulario">
        <?= campoCSRF() ?>
        <div class="fila-formulario">
            <div>
                <label for="curso">Curso / Nivel *</label>
                <input type="text" id="curso" name="curso" required value="<?= e($miPerfil['curso']) ?>">
            </div>
            <div>
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" value="<?= e($miPerfil['telefono'] ?? '') ?>">
            </div>
        </div>
        <label for="biografia">Biografía breve</label>
        <textarea id="biografia" name="biografia" maxlength="500"><?= e($miPerfil['biografia'] ?? '') ?></textarea>
        <div class="acciones-formulario">
            <button type="submit" class="boton boton-primario">💾 Actualizar datos</button>
        </div>
    </form>
</div>

<div class="fila-formulario" style="align-items:start;">
    <!-- ================= FORTALEZAS ================= -->
    <div class="tarjeta">
        <h2>💪 Mis fortalezas (lo que puedo enseñar)</h2>
        <p class="subtitulo-tarjeta">Registra los temas en los que destacas para que otros estudiantes puedan encontrarte.</p>

        <form method="POST" action="perfil.php?accion=agregar_fortaleza" class="formulario">
            <?= campoCSRF() ?>
            <label for="fortaleza_id">Fortaleza *</label>
            <select id="fortaleza_id" name="fortaleza_id" required>
                <option value="">Seleccione...</option>
                <?php foreach ($catalogoFortalezas as $f): ?>
                    <option value="<?= (int)$f['id'] ?>"><?= e($f['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="fila-formulario">
                <div>
                    <label for="materia_id_f">Materia relacionada</label>
                    <select id="materia_id_f" name="materia_id">
                        <option value="">— No aplica —</option>
                        <?php foreach ($materias as $m): ?>
                            <option value="<?= (int)$m['id'] ?>"><?= e($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="nivel">Nivel *</label>
                    <select id="nivel" name="nivel" required>
                        <option value="básico">Básico</option>
                        <option value="intermedio" selected>Intermedio</option>
                        <option value="avanzado">Avanzado</option>
                    </select>
                </div>
            </div>
            <label for="descripcion_f">¿Qué puedes enseñar concretamente?</label>
            <textarea id="descripcion_f" name="descripcion" maxlength="400" placeholder="Ej. Puedo enseñar resolución de ecuaciones de segundo grado."></textarea>
            <div class="acciones-formulario">
                <button type="submit" class="boton boton-exito">➕ Agregar fortaleza</button>
            </div>
        </form>

        <div class="contenedor-tabla" style="margin-top:16px;">
            <table class="tabla-datos">
                <thead><tr><th>Fortaleza</th><th>Materia</th><th>Nivel</th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($misFortalezas)): ?>
                        <tr><td colspan="4" class="sin-resultados">Aún no registraste fortalezas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($misFortalezas as $mf): ?>
                        <tr>
                            <td><strong><?= e($mf['fortaleza_nombre']) ?></strong><?php if ($mf['descripcion']): ?><br><small style="color:var(--texto-secundario);"><?= e(truncar($mf['descripcion'], 60)) ?></small><?php endif; ?></td>
                            <td><?= e($mf['materia_nombre'] ?? '—') ?></td>
                            <td><span class="badge badge-nivel-<?= e($mf['nivel']) ?>"><?= e($mf['nivel']) ?></span></td>
                            <td>
                                <form method="GET" action="perfil.php" class="confirmar-eliminacion" data-mensaje="¿Eliminar esta fortaleza de tu perfil?">
                                    <input type="hidden" name="accion" value="eliminar_fortaleza">
                                    <input type="hidden" name="id" value="<?= (int)$mf['id'] ?>">
                                    <button type="submit" class="boton boton-peligro boton-chico">✕</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================= NECESIDADES ================= -->
    <div class="tarjeta">
        <h2>🎯 Mis necesidades académicas (lo que deseo aprender)</h2>
        <p class="subtitulo-tarjeta">Registra los temas en los que necesitas apoyo para que otros estudiantes te contacten.</p>

        <form method="POST" action="perfil.php?accion=agregar_necesidad" class="formulario">
            <?= campoCSRF() ?>
            <label for="necesidad_id">Necesidad académica *</label>
            <select id="necesidad_id" name="necesidad_id" required>
                <option value="">Seleccione...</option>
                <?php foreach ($catalogoNecesidades as $n): ?>
                    <option value="<?= (int)$n['id'] ?>"><?= e($n['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="fila-formulario">
                <div>
                    <label for="materia_id_n">Materia relacionada</label>
                    <select id="materia_id_n" name="materia_id">
                        <option value="">— No aplica —</option>
                        <?php foreach ($materias as $m): ?>
                            <option value="<?= (int)$m['id'] ?>"><?= e($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="prioridad">Prioridad *</label>
                    <select id="prioridad" name="prioridad" required>
                        <option value="baja">Baja</option>
                        <option value="media" selected>Media</option>
                        <option value="alta">Alta</option>
                    </select>
                </div>
            </div>
            <label for="descripcion_n">¿Qué necesitas aprender concretamente?</label>
            <textarea id="descripcion_n" name="descripcion" maxlength="400" placeholder="Ej. Necesito apoyo con ecuaciones y geometría."></textarea>
            <div class="acciones-formulario">
                <button type="submit" class="boton boton-exito">➕ Agregar necesidad</button>
            </div>
        </form>

        <div class="contenedor-tabla" style="margin-top:16px;">
            <table class="tabla-datos">
                <thead><tr><th>Necesidad</th><th>Materia</th><th>Prioridad</th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($misNecesidades)): ?>
                        <tr><td colspan="4" class="sin-resultados">Aún no registraste necesidades académicas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($misNecesidades as $mn): ?>
                        <tr>
                            <td><strong><?= e($mn['necesidad_nombre']) ?></strong><?php if ($mn['descripcion']): ?><br><small style="color:var(--texto-secundario);"><?= e(truncar($mn['descripcion'], 60)) ?></small><?php endif; ?></td>
                            <td><?= e($mn['materia_nombre'] ?? '—') ?></td>
                            <td><span class="badge badge-prioridad-<?= e($mn['prioridad']) ?>"><?= e($mn['prioridad']) ?></span></td>
                            <td>
                                <form method="GET" action="perfil.php" class="confirmar-eliminacion" data-mensaje="¿Eliminar esta necesidad de tu perfil?">
                                    <input type="hidden" name="accion" value="eliminar_necesidad">
                                    <input type="hidden" name="id" value="<?= (int)$mn['id'] ?>">
                                    <button type="submit" class="boton boton-peligro boton-chico">✕</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/plantilla/pie.php'; ?>
