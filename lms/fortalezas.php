<?php
/**
 * fortalezas.php
 * CRUD del catálogo general de fortalezas académicas (habilidades que los estudiantes pueden ofrecer).
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';
requerirRol(['administrador']);

$pdo = obtenerConexion();
$tituloPagina = 'Catálogo de Fortalezas';
$accion = get('accion', 'listar');
$id = (int)get('id', '0');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($accion, ['nuevo', 'editar'], true)) {
    if (!tokenCSRFValido()) { flashError('Token de seguridad inválido.'); redirigir('fortalezas.php'); }
    $nombre = post('nombre');
    $descripcion = post('descripcion');
    if ($nombre === '') {
        flashError('El nombre de la fortaleza es obligatorio.');
        redirigir($accion === 'nuevo' ? 'fortalezas.php?accion=nuevo' : "fortalezas.php?accion=editar&id=$id");
    }
    try {
        if ($accion === 'nuevo') {
            $pdo->prepare('INSERT INTO fortalezas (nombre, descripcion) VALUES (?,?)')->execute([$nombre, $descripcion]);
            flashExito('Fortaleza registrada correctamente.');
        } else {
            $pdo->prepare('UPDATE fortalezas SET nombre=?, descripcion=? WHERE id=?')->execute([$nombre, $descripcion, $id]);
            flashExito('Fortaleza actualizada correctamente.');
        }
    } catch (Throwable $e) {
        flashError('No se pudo guardar (¿nombre duplicado?): ' . $e->getMessage());
    }
    redirigir('fortalezas.php');
}

if ($accion === 'eliminar' && $id > 0) {
    try {
        $pdo->prepare('DELETE FROM fortalezas WHERE id = ?')->execute([$id]);
        flashExito('Fortaleza eliminada correctamente.');
    } catch (Throwable $e) {
        flashError('No se pudo eliminar: ' . $e->getMessage());
    }
    redirigir('fortalezas.php');
}

$fortalezaEditar = null;
if ($accion === 'editar' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM fortalezas WHERE id = ?');
    $stmt->execute([$id]);
    $fortalezaEditar = $stmt->fetch();
    if (!$fortalezaEditar) { flashError('Fortaleza no encontrada.'); redirigir('fortalezas.php'); }
}

$busqueda = get('q');
$sql = 'SELECT f.*, (SELECT COUNT(*) FROM estudiante_fortalezas ef WHERE ef.fortaleza_id = f.id) AS total_usos FROM fortalezas f WHERE 1=1';
$parametros = [];
if ($busqueda !== '') { $sql .= ' AND f.nombre LIKE ?'; $parametros[] = "%$busqueda%"; }
$sql .= ' ORDER BY f.nombre';
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$fortalezas = $stmt->fetchAll();

require __DIR__ . '/plantilla/encabezado.php';
?>
<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="tarjeta" style="max-width:600px;">
        <div class="encabezado-tarjeta">
            <h2><?= $accion === 'nuevo' ? '➕ Nueva fortaleza' : '✏️ Editar fortaleza' ?></h2>
            <a href="fortalezas.php" class="boton boton-secundario">← Volver</a>
        </div>
        <form method="POST" action="fortalezas.php?accion=<?= e($accion) ?><?= $accion === 'editar' ? '&id=' . $id : '' ?>" class="formulario">
            <?= campoCSRF() ?>
            <label for="nombre">Nombre de la fortaleza *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="100" value="<?= e($fortalezaEditar['nombre'] ?? '') ?>">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" maxlength="255"><?= e($fortalezaEditar['descripcion'] ?? '') ?></textarea>
            <div class="acciones-formulario">
                <button type="submit" class="boton boton-primario">💾 Guardar</button>
                <a href="fortalezas.php" class="boton boton-secundario">Cancelar</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="tarjeta">
        <div class="encabezado-tarjeta">
            <h2>💪 Catálogo de fortalezas (<?= count($fortalezas) ?>)</h2>
            <a href="fortalezas.php?accion=nuevo" class="boton boton-primario">➕ Nueva fortaleza</a>
        </div>
        <form method="GET" action="fortalezas.php" class="barra-filtros">
            <div class="campo-busqueda">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="q" placeholder="Buscar fortaleza..." value="<?= e($busqueda) ?>">
            </div>
            <button type="submit" class="boton boton-secundario">Buscar</button>
        </form>
        <div class="contenedor-tabla">
            <table class="tabla-datos">
                <thead><tr><th>Nombre</th><th>Descripción</th><th>Estudiantes que la ofrecen</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php if (empty($fortalezas)): ?>
                        <tr><td colspan="4" class="sin-resultados">No hay fortalezas registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($fortalezas as $f): ?>
                        <tr>
                            <td><strong><?= e($f['nombre']) ?></strong></td>
                            <td><?= e($f['descripcion'] ?? '—') ?></td>
                            <td><span class="badge badge-verde"><?= (int)$f['total_usos'] ?></span></td>
                            <td class="celda-acciones">
                                <a href="fortalezas.php?accion=editar&id=<?= (int)$f['id'] ?>" class="boton boton-secundario boton-chico">Editar</a>
                                <form method="GET" action="fortalezas.php" class="confirmar-eliminacion" data-mensaje="¿Eliminar la fortaleza <?= e($f['nombre']) ?>?">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
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
