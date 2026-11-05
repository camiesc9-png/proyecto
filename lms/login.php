<?php
/**
 * login.php
 * Autenticación de usuarios: valida credenciales contra la BD con password_verify().
 */

declare(strict_types=1);
require_once __DIR__ . '/funciones.php';

// Si ya hay sesión activa, redirige al dashboard
if (estaAutenticado()) {
    redirigir('index.php');
}

$error = '';
$emailPrevio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!tokenCSRFValido()) {
        $error = 'Sesión de formulario expirada. Intente nuevamente.';
    } else {
        $email    = post('email');
        $password = $_POST['password'] ?? '';
        $emailPrevio = $email;

        if ($email === '' || $password === '') {
            $error = 'Debe completar correo y contraseña.';
        } else {
            $pdo = obtenerConexion();
            $stmt = $pdo->prepare('
                SELECT u.id, u.nombre, u.email, u.password, u.rol, u.estado, e.id AS estudiante_id
                FROM usuarios u
                LEFT JOIN estudiantes e ON e.usuario_id = u.id
                WHERE u.email = ?
                LIMIT 1
            ');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if (!$usuario || !password_verify($password, $usuario['password'])) {
                $error = 'Correo o contraseña incorrectos.';
            } elseif ($usuario['estado'] !== 'activo') {
                $error = 'Su cuenta se encuentra inactiva. Contacte al administrador.';
            } else {
                // Credenciales correctas: regenerar ID de sesión (previene fijación de sesión)
                session_regenerate_id(true);
                $_SESSION['usuario_id']     = (int)$usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['usuario_email']  = $usuario['email'];
                $_SESSION['usuario_rol']    = $usuario['rol'];
                $_SESSION['estudiante_id']  = $usuario['estudiante_id'] ? (int)$usuario['estudiante_id'] : null;

                flashExito('Bienvenido/a, ' . $usuario['nombre'] . '.');
                redirigir('index.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión - <?= e(NOMBRE_SISTEMA) ?></title>
<link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<div class="pagina-login">
    <div class="tarjeta-login">
        <div class="logo-login">
            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5-10-5z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/></svg>
            <h1>LMS Vinculación</h1>
        </div>
        <p class="subtitulo-login">Intercambio de conocimientos entre instituciones colaborativas · Santa Cruz de la Sierra</p>

        <?php if ($error): ?>
            <div class="alerta alerta-error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="formulario">
            <?= campoCSRF() ?>
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" required autofocus value="<?= e($emailPrevio) ?>" placeholder="nombre@lms.edu.bo">

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required placeholder="••••••••">

            <button type="submit" class="boton boton-primario boton-ancho">Iniciar sesión</button>
        </form>

        <div class="caja-credenciales-demo">
            <strong>Credenciales de prueba</strong> (contraseña: <code>123456</code>)
            <ul>
                <li>Administrador: <code>admin@lms.edu.bo</code></li>
                <li>Docente: <code>docente1@lms.edu.bo</code></li>
                <li>Estudiante: <code>ana.gutierrez@lms.edu.bo</code></li>
            </ul>
        </div>
        <p class="pie-login">¿Primera vez? Ejecute <a href="instalar.php">instalar.php</a> para crear la base de datos.</p>
    </div>
</div>
</body>
</html>
