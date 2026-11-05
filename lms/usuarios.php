<?php
/**
 * usuarios.php
 * Administración de cuentas de usuario (administradores, docentes y estudiantes):
 * creación de cuentas de personal, cambio de estado y de contraseña.
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';
requerirRol(['administrador']);

$pdo = obtenerConexion();
$tituloPagina = 'Usuarios del Sistema';
$accion = get('accion', 'listar');
$id = (int)get('id', '0');
$rolesValidos = ['administrador', 'docente', 'estudiante'];

// ==========================================================
// CREAR / EDITAR (solo personal administrativo/docente desde aquí;
// los estudiantes se gestionan de forma completa en estudiantes.php)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($accion, ['nuevo', 'editar'], true)) {
    if (!tokenCSRFValido()) { flashError('Token de seguridad inválido.'); redirigir('usuarios.php'); }
    $nombre = post('nombre');
    $email = post('email');
    $rolNuevo = post('rol');
    $password = $_POST['password'] ?? '';

    if ($nombre === '' || $email === '' || !in_array($rolNuevo, $rolesValidos, true)) {
        flashError('Nombre, correo y rol son obligatorios.');
        redirigir($accion === 'nuevo' ? 'usuarios.php?accion=nuevo' : "usuarios.php?accion=editar&id=$id");
    }
    if ($rolNuevo === 'estudiante') {
        flashError('Para crear cuentas de estudiante, use el módulo "Estudiantes" (requiere código, curso, etc.).');
        redirigir('usuarios.php');
    }

    try {
        if ($accion === 'nuevo') {
            if ($password === '') { $password = '123456'; }
            $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, password, rol) VALUES (?,?,?,?)');
            $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), $rolNuevo]);
            flashExito('Usuario creado correctamente. Contraseña de acceso: ' . e($password));
        } else {
            $stmt = $pdo->prepare('UPDATE usuarios SET nombre=?, email=?, rol=? WHERE id=?');
            $stmt->execute([$nombre, $email, $rolNuevo, $id]);
            if ($password !== '') {
                $pdo->prepare('UPDATE usuarios SET password=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            flashExito('Usuario actualizado correctamente.');
        }
    } catch (Throwable $e) {
        flashError('No se pudo guardar (¿correo duplicado?): ' . $e->getMessage());
    }
    redirigir('usuarios.php');
}

// ==========================================================
// CAMBIAR ESTADO (activar / inactivar)
// ==========================================================
if ($accion === 'cambiar_estado' && $id > 0) {
    if ($id === usuarioIdActual()) {
        flashError('No puede desactivar su propia cuenta.');
        redirigir('usuarios.php');
    }
    $stmt = $pdo->prepare('SELECT estado FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if ($u) {
        $nuevoEstado = $u['estado'] === 'activo' ? 'inactivo' : 'activo';
        $pdo->prepare('UPDATE usuarios SET estado = ? WHERE id = ?')->execute([$nuevoEstado, $id]);
        flashExito('Estado del usuario actualizado a "' . $nuevoEstado . '".');
    }
    redirigir('usuarios.php');
}

// ==========================================================
// ELIMINAR
// ==========================================================
if ($accion === 'eliminar' && $id > 0) {
    if ($id === usuarioIdActual()) {
        flashError('No puede eliminar su propia cuenta.');
        redirigir('usuarios.php');
    }
    try {
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
        flashExito('Usuario eliminado correctamente.');
    } catch (Throwable $e) {
        flashError('No se pudo eliminar: ' . $e->getMessage());
    }
    redirigir('usuarios.php');
}

$usuarioEditar = null;
if ($accion === 'editar' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $usuarioEditar = $stmt->fetch();
    if (!$usuarioEditar) { flashError('Usuario no encontrado.'); redirigir('usuarios.php'); }
}

// ==========================================================
// LISTADO
// ==========================================================
$busqueda = get('q');
$filtroRol = get('rol');
$sql = 'SELECT * FROM usuarios WHERE 1=1';
$parametros = [];
if ($busqueda !== '') {
    $sql .= ' AND (nombre LIKE ? OR email LIKE ?)';
    array_push($parametros, "%$busqueda%", "%$busqueda%");
}
if ($filtroRol !== '' && in_array($filtroRol, $rolesValidos, true)) {
    $sql .= ' AND rol = ?';
    $parametros[] = $filtroRol;
}
$sql .= ' ORDER BY fecha_registro DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$usuarios = $stmt->fetchAll();

require __DIR__ . '/plantilla/encabezado.php';
?>

<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="tarjeta" style="max-width:600px;">
        <div class="encabezado-tarjeta">
            <h2><?= $accion === 'nuevo' ? '➕ Nuevo usuario (docente / administrador)' : '✏️ Editar usuario' ?></h2>
            <a href="usuarios.php" class="boton boton-secundario">← Volver</a>
        </div>
        <form method="POST" action="usuarios.php?accion=<?= e($accion) ?><?= $accion === 'editar' ? '&id=' . $id : '' ?>" class="formulario">
            <?= campoCSRF() ?>
            <label for="nombre">Nombre completo *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="120" value="<?= e($usuarioEditar['nombre'] ?? '') ?>">
            <label for="email">Correo electrónico *</label>
            <input type="email" id="email" name="email" required value="<?= e($usuarioEditar['email'] ?? '') ?>">
            <label for="rol">Rol *</label>
            <select id="rol" name="rol" required>
                <option value="docente" <?= ($usuarioEditar['rol'] ?? '') === 'docente' ? 'selected' : '' ?>>Docente</option>
                <option value="administrador" <?= ($usuarioEditar['rol'] ?? '') === 'administrador' ? 'selected' : '' ?>>Administrador</option>
            </select>
            <label for="password">Contraseña <?= $accion === 'editar' ? '(dejar en blanco para no cambiar)' : '(por defecto: 123456)' ?></label>
            <input type="password" id="password" name="password" placeholder="••••••••">
            <div class="acciones-formulario">
                <button type="submit" class="boton boton-primario">💾 Guardar</button>
                <a href="usuarios.php" class="boton boton-secundario">Cancelar</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="tarjeta">
        <div class="encabezado-tarjeta">
            <h2>👥 Usuarios del sistema (<?= count($usuarios) ?>)</h2>
            <a href="usuarios.php?accion=nuevo" class="boton boton-primario">➕ Nuevo docente/administrador</a>
        </div>
        <form method="GET" action="usuarios.php" class="barra-filtros">
            <div class="campo-busqueda">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="q" placeholder="Buscar por nombre o correo..." value="<?= e($busqueda) ?>">
            </div>
            <select name="rol" onchange="this.form.submit()">
                <option value="">Todos los roles</option>
                <?php foreach ($rolesValidos as $r): ?>
                    <option value="<?= e($r) ?>" <?= $filtroRol === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="boton boton-secundario">Filtrar</button>
        </form>
        <div class="contenedor-tabla">
            <table class="tabla-datos">
                <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Registrado</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr><td colspan="6" class="sin-resultados">No se encontraron usuarios.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><strong><?= e($u['nombre']) ?></strong></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge-rol badge-rol-<?= e($u['rol']) ?>"><?= e(ucfirst($u['rol'])) ?></span></td>
                            <td><span class="<?= claseBadgeEstado($u['estado']) ?>"><?= e($u['estado']) ?></span></td>
                            <td><?= formatearFecha($u['fecha_registro']) ?></td>
                            <td class="celda-acciones">
                                <?php if ($u['rol'] !== 'estudiante'): ?>
                                    <a href="usuarios.php?accion=editar&id=<?= (int)$u['id'] ?>" class="boton boton-secundario boton-chico">Editar</a>
                                <?php endif; ?>
                                <?php if ((int)$u['id'] !== usuarioIdActual()): ?>
                                    <a href="usuarios.php?accion=cambiar_estado&id=<?= (int)$u['id'] ?>" class="boton boton-secundario boton-chico"><?= $u['estado'] === 'activo' ? 'Inactivar' : 'Activar' ?></a>
                                    <?php if ($u['rol'] !== 'estudiante'): ?>
                                        <form method="GET" action="usuarios.php" class="confirmar-eliminacion" data-mensaje="¿Eliminar al usuario <?= e($u['nombre']) ?>?">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="boton boton-peligro boton-chico">Eliminar</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="texto-ayuda">(usted)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="texto-ayuda" style="margin-top:10px;">💡 Las cuentas de estudiante se gestionan desde el módulo <a href="estudiantes.php" style="color:var(--color-primario);">Estudiantes</a>.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/plantilla/pie.php'; ?>
