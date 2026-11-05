<?php
/**
 * necesidades.php
 * CRUD del catálogo general de necesidades académicas (temas en los que los estudiantes requieren apoyo).
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';
requerirRol(['administrador']);

$pdo = obtenerConexion();
$tituloPagina = 'Catálogo de Necesidades Académicas';
$accion = get('accion', 'listar');
$id = (int)get('id', '0');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($accion, ['nuevo', 'editar'], true)) {
    if (!tokenCSRFValido()) { flashError('Token de seguridad inválido.'); redirigir('necesidades.php'); }
    $nombre = post('nombre');
    $descripcion = post('descripcion');
    if ($nombre === '') {
        flashError('El nombre de la necesidad académica es obligatorio.');
        redirigir($accion === 'nuevo' ? 'necesidades.php?accion=nuevo' : "necesidades.php?accion=editar&id=$id");
    }
    try {
        if ($accion === 'nuevo') {
            $pdo->prepare('INSERT INTO necesidades_academicas (nombre, descripcion) VALUES (?,?)')->execute([$nombre, $descripcion]);
            flashExito('Necesidad académica registrada correctamente.');
        } else {
            $pdo->prepare('UPDATE necesidades_academicas SET nombre=?, descripcion=? WHERE id=?')->execute([$nombre, $descripcion, $id]);
            flashExito('Necesidad académica actualizada correctamente.');
        }
    } catch (Throwable $e) {
        flashError('No se pudo guardar (¿nombre duplicado?): ' . $e->getMessage());
    }
    redirigir('necesidades.php');
}

if ($accion === 'eliminar' && $id > 0) {
    try {
        $pdo->prepare('DELETE FROM necesidades_academicas WHERE id = ?')->execute([$id]);
        flashExito('Necesidad académica eliminada correctamente.');
    } catch (Throwable $e) {
        flashError('No se pudo eliminar: ' . $e->getMessage());
    }
    redirigir('necesidades.php');
}

$necesidadEditar = null;
if ($accion === 'editar' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM necesidades_academicas WHERE id = ?');
    $stmt->execute([$id]);
    $necesidadEditar = $stmt->fetch();
    if (!$necesidadEditar) { flashError('Necesidad académica no encontrada.'); redirigir('necesidades.php'); }
}

$busqueda = get('q');
$sql = 'SELECT n.*, (SELECT COUNT(*) FROM estudiante_necesidades en WHERE en.necesidad_id = n.id) AS total_usos FROM necesidades_academicas n WHERE 1=1';
$parametros = [];
if ($busqueda !== '') { $sql .= ' AND n.nombre LIKE ?'; $parametros[] = "%$busqueda%"; }
$sql .= ' ORDER BY n.nombre';
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$necesidades = $stmt->fetchAll();

require __DIR__ . '/plantilla/encabezado.php';
?>
<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="tarjeta" style="max-width:600px;">
        <div class="encabezado-tarjeta">
            <h2><?= $accion === 'nuevo' ? '➕ Nueva necesidad académica' : '✏️ Editar necesidad académica' ?></h2>
            <a href="necesidades.php" class="boton boton-secundario">← Volver</a>
        </div>
        <form method="POST" action="necesidades.php?accion=<?= e($accion) ?><?= $accion === 'editar' ? '&id=' . $id : '' ?>" class="formulario">
            <?= campoCSRF() ?>
            <label for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" required maxlength="100" value="<?= e($necesidadEditar['nombre'] ?? '') ?>">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" maxlength="255"><?= e($necesidadEditar['descripcion'] ?? '') ?></textarea>
            <div class="acciones-formulario">
                <button type="submit" class="boton boton-primario">💾 Guardar</button>
                <a href="necesidades.php" class="boton boton-secundario">Cancelar</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="tarjeta">
        <div class="encabezado-tarjeta">
            <h2>🎯 Catálogo de necesidades académicas (<?= count($necesidades) ?>)</h2>
            <a href="necesidades.php?accion=nuevo" class="boton boton-primario">➕ Nueva necesidad</a>
        </div>
        <form method="GET" action="necesidades.php" class="barra-filtros">
            <div class="campo-busqueda">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" name="q" placeholder="Buscar necesidad..." value="<?= e($busqueda) ?>">
            </div>
            <button type="submit" class="boton boton-secundario">Buscar</button>
        </form>
        <div class="contenedor-tabla">
            <table class="tabla-datos">
                <thead><tr><th>Nombre</th><th>Descripción</th><th>Estudiantes que la reportan</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php if (empty($necesidades)): ?>
                        <tr><td colspan="4" class="sin-resultados">No hay necesidades académicas registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($necesidades as $n): ?>
                        <tr>
                            <td><strong><?= e($n['nombre']) ?></strong></td>
                            <td><?= e($n['descripcion'] ?? '—') ?></td>
                            <td><span class="badge badge-amarillo"><?= (int)$n['total_usos'] ?></span></td>
                            <td class="celda-acciones">
                                <a href="necesidades.php?accion=editar&id=<?= (int)$n['id'] ?>" class="boton boton-secundario boton-chico">Editar</a>
                                <form method="GET" action="necesidades.php" class="confirmar-eliminacion" data-mensaje="¿Eliminar la necesidad <?= e($n['nombre']) ?>?">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
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
