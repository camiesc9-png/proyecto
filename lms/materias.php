<?php
/**
 * materias.php
 * CRUD del catálogo de materias académicas.
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';
requerirRol(['administrador', 'docente']);

$pdo = obtenerConexion();
$tituloPagina = 'Materias';
$accion = get('accion', 'listar');
$id = (int)get('id', '0');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($accion, ['nuevo', 'editar'], true)) {
    if (!tokenCSRFValido()) {
        flashError('Token de seguridad inválido.');
        redirigir('materias.php');
    }
    $nombre = post('nombre');
    $descripcion = post('descripcion');

    if ($nombre === '') {
        flashError('El nombre de la materia es obligatorio.');
        redirigir($accion === 'nuevo' ? 'materias.php?accion=nuevo' : "materias.php?accion=editar&id=$id");
    }

    try {
        if ($accion === 'nuevo') {
            $stmt = $pdo->prepare('INSERT INTO materias (nombre, descripcion) VALUES (?,?)');
            $stmt->execute([$nombre, $descripcion]);
            flashExito('Materia registrada correctamente.');
        } else {
            requerirRol(['administrador']);
            $stmt = $pdo->prepare('UPDATE materias SET nombre=?, descripcion=? WHERE id=?');
            $stmt->execute([$nombre, $descripcion, $id]);
            flashExito('Materia actualizada correctamente.');
        }
    } catch (Throwable $e) {
        flashError('No se pudo guardar (¿nombre duplicado?): ' . $e->getMessage());
    }
    redirigir('materias.php');
}

if ($accion === 'eliminar' && $id > 0) {
    requerirRol(['administrador']);
    try {
        $pdo->prepare('DELETE FROM materias WHERE id = ?')->execute([$id]);
        flashExito('Materia eliminada correctamente.');
    } catch (Throwable $e) {
        flashError('No se pudo eliminar: ' . $e->getMessage());
    }
    redirigir('materias.php');
}

$materiaEditar = null;
if ($accion === 'editar' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM materias WHERE id = ?');
    $stmt->execute([$id]);
    $materiaEditar = $stmt->fetch();
    if (!$materiaEditar) { flashError('Materia no encontrada.'); redirigir('materias.php'); }
}

$busqueda = get('q');
$sql = 'SELECT m.*, 
        (SELECT COUNT(*) FROM estudiante_fortalezas ef WHERE ef.materia_id = m.id) +
        (SELECT COUNT(*) FROM estudiante_necesidades en WHERE en.materia_id = m.id) AS total_usos
        FROM materias m WHERE 1=1';
$parametros = [];
if ($busqueda !== '') { $sql .= ' AND m.nombre LIKE ?'; $parametros[] = "%$busqueda%"; }
$sql .= ' ORDER BY m.nombre';
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$materias = $stmt->fetchAll();

require __DIR__ . '/plantilla/encabezado.php';
?>

<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="tarjeta" style="max-width:600px;">
        <div class="encabezado-tarjeta">
            <h2><?= $accion === 'nuevo' ? '➕ Nueva materia' : '✏️ Editar materia' ?></h2>
            <a href="materias.php" class="boton boton-secundario">← Volver</a>
        </div>
        <form method="POST" action="materias.php?accion=<?= e($accion) ?><?= $accion === 'editar' ? '&id=' . $id : '' ?>" class="formulario">
            <?= campoCSRF() ?>
            <label for="nombre">Nombre de la materia *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="100" value="<?= e($materiaEditar['nombre'] ?? '') ?>">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" maxlength="255"><?= e($materiaEditar['descripcion'] ?? '') ?></textarea>
            <div class="acciones-formulario">
                <button type="submit" class="boton boton-primario">💾 Guardar</button>
                <a href="materias.php" class="boton boton-secundario">Cancelar</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="tarjeta">
        <div class="encabezado-tarjeta">
            <h2>📘 Materias (<?= count($materias) ?>)</h2>
            <a href="materias.php?accion=nuevo" class="boton boton-primario">➕ Nueva materia</a>
        </div>
        <form method="GET" action="materias.php" class="barra-filtros">
            <div class="campo-busqueda">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="q" placeholder="Buscar materia..." value="<?= e($busqueda) ?>">
            </div>
            <button type="submit" class="boton boton-secundario">Buscar</button>
        </form>
        <div class="contenedor-tabla">
            <table class="tabla-datos">
                <thead><tr><th>Nombre</th><th>Descripción</th><th>Usos en perfiles</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php if (empty($materias)): ?>
                        <tr><td colspan="4" class="sin-resultados">No hay materias registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($materias as $mat): ?>
                        <tr>
                            <td><strong><?= e($mat['nombre']) ?></strong></td>
                            <td><?= e($mat['descripcion'] ?? '—') ?></td>
                            <td><span class="badge badge-gris"><?= (int)$mat['total_usos'] ?></span></td>
                            <td class="celda-acciones">
                                <?php if (rolActual() === 'administrador'): ?>
                                <a href="materias.php?accion=editar&id=<?= (int)$mat['id'] ?>" class="boton boton-secundario boton-chico">Editar</a>
                                <form method="GET" action="materias.php" class="confirmar-eliminacion" data-mensaje="¿Eliminar la materia <?= e($mat['nombre']) ?>?">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int)$mat['id'] ?>">
                                    <button type="submit" class="boton boton-peligro boton-chico">Eliminar</button>
                                </form>
                                <?php else: ?>
                                    <span class="texto-ayuda">Solo lectura</span>
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
