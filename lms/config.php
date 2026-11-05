<?php
/**
 * config.php
 * Configuración de conexión a la base de datos y funciones base de autenticación.
 * Sistema: LMS de Vinculación de Estudiantes - Santa Cruz de la Sierra
 */

declare(strict_types=1);

// ==========================================================
// CONFIGURACIÓN DE BASE DE DATOS
// ==========================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'lms_vinculacion');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Nombre del sistema (usado en encabezado.php)
define('NOMBRE_SISTEMA', 'LMS Vinculación Estudiantil - Santa Cruz de la Sierra');

// ==========================================================
// INICIO DE SESIÓN SEGURO
// ==========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// ==========================================================
// CONEXIÓN PDO (Singleton)
// ==========================================================
function obtenerConexion(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('Error de conexión a la base de datos. Verifique que ejecutó instalar.php. Detalle: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}

// ==========================================================
// AUTENTICACIÓN
// ==========================================================

/** Verifica si hay una sesión activa */
function estaAutenticado(): bool
{
    return isset($_SESSION['usuario_id']);
}

/** Obliga a que exista sesión activa; si no, redirige a login */
function requerirSesion(): void
{
    if (!estaAutenticado()) {
        header('Location: login.php');
        exit;
    }
}

/** Obliga a que el usuario tenga uno de los roles indicados */
function requerirRol(array $rolesPermitidos): void
{
    requerirSesion();
    if (!in_array($_SESSION['usuario_rol'], $rolesPermitidos, true)) {
        $_SESSION['flash_error'] = 'No tiene permisos para acceder a este módulo.';
        header('Location: index.php');
        exit;
    }
}

/** Devuelve el rol del usuario actual o cadena vacía */
function rolActual(): string
{
    return $_SESSION['usuario_rol'] ?? '';
}

/** Devuelve el id del usuario actual o null */
function usuarioIdActual(): ?int
{
    return $_SESSION['usuario_id'] ?? null;
}

/** Devuelve el id de estudiante ligado al usuario actual (si el rol es estudiante) */
function estudianteIdActual(): ?int
{
    return $_SESSION['estudiante_id'] ?? null;
}
