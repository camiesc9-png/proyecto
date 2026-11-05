<?php
/**
 * estudiantes.php
 * CRUD de estudiantes. Cada estudiante está ligado a un usuario (login propio).
 * Accesible para administrador y docente. La eliminación solo la puede hacer el administrador.
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';
requerirRol(['administrador', 'docente']);

$pdo = obtenerConexion();
$tituloPagina = 'Gestión de Estudiantes';
$accion = get('accion', 'listar');
$id = (int)get('id', '0');

$instituciones = $pdo->query('SELECT id, nombre FROM instituciones ORDER BY nombre')->fetchAll();

// ==========================================================
// PROCESAR ENVÍO DE FORMULARIO (crear / editar)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($accion, ['nuevo', 'editar'], true)) {
    if (!tokenCSRFValido()) {
        flashError('Token de seguridad inválido. Intente nuevamente.');
        redirigir('estudiantes.php');
    }

    $codigo       = post('codigo');
    $nombre       = post('nombre');
    $apellido     = post('apellido');
    $curso        = post('curso');
    $institucionId = post('institucion_id') !== '' ? (int)post('institucion_id') : null;
    $telefono     = post('telefono');
    $biografia    = post('biografia');
    $email        = post('email');
    $password     = $_POST['password'] ?? '';

    if ($codigo === '' || $nombre === '' || $apellido === '' || $curso === '' || $email === '') {
        flashError('Los campos código, nombre, apellido, curso y correo son obligatorios.');
        redirigir($accion === 'nuevo' ? 'estudiantes.php?accion=nuevo' : "estudiantes.php?accion=editar&id=$id");
    }

    try {
        $pdo->beginTransaction();

        if ($accion === 'nuevo') {
            // Verificar duplicados
            $chk = $pdo->prepare('SELECT COUNT(*) c FROM usuarios WHERE email = ?');
            $chk->execute([$email]);
            if ((int)$chk->fetch()['c'] > 0) {
                throw new RuntimeException('Ya existe un usuario con ese correo electrónico.');
            }
            $chk = $pdo->prepare('SELECT COUNT(*) c FROM estudiantes WHERE codigo = ?');
            $chk->execute([$codigo]);
            if ((int)$chk->fetch()['c'] > 0) {
                throw new RuntimeException('Ya existe un estudiante con ese código.');
            }
            if ($password === '') {
                $password = '123456'; // contraseña por defecto si no se especifica
            }

            $stmtUsuario = $pdo->prepare('INSERT INTO usuarios (nombre, email, password, rol) VALUES (?,?,?,\'estudiante\')');
            $stmtUsuario->execute(["$nombre $apellido", $email, password_hash($password, PASSWORD_DEFAULT)]);
            $usuarioId = (int)$pdo->lastInsertId();

            $stmtEst = $pdo->prepare('INSERT INTO estudiantes (usuario_id, codigo, nombre, apellido, curso, institucion_id, telefono, biografia) VALUES (?,?,?,?,?,?,?,?)');
            $stmtEst->execute([$usuarioId, $codigo, $nombre, $apellido, $curso, $institucionId, $telefono, $biografia]);

            $pdo->commit();
            flashExito('Estudiante registrado correctamente. Contraseña de acceso: ' . e($password));
        } else {
            // Editar
            $stmt = $pdo->prepare('SELECT usuario_id FROM estudiantes WHERE id = ?');
            $stmt->execute([$id]);
            $existente = $stmt->fetch();
            if (!$existente) {
                throw new RuntimeException('El estudiante indicado no existe.');
            }

            $stmtEst = $pdo->prepare('UPDATE estudiantes SET codigo=?, nombre=?, apellido=?, curso=?, institucion_id=?, telefono=?, biografia=? WHERE id=?');
            $stmtEst->execute([$codigo, $nombre, $apellido, $curso, $institucionId, $telefono, $biografia, $id]);

            $stmtUsu = $pdo->prepare('UPDATE usuarios SET nombre=?, email=? WHERE id=?');
            $stmtUsu->execute(["$nombre $apellido", $email, $existente['usuario_id']]);

            if ($password !== '') {
                $stmtPass = $pdo->prepare('UPDATE usuarios SET password=? WHERE id=?');
                $stmtPass->execute([password_hash($password, PASSWORD_DEFAULT), $existente['usuario_id']]);
            }

            $pdo->commit();
            flashExito('Estudiante actualizado correctamente.');
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        flashError('No se pudo guardar: ' . $e->getMessage());
        redirigir($accion === 'nuevo' ? 'estudiantes.php?accion=nuevo' : "estudiantes.php?accion=editar&id=$id");
    }

    redirigir('estudiantes.php');
}

// ==========================================================
// ELIMINAR (solo administrador)
// ==========================================================
if ($accion === 'eliminar' && $id > 0) {
    requerirRol(['administrador']);
    try {
        $stmt = $pdo->prepare('SELECT usuario_id FROM estudiantes WHERE id = ?');
        $stmt->execute([$id]);
        $est = $stmt->fetch();
        if ($est) {
            // Al eliminar el usuario, se elimina en cascada el estudiante y sus registros relacionados
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$est['usuario_id']]);
            flashExito('Estudiante eliminado correctamente.');
        } else {
            flashError('El estudiante indicado no existe.');
        }
    } catch (Throwable $e) {
        flashError('No se pudo eliminar: ' . $e->getMessage());
    }
    redirigir('estudiantes.php');
}

// ==========================================================
// EXPORTAR CSV
// ==========================================================
if ($accion === 'exportar') {
    $filas = $pdo->query('
        SELECT e.codigo AS "Código", CONCAT(e.nombre," ",e.apellido) AS "Nombre completo",
               e.curso AS "Curso", COALESCE(i.nombre,"—") AS "Institución",
               u.email AS "Correo", e.telefono AS "Teléfono", e.fecha_registro AS "Fecha de registro"
        FROM estudiantes e
        JOIN usuarios u ON u.id = e.usuario_id
        LEFT JOIN instituciones i ON i.id = e.institucion_id
        ORDER BY e.nombre
    ')->fetchAll();
    exportarCSV($filas, 'estudiantes_' . date('Y-m-d') . '.csv');
}

// ==========================================================
// OBTENER REGISTRO PARA EDITAR
// ==========================================================
$estudianteEditar = null;
if ($accion === 'editar' && $id > 0) {
    $stmt = $pdo->prepare('SELECT e.*, u.email FROM estudiantes e JOIN usuarios u ON u.id = e.usuario_id WHERE e.id = ?');
    $stmt->execute([$id]);
    $estudianteEditar = $stmt->fetch();
    if (!$estudianteEditar) {
        flashError('Estudiante no encontrado.');
        redirigir('estudiantes.php');
    }
}

// ==========================================================
// LISTADO CON BÚSQUEDA Y FILTROS
// ==========================================================
$busqueda = get('q');
$filtroInstitucion = get('institucion');

$sql = '
    SELECT e.*, u.email, u.estado AS estado_usuario, i.nombre AS institucion_nombre
    FROM estudiantes e
    JOIN usuarios u ON u.id = e.usuario_id
    LEFT JOIN instituciones i ON i.id = e.institucion_id
    WHERE 1=1
';
$parametros = [];
if ($busqueda !== '') {
    $sql .= ' AND (e.nombre LIKE ? OR e.apellido LIKE ? OR e.codigo LIKE ? OR u.email LIKE ?)';
    $like = "%$busqueda%";
    array_push($parametros, $like, $like, $like, $like);
}
if ($filtroInstitucion !== '') {
    $sql .= ' AND e.institucion_id = ?';
    $parametros[] = (int)$filtroInstitucion;
}
$sql .= ' ORDER BY e.fecha_registro DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$estudiantes = $stmt->fetchAll();

require __DIR__ . '/plantilla/encabezado.php';
?>

<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="tarjeta" style="max-width:700px;">
        <div class="encabezado-tarjeta">
            <h2><?= $accion === 'nuevo' ? '➕ Registrar nuevo estudiante' : '✏️ Editar estudiante' ?></h2>
            <a href="estudiantes.php" class="boton boton-secundario">← Volver al listado</a>
        </div>
        <form method="POST" action="estudiantes.php?accion=<?= e($accion) ?><?= $accion === 'editar' ? '&id=' . $id : '' ?>" class="formulario">
            <?= campoCSRF() ?>
            <div class="fila-formulario">
                <div>
                    <label for="codigo">Código de estudiante *</label>
                    <input type="text" id="codigo" name="codigo" required maxlength="20" value="<?= e($estudianteEditar['codigo'] ?? '') ?>" placeholder="EST-0007">
                </div>
                <div>
                    <label for="curso">Curso / Nivel *</label>
                    <input type="text" id="curso" name="curso" required maxlength="40" value="<?= e($estudianteEditar['curso'] ?? '') ?>" placeholder="Ej. 5to Secundaria">
                </div>
            </div>
            <div class="fila-formulario">
                <div>
                    <label for="nombre">Nombres *</label>
                    <input type="text" id="nombre" name="nombre" required maxlength="80" value="<?= e($estudianteEditar['nombre'] ?? '') ?>">
                </div>
                <div>
                    <label for="apellido">Apellidos *</label>
                    <input type="text" id="apellido" name="apellido" required maxlength="80" value="<?= e($estudianteEditar['apellido'] ?? '') ?>">
                </div>
            </div>
            <div class="fila-formulario">
                <div>
                    <label for="institucion_id">Institución colaborativa</label>
                    <select id="institucion_id" name="institucion_id">
                        <option value="">— Sin especificar —</option>
                        <?php foreach ($instituciones as $inst): ?>
                            <option value="<?= (int)$inst['id'] ?>" <?= (isset($estudianteEditar['institucion_id']) && (int)$estudianteEditar['institucion_id'] === (int)$inst['id']) ? 'selected' : '' ?>><?= e($inst['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" maxlength="30" value="<?= e($estudianteEditar['telefono'] ?? '') ?>">
                </div>
            </div>
            <label for="biografia">Biografía breve</label>
            <textarea id="biografia" name="biografia" maxlength="500" placeholder="Ej. Me apasionan las matemáticas y ayudar a mis compañeros."><?= e($estudianteEditar['biografia'] ?? '') ?></textarea>

            <div class="fila-formulario">
                <div>
                    <label for="email">Correo electrónico (usuario) *</label>
                    <input type="email" id="email" name="email" required value="<?= e($estudianteEditar['email'] ?? '') ?>">
                </div>
                <div>
                    <label for="password">Contraseña <?= $accion === 'editar' ? '(dejar en blanco para no cambiar)' : '(por defecto: 123456)' ?></label>
                    <input type="password" id="password" name="password" placeholder="••••••••">
                </div>
            </div>

            <div class="acciones-formulario">
                <button type="submit" class="boton boton-primario">💾 Guardar estudiante</button>
                <a href="estudiantes.php" class="boton boton-secundario">Cancelar</a>
            </div>
        </form>
    </div>

<?php else: ?>

    <div class="tarjeta">
        <div class="encabezado-tarjeta">
            <h2>🎓 Listado de estudiantes (<?= count($estudiantes) ?>)</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="estudiantes.php?accion=exportar<?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?>" class="boton boton-secundario">⬇️ Exportar CSV</a>
                <a href="estudiantes.php?accion=nuevo" class="boton boton-primario">➕ Nuevo estudiante</a>
            </div>
        </div>

        <form method="GET" action="estudiantes.php" class="barra-filtros">
            <div class="campo-busqueda">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="q" placeholder="Buscar por nombre, código o correo..." value="<?= e($busqueda) ?>">
            </div>
            <select name="institucion" onchange="this.form.submit()">
                <option value="">Todas las instituciones</option>
                <?php foreach ($instituciones as $inst): ?>
                    <option value="<?= (int)$inst['id'] ?>" <?= $filtroInstitucion === (string)$inst['id'] ? 'selected' : '' ?>><?= e($inst['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="boton boton-secundario">Filtrar</button>
        </form>

        <div class="contenedor-tabla">
            <table class="tabla-datos">
                <thead>
                    <tr><th>Código</th><th>Nombre completo</th><th>Curso</th><th>Institución</th><th>Correo</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($estudiantes)): ?>
                        <tr><td colspan="7" class="sin-resultados">No se encontraron estudiantes con esos criterios.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($estudiantes as $est): ?>
                        <tr>
                            <td><strong><?= e($est['codigo']) ?></strong></td>
                            <td><?= e($est['nombre'] . ' ' . $est['apellido']) ?></td>
                            <td><?= e($est['curso']) ?></td>
                            <td><?= e($est['institucion_nombre'] ?? '—') ?></td>
                            <td><?= e($est['email']) ?></td>
                            <td><span class="<?= claseBadgeEstado($est['estado_usuario']) ?>"><?= e($est['estado_usuario']) ?></span></td>
                            <td class="celda-acciones">
                                <a href="estudiantes.php?accion=editar&id=<?= (int)$est['id'] ?>" class="boton boton-secundario boton-chico">Editar</a>
                                <?php if (rolActual() === 'administrador'): ?>
                                    <form method="GET" action="estudiantes.php" class="confirmar-eliminacion" data-mensaje="¿Eliminar al estudiante <?= e($est['nombre']) ?>? Se eliminará también su usuario y todos sus registros asociados.">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int)$est['id'] ?>">
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

<?php endif; ?>

<?php require __DIR__ . '/plantilla/pie.php'; ?>
