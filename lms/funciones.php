<?php
/**
 * funciones.php
 * Funciones auxiliares reutilizables en todo el sistema.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

/** Escapa texto para salida segura en HTML (previene XSS) */
function e(?string $texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/** Formatea una fecha (YYYY-MM-DD o datetime) a formato legible dd/mm/yyyy */
function formatearFecha(?string $fecha, bool $conHora = false): string
{
    if (empty($fecha) || $fecha === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($fecha);
    if ($ts === false) {
        return '—';
    }
    return $conHora ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
}

/** Guarda un mensaje flash (éxito) para mostrarlo tras una redirección */
function flashExito(string $mensaje): void
{
    $_SESSION['flash_exito'] = $mensaje;
}

/** Guarda un mensaje flash (error) para mostrarlo tras una redirección */
function flashError(string $mensaje): void
{
    $_SESSION['flash_error'] = $mensaje;
}

/** Imprime (y limpia) los mensajes flash almacenados en sesión */
function mostrarFlash(): void
{
    if (!empty($_SESSION['flash_exito'])) {
        echo '<div class="alerta alerta-exito"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg><span>' . e($_SESSION['flash_exito']) . '</span></div>';
        unset($_SESSION['flash_exito']);
    }
    if (!empty($_SESSION['flash_error'])) {
        echo '<div class="alerta alerta-error"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg><span>' . e($_SESSION['flash_error']) . '</span></div>';
        unset($_SESSION['flash_error']);
    }
}

/** Redirige a una URL y detiene la ejecución */
function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Limpia y devuelve un valor de $_POST como string recortado */
function post(string $clave, string $porDefecto = ''): string
{
    return trim((string)($_POST[$clave] ?? $porDefecto));
}

/** Limpia y devuelve un valor de $_GET como string recortado */
function get(string $clave, string $porDefecto = ''): string
{
    return trim((string)($_GET[$clave] ?? $porDefecto));
}

/** Valida que el token CSRF del formulario coincida con el de sesión */
function tokenCSRFValido(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/** Genera (si no existe) y devuelve el token CSRF de la sesión actual */
function campoCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

/** Devuelve una clase CSS de "badge" según el estado textual */
function claseBadgeEstado(string $estado): string
{
    $verdes = ['activo', 'aceptada', 'completada', 'aprobado'];
    $rojos  = ['rechazada', 'inactivo', 'cancelada'];

    if (in_array($estado, $verdes, true)) {
        return 'badge badge-verde';
    }
    if ($estado === 'pendiente') {
        return 'badge badge-amarillo';
    }
    if (in_array($estado, $rojos, true)) {
        return 'badge badge-rojo';
    }
    return 'badge badge-gris';
}

/** Genera iniciales a partir de un nombre completo (para avatares) */
function iniciales(string $nombreCompleto): string
{
    $partes = preg_split('/\s+/', trim($nombreCompleto));
    $partes = array_filter($partes);
    if (empty($partes)) {
        return '?';
    }
    $ini = mb_strtoupper(mb_substr($partes[0], 0, 1));
    if (count($partes) > 1) {
        $ini .= mb_strtoupper(mb_substr(end($partes), 0, 1));
    }
    return $ini;
}

/** Exporta un arreglo de filas asociativas a CSV y termina la ejecución */
function exportarCSV(array $filas, string $nombreArchivo): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    $salida = fopen('php://output', 'w');
    // BOM para que Excel reconozca UTF-8 correctamente
    fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF));
    if (!empty($filas)) {
        fputcsv($salida, array_keys($filas[0]));
        foreach ($filas as $fila) {
            fputcsv($salida, $fila);
        }
    }
    fclose($salida);
    exit;
}

/** Trunca un texto a $limite caracteres añadiendo puntos suspensivos */
function truncar(string $texto, int $limite = 80): string
{
    if (mb_strlen($texto) <= $limite) {
        return $texto;
    }
    return mb_substr($texto, 0, $limite) . '…';
}
