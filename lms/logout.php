<?php
/**
 * logout.php
 * Destruye la sesión activa de forma segura.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parametrosCookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $parametrosCookie['path'], $parametrosCookie['domain'],
        $parametrosCookie['secure'], $parametrosCookie['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;
