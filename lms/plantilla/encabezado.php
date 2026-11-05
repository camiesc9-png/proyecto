<?php
/**
 * plantilla/encabezado.php
 * Layout común: incluye <head>, sidebar de navegación y header superior.
 * Requiere que la página que lo incluye ya haya llamado a requerirSesion()/requerirRol()
 * y defina (opcionalmente) $tituloPagina antes de incluirlo.
 */

declare(strict_types=1);

$tituloPagina = $tituloPagina ?? 'Panel';
$rol = rolActual();

/** Estructura del menú lateral, filtrado por rol */
$itemsMenu = [
    ['url' => 'index.php',         'texto' => 'Dashboard',            'roles' => ['administrador','docente','estudiante'], 'icono' => 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z'],
    ['url' => 'perfil.php',        'texto' => 'Mi perfil académico',  'roles' => ['estudiante'], 'icono' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
    ['url' => 'buscar.php',        'texto' => 'Buscar compañeros',    'roles' => ['estudiante','docente','administrador'], 'icono' => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.35-4.35'],
    ['url' => 'vinculaciones.php', 'texto' => 'Vinculaciones',        'roles' => ['estudiante','docente','administrador'], 'icono' => 'M18 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM9 20c0-2.2 2-4 4-4M9 8c0 2.2-2 4-4 4'],
    ['url' => 'estudiantes.php',   'texto' => 'Estudiantes',          'roles' => ['administrador','docente'], 'icono' => 'M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m5-5a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
    ['url' => 'instituciones.php', 'texto' => 'Instituciones',        'roles' => ['administrador'], 'icono' => 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1M9 13h1M14 9h1M14 13h1M9 21v-4h6v4'],
    ['url' => 'materias.php',      'texto' => 'Materias',             'roles' => ['administrador','docente'], 'icono' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15z'],
    ['url' => 'fortalezas.php',    'texto' => 'Catálogo fortalezas',  'roles' => ['administrador'], 'icono' => 'M13 2L3 14h9l-1 8 10-12h-9l1-8z'],
    ['url' => 'necesidades.php',   'texto' => 'Catálogo necesidades', 'roles' => ['administrador'], 'icono' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM12 8v4l3 3'],
    ['url' => 'usuarios.php',      'texto' => 'Usuarios',             'roles' => ['administrador'], 'icono' => 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM20 8v6M23 11h-6'],
];

$paginaActual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($tituloPagina) ?> - <?= e(NOMBRE_SISTEMA) ?></title>
<link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<div class="app-layout">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-marca">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5-10-5z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/></svg>
            <div>
                <strong>LMS Vinculación</strong>
                <small>Santa Cruz de la Sierra</small>
            </div>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($itemsMenu as $item): ?>
                <?php if (in_array($rol, $item['roles'], true)): ?>
                    <a href="<?= e($item['url']) ?>" class="sidebar-link <?= $paginaActual === $item['url'] ? 'activo' : '' ?>">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $item['icono'] ?>"/></svg>
                        <span><?= e($item['texto']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <a href="logout.php" class="sidebar-link sidebar-salir">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            <span>Cerrar sesión</span>
        </a>
    </aside>

    <div class="area-contenido">
        <!-- ===== HEADER SUPERIOR ===== -->
        <header class="header-superior">
            <button class="boton-menu-movil" id="botonMenuMovil" aria-label="Abrir menú">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
            <h1 class="titulo-pagina"><?= e($tituloPagina) ?></h1>
            <div class="usuario-header">
                <span class="badge-rol badge-rol-<?= e($rol) ?>"><?= e(ucfirst($rol)) ?></span>
                <div class="avatar-usuario"><?= e(iniciales($_SESSION['usuario_nombre'] ?? '?')) ?></div>
                <span class="nombre-usuario-header"><?= e($_SESSION['usuario_nombre'] ?? '') ?></span>
            </div>
        </header>

        <main class="contenido-principal">
            <?php mostrarFlash(); ?>
