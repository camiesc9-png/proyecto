<?php
/**
 * instituciones.php
 * CRUD del catálogo de instituciones colaborativas del municipio (colegios, universidades, institutos).
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';
requerirRol(['administrador']);

$pdo = obtenerConexion();
$tituloPagina = 'Instituciones Colaborativas';
$accion = get('accion', 'listar');
$id = (int)get('id', '0');
$tiposValidos = ['Colegio', 'Universidad', 'Instituto Técnico'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($accion, ['nuevo', 'editar'], true)) {
    if (!tokenCSRFValido()) {
        flashError('Token de seguridad inválido.');
        redirigir('instituciones.php');
    }
    $nombre    = post('nombre');
    $tipo      = post('tipo');
    $direccion = post('direccion');
    $telefono  = post('telefono');

    if ($nombre === '' || !in_array($tipo, $tiposValidos, true)) {
        flashError('El nombre y el tipo de institución son obligatorios.');
        redirigir($accion === 'nuevo' ? 'instituciones.php?accion=nuevo' : "instituciones.php?accion=editar&id=$id");
    }

    try {
        if ($accion === 'nuevo') {
            $stmt = $pdo->prepare('INSERT INTO instituciones (nombre, tipo, direccion, telefono) VALUES (?,?,?,?)');
            $stmt->execute([$nombre, $tipo, $direccion, $telefono]);
            flashExito('Institución registrada correctamente.');
        } else {
            $stmt = $pdo->prepare('UPDATE instituciones SET nombre=?, tipo=?, direccion=?, telefono=? WHERE id=?');
            $stmt->execute([$nombre, $tipo, $direccion, $telefono, $id]);
            flashExito('Institución actualizada correctamente.');
        }
    } catch (Throwable $e) {
        flashError('No se pudo guardar: ' . $e->getMessage());
    }
    redirigir('instituciones.php');
}

if ($accion === 'eliminar' && $id > 0) {
    try {
        $pdo->prepare('DELETE FROM instituciones WHERE id = ?')->execute([$id]);
        flashExito('Institución eliminada. Los estudiantes ligados quedaron sin institución asignada.');
    } catch (Throwable $e) {
        flashError('No se pudo eliminar: ' . $e->getMessage());
    }
    redirigir('instituciones.php');
}

$institucionEditar = null;
if ($accion === 'editar' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM instituciones WHERE id = ?');
    $stmt->execute([$id]);
    $institucionEditar = $stmt->fetch();
    if (!$institucionEditar) {
        flashError('Institución no encontrada.');
        redirigir('instituciones.php');
    }
}

$busqueda = get('q');
$sql = '
    SELECT i.*, (SELECT COUNT(*) FROM estudiantes e WHERE e.institucion_id = i.id) AS total_estudiantes
    FROM instituciones i WHERE 1=1
';
$parametros = [];
if ($busqueda !== '') {
    $sql .= ' AND i.nombre LIKE ?';
    $parametros[] = "%$busqueda%";
}
$sql .= ' ORDER BY i.nombre';
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$instituciones = $stmt->fetchAll();

require __DIR__ . '/plantilla/encabezado.php';
?>

<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="tarjeta" style="max-width:600px;">
        <div class="encabezado-tarjeta">
            <h2><?= $accion === 'nuevo' ? '➕ Nueva institución' : '✏️ Editar institución' ?></h2>
            <a href="instituciones.php" class="boton boton-secundario">← Volver</a>
        </div>
        <form method="POST" action="instituciones.php?accion=<?= e($accion) ?><?= $accion === 'editar' ? '&id=' . $id : '' ?>" class="formulario">
            <?= campoCSRF() ?>
            <label for="nombre">Nombre de la institución *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="150" value="<?= e($institucionEditar['nombre'] ?? '') ?>">

            <label for="tipo">Tipo *</label>
            <select id="tipo" name="tipo" required>
                <?php foreach ($tiposValidos as $t): ?>
                    <option value="<?= e($t) ?>" <?= ($institucionEditar['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="direccion">Dirección</label>
            <input type="text" id="direccion" name="direccion" maxlength="200" value="<?= e($institucionEditar['direccion'] ?? '') ?>">

            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono" maxlength="30" value="<?= e($institucionEditar['telefono'] ?? '') ?>">

            <div class="acciones-formulario">
                <button type="submit" class="boton boton-primario">💾 Guardar</button>
                <a href="instituciones.php" class="boton boton-secundario">Cancelar</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="tarjeta">
        <div class="encabezado-tarjeta">
            <h2>🏫 Instituciones (<?= count($instituciones) ?>)</h2>
            <a href="instituciones.php?accion=nuevo" class="boton boton-primario">➕ Nueva institución</a>
        </div>
        <form method="GET" action="instituciones.php" class="barra-filtros">
            <div class="campo-busqueda">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="q" placeholder="Buscar institución..." value="<?= e($busqueda) ?>">
            </div>
            <button type="submit" class="boton boton-secundario">Buscar</button>
        </form>
        <div class="contenedor-tabla">
            <table class="tabla-datos">
                <thead><tr><th>Nombre</th><th>Tipo</th><th>Dirección</th><th>Teléfono</th><th>Estudiantes</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php if (empty($instituciones)): ?>
                        <tr><td colspan="6" class="sin-resultados">No hay instituciones registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($instituciones as $inst): ?>
                        <tr>
                            <td><strong><?= e($inst['nombre']) ?></strong></td>
                            <td><span class="badge badge-gris"><?= e($inst['tipo']) ?></span></td>
                            <td><?= e($inst['direccion'] ?? '—') ?></td>
                            <td><?= e($inst['telefono'] ?? '—') ?></td>
                            <td><?= (int)$inst['total_estudiantes'] ?></td>
                            <td class="celda-acciones">
                                <a href="instituciones.php?accion=editar&id=<?= (int)$inst['id'] ?>" class="boton boton-secundario boton-chico">Editar</a>
                                <form method="GET" action="instituciones.php" class="confirmar-eliminacion" data-mensaje="¿Eliminar la institución <?= e($inst['nombre']) ?>?">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int)$inst['id'] ?>">
                                    <button type="submit" class="boton boton-peligro boton-chico">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/plantilla/pie.php'; ?>
