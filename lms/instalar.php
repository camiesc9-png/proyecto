<?php
/**
 * instalar.php
 * Instalador automático del sistema LMS de Vinculación Estudiantil.
 * Crea la base de datos, las tablas con sus relaciones y carga datos de prueba.
 * Ejecutar UNA sola vez desde el navegador: http://localhost/lms/instalar.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pasos  = [];
$errores = [];
$instalacionOk = false;

try {
    // 1) Conexión sin seleccionar base de datos, para poder crearla
    $pdoRaiz = new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdoRaiz->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pasos[] = 'Base de datos "' . DB_NAME . '" creada o ya existente.';

    // 2) Conexión a la base de datos recién creada
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // 3) Eliminar tablas previas (permite re-ejecutar el instalador limpiamente)
    $tablasAEliminar = [
        'vinculaciones', 'estudiante_necesidades', 'estudiante_fortalezas',
        'estudiantes', 'usuarios', 'necesidades_academicas', 'fortalezas',
        'materias', 'instituciones',
    ];
    foreach ($tablasAEliminar as $tabla) {
        $pdo->exec("DROP TABLE IF EXISTS `$tabla`");
    }
    $pasos[] = 'Tablas anteriores eliminadas (instalación limpia).';

    // 4) Creación de tablas
    $pdo->exec("
        CREATE TABLE instituciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            tipo ENUM('Colegio','Universidad','Instituto Técnico') NOT NULL DEFAULT 'Colegio',
            direccion VARCHAR(200) DEFAULT NULL,
            telefono VARCHAR(30) DEFAULT NULL,
            fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            rol ENUM('administrador','docente','estudiante') NOT NULL DEFAULT 'estudiante',
            estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
            fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE materias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            descripcion VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE fortalezas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            descripcion VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE necesidades_academicas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            descripcion VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE estudiantes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL UNIQUE,
            codigo VARCHAR(20) NOT NULL UNIQUE,
            nombre VARCHAR(80) NOT NULL,
            apellido VARCHAR(80) NOT NULL,
            curso VARCHAR(40) NOT NULL,
            institucion_id INT DEFAULT NULL,
            telefono VARCHAR(30) DEFAULT NULL,
            biografia VARCHAR(500) DEFAULT NULL,
            fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_est_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_est_institucion FOREIGN KEY (institucion_id) REFERENCES instituciones(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE estudiante_fortalezas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            estudiante_id INT NOT NULL,
            materia_id INT DEFAULT NULL,
            fortaleza_id INT NOT NULL,
            nivel ENUM('básico','intermedio','avanzado') NOT NULL DEFAULT 'intermedio',
            descripcion VARCHAR(400) DEFAULT NULL COMMENT 'Qué conocimiento concreto puede enseñar',
            fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ef_estudiante FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
            CONSTRAINT fk_ef_materia FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE SET NULL,
            CONSTRAINT fk_ef_fortaleza FOREIGN KEY (fortaleza_id) REFERENCES fortalezas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE estudiante_necesidades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            estudiante_id INT NOT NULL,
            materia_id INT DEFAULT NULL,
            necesidad_id INT NOT NULL,
            prioridad ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
            descripcion VARCHAR(400) DEFAULT NULL COMMENT 'Qué desea aprender concretamente',
            fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_en_estudiante FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
            CONSTRAINT fk_en_materia FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE SET NULL,
            CONSTRAINT fk_en_necesidad FOREIGN KEY (necesidad_id) REFERENCES necesidades_academicas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE vinculaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            estudiante_solicitante_id INT NOT NULL COMMENT 'Quien pide aprender',
            estudiante_ofertante_id INT NOT NULL COMMENT 'Quien va a enseñar',
            materia_id INT DEFAULT NULL,
            fortaleza_id INT DEFAULT NULL,
            necesidad_id INT DEFAULT NULL,
            mensaje VARCHAR(500) DEFAULT NULL,
            estado ENUM('pendiente','aceptada','rechazada','completada') NOT NULL DEFAULT 'pendiente',
            calificacion TINYINT DEFAULT NULL COMMENT '1 a 5, al completar el intercambio',
            fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_respuesta DATETIME DEFAULT NULL,
            CONSTRAINT fk_vi_solicitante FOREIGN KEY (estudiante_solicitante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
            CONSTRAINT fk_vi_ofertante FOREIGN KEY (estudiante_ofertante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
            CONSTRAINT fk_vi_materia FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE SET NULL,
            CONSTRAINT fk_vi_fortaleza FOREIGN KEY (fortaleza_id) REFERENCES fortalezas(id) ON DELETE SET NULL,
            CONSTRAINT fk_vi_necesidad FOREIGN KEY (necesidad_id) REFERENCES necesidades_academicas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pasos[] = 'Las 9 tablas fueron creadas correctamente con sus relaciones (FOREIGN KEY).';

    // 5) Datos de prueba
    $pdo->beginTransaction();

    // Instituciones colaborativas del municipio
    $instituciones = [
        ['U.E. San Ignacio de Loyola', 'Colegio', 'Av. San Martín 3er anillo', '3-3521478'],
        ['U.E. La Salle', 'Colegio', 'Av. Busch, Equipetrol', '3-3423311'],
        ['Universidad Autónoma Gabriel René Moreno (UAGRM)', 'Universidad', 'Av. Busch km. 3', '3-3423430'],
        ['Instituto Técnico Superior Santa Cruz', 'Instituto Técnico', '2do anillo, Av. Cristo Redentor', '3-3550021'],
        ['U.E. Cristo Rey', 'Colegio', 'Barrio Sirari, zona norte', '3-3441122'],
    ];
    $stmt = $pdo->prepare('INSERT INTO instituciones (nombre, tipo, direccion, telefono) VALUES (?,?,?,?)');
    foreach ($instituciones as $i) {
        $stmt->execute($i);
    }
    $pasos[] = count($instituciones) . ' instituciones colaborativas registradas.';

    // Materias
    $materias = ['Matemáticas', 'Física', 'Química', 'Lenguaje y Literatura', 'Inglés', 'Programación', 'Biología', 'Historia', 'Robótica', 'Contabilidad'];
    $stmt = $pdo->prepare('INSERT INTO materias (nombre) VALUES (?)');
    foreach ($materias as $m) {
        $stmt->execute([$m]);
    }
    $pasos[] = count($materias) . ' materias registradas.';

    // Fortalezas (catálogo de habilidades que un estudiante puede ofrecer)
    $fortalezas = [
        ['Resolución de problemas matemáticos', 'Facilidad para álgebra, geometría y cálculo'],
        ['Redacción y ortografía', 'Buena expresión escrita y comprensión lectora'],
        ['Programación básica', 'Lógica de programación, HTML/CSS, Python'],
        ['Conversación en inglés', 'Fluidez oral e interpretación de textos en inglés'],
        ['Laboratorio de química', 'Manejo de experimentos y reacciones químicas'],
        ['Oratoria y debate', 'Facilidad para hablar en público y argumentar'],
        ['Diseño gráfico', 'Manejo de herramientas de diseño y edición'],
        ['Memoria histórica', 'Facilidad para recordar y explicar procesos históricos'],
    ];
    $stmt = $pdo->prepare('INSERT INTO fortalezas (nombre, descripcion) VALUES (?,?)');
    foreach ($fortalezas as $f) {
        $stmt->execute($f);
    }
    $pasos[] = count($fortalezas) . ' fortalezas registradas en el catálogo.';

    // Necesidades académicas (catálogo)
    $necesidades = [
        ['Refuerzo en matemáticas', 'Dificultad con álgebra, ecuaciones o geometría'],
        ['Apoyo en redacción', 'Dificultad para estructurar textos o ensayos'],
        ['Introducción a la programación', 'Necesita aprender lógica de programación'],
        ['Práctica de inglés conversacional', 'Necesita mejorar fluidez oral en inglés'],
        ['Preparación de laboratorio', 'Necesita apoyo previo a prácticas de química/física'],
        ['Técnicas de estudio', 'Necesita mejorar organización y hábitos de estudio'],
        ['Preparación para exposiciones', 'Necesita mejorar su desempeño al exponer en público'],
    ];
    $stmt = $pdo->prepare('INSERT INTO necesidades_academicas (nombre, descripcion) VALUES (?,?)');
    foreach ($necesidades as $n) {
        $stmt->execute($n);
    }
    $pasos[] = count($necesidades) . ' necesidades académicas registradas en el catálogo.';

    // Usuarios (contraseña de prueba homogénea, hasheada con password_hash)
    $hashDemo = password_hash('123456', PASSWORD_DEFAULT);
    $stmtUsuario = $pdo->prepare('INSERT INTO usuarios (nombre, email, password, rol) VALUES (?,?,?,?)');

    $usuarios = [
        ['Administrador General', 'admin@lms.edu.bo', 'administrador'],
        ['Prof. Marco Vásquez', 'docente1@lms.edu.bo', 'docente'],
        ['Prof. Daniela Roca', 'docente2@lms.edu.bo', 'docente'],
        ['Ana Gutiérrez Salvatierra', 'ana.gutierrez@lms.edu.bo', 'estudiante'],
        ['Bruno Fernández Áñez', 'bruno.fernandez@lms.edu.bo', 'estudiante'],
        ['Camila Suárez Justiniano', 'camila.suarez@lms.edu.bo', 'estudiante'],
        ['Diego Vaca Melgar', 'diego.vaca@lms.edu.bo', 'estudiante'],
        ['Elena Rivero Peña', 'elena.rivero@lms.edu.bo', 'estudiante'],
        ['Franco Ortiz Barbery', 'franco.ortiz@lms.edu.bo', 'estudiante'],
    ];
    $idsUsuarios = [];
    foreach ($usuarios as $u) {
        $stmtUsuario->execute([$u[0], $u[1], $hashDemo, $u[2]]);
        $idsUsuarios[] = (int)$pdo->lastInsertId();
    }
    $pasos[] = count($usuarios) . ' usuarios creados (contraseña de prueba para todos: 123456).';

    // Estudiantes (ligados a los 6 usuarios con rol estudiante -> índices 3..8)
    $datosEstudiantes = [
        ['EST-0001', 'Ana', 'Gutiérrez Salvatierra', '5to Secundaria', 1, '70011122', 'Me apasionan las matemáticas y ayudar a mis compañeros.'],
        ['EST-0002', 'Bruno', 'Fernández Áñez', '1er Año Universidad', 3, '70022233', 'Estudiante de Ingeniería en Sistemas, me gusta programar.'],
        ['EST-0003', 'Camila', 'Suárez Justiniano', '4to Secundaria', 2, '70033344', 'Buena en redacción y literatura, busco mejorar en química.'],
        ['EST-0004', 'Diego', 'Vaca Melgar', '2do Año Universidad', 3, '70044455', 'Me interesa la robótica y el inglés técnico.'],
        ['EST-0005', 'Elena', 'Rivero Peña', '5to Secundaria', 4, '70055566', 'Buena en inglés, necesito refuerzo en física.'],
        ['EST-0006', 'Franco', 'Ortiz Barbery', '3ro Secundaria', 5, '70066677', 'Me gusta el diseño gráfico y la oratoria.'],
    ];
    $stmtEst = $pdo->prepare('INSERT INTO estudiantes (usuario_id, codigo, nombre, apellido, curso, institucion_id, telefono, biografia) VALUES (?,?,?,?,?,?,?,?)');
    $idsEstudiantes = [];
    foreach ($datosEstudiantes as $i => $d) {
        $usuarioId = $idsUsuarios[3 + $i]; // los primeros 3 usuarios son admin/docentes
        $stmtEst->execute([$usuarioId, $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6]]);
        $idsEstudiantes[] = (int)$pdo->lastInsertId();
    }
    $pasos[] = count($datosEstudiantes) . ' perfiles de estudiante creados.';

    // Fortalezas por estudiante [estudiante_idx, fortaleza_id, materia_id, nivel, descripcion]
    $stmtEF = $pdo->prepare('INSERT INTO estudiante_fortalezas (estudiante_id, fortaleza_id, materia_id, nivel, descripcion) VALUES (?,?,?,?,?)');
    $fortalezasEst = [
        [0, 1, 1, 'avanzado', 'Puedo enseñar álgebra y resolución de ecuaciones de segundo grado.'],
        [0, 6, 8, 'intermedio', 'Puedo explicar procesos históricos de forma resumida.'],
        [1, 3, 6, 'avanzado', 'Puedo enseñar lógica de programación y HTML/CSS desde cero.'],
        [2, 2, 4, 'avanzado', 'Puedo apoyar en redacción de ensayos y corrección de ortografía.'],
        [3, 3, 9, 'intermedio', 'Puedo enseñar fundamentos de robótica educativa con Arduino.'],
        [4, 4, 5, 'avanzado', 'Puedo practicar conversación en inglés a nivel intermedio-avanzado.'],
        [5, 7, null, 'intermedio', 'Puedo enseñar edición básica con Canva y Photoshop.'],
        [5, 6, null, 'intermedio', 'Puedo dar tips de oratoria para exposiciones.'],
    ];
    foreach ($fortalezasEst as $f) {
        $stmtEF->execute([$idsEstudiantes[$f[0]], $f[1], $f[2], $f[3], $f[4]]);
    }
    $pasos[] = count($fortalezasEst) . ' fortalezas asignadas a estudiantes.';

    // Necesidades por estudiante [estudiante_idx, necesidad_id, materia_id, prioridad, descripcion]
    $stmtEN = $pdo->prepare('INSERT INTO estudiante_necesidades (estudiante_id, necesidad_id, materia_id, prioridad, descripcion) VALUES (?,?,?,?,?)');
    $necesidadesEst = [
        [0, 4, 5, 'media', 'Necesito practicar más conversación en inglés.'],
        [1, 5, 3, 'alta', 'Necesito apoyo antes de mis prácticas de laboratorio de química.'],
        [2, 1, 1, 'alta', 'Tengo dificultades con ecuaciones y geometría.'],
        [3, 4, 5, 'media', 'Quiero mejorar mi inglés técnico para robótica.'],
        [4, 1, 2, 'media', 'Necesito refuerzo en física, especialmente cinemática.'],
        [5, 3, 6, 'alta', 'Quiero aprender lógica de programación desde cero.'],
    ];
    foreach ($necesidadesEst as $n) {
        $stmtEN->execute([$idsEstudiantes[$n[0]], $n[1], $n[2], $n[3], $n[4]]);
    }
    $pasos[] = count($necesidadesEst) . ' necesidades académicas asignadas a estudiantes.';

    // Vinculaciones de ejemplo [solicitante_idx, ofertante_idx, materia_id, fortaleza_id, necesidad_id, estado, mensaje]
    $stmtVi = $pdo->prepare('INSERT INTO vinculaciones (estudiante_solicitante_id, estudiante_ofertante_id, materia_id, fortaleza_id, necesidad_id, estado, mensaje, fecha_respuesta) VALUES (?,?,?,?,?,?,?,?)');
    $vinculaciones = [
        [2, 0, 1, 1, 1, 'aceptada', 'Hola Ana, ¿podrías ayudarme con ecuaciones de segundo grado?', date('Y-m-d H:i:s')],
        [5, 1, 6, 3, 3, 'pendiente', 'Franco, me interesa aprender programación básica contigo.', null],
        [4, 3, 9, 3, null, 'completada', 'Gracias por las clases de robótica, me sirvieron mucho.', date('Y-m-d H:i:s', strtotime('-2 days'))],
        [1, 4, 5, 4, 4, 'rechazada', 'Hola Elena, ¿me ayudas con inglés técnico?', date('Y-m-d H:i:s', strtotime('-1 day'))],
    ];
    foreach ($vinculaciones as $v) {
        $stmtVi->execute([$idsEstudiantes[$v[0]], $idsEstudiantes[$v[1]], $v[2], $v[3], $v[4], $v[5], $v[6], $v[7]]);
    }
    // Calificación para la vinculación completada
    $pdo->exec("UPDATE vinculaciones SET calificacion = 5 WHERE estado = 'completada'");
    $pasos[] = count($vinculaciones) . ' solicitudes de vinculación de ejemplo creadas.';

    $pdo->commit();
    $instalacionOk = true;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errores[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Instalación - <?= htmlspecialchars(NOMBRE_SISTEMA) ?></title>
<link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<div class="pagina-instalacion">
    <div class="tarjeta-instalacion">
        <h1>⚙️ Instalación del Sistema</h1>
        <h2><?= htmlspecialchars(NOMBRE_SISTEMA) ?></h2>

        <?php if ($instalacionOk): ?>
            <div class="alerta alerta-exito">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                <span>Instalación completada exitosamente.</span>
            </div>
            <ul class="lista-pasos">
                <?php foreach ($pasos as $p): ?>
                    <li><?= htmlspecialchars($p) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="caja-credenciales">
                <h3>Credenciales de acceso de prueba</h3>
                <table class="tabla-credenciales">
                    <tr><th>Rol</th><th>Correo</th><th>Contraseña</th></tr>
                    <tr><td>Administrador</td><td>admin@lms.edu.bo</td><td>123456</td></tr>
                    <tr><td>Docente</td><td>docente1@lms.edu.bo</td><td>123456</td></tr>
                    <tr><td>Estudiante</td><td>ana.gutierrez@lms.edu.bo</td><td>123456</td></tr>
                </table>
            </div>
            <a class="boton boton-primario" href="login.php">Ir al inicio de sesión →</a>
            <p class="aviso-seguridad">⚠️ Por seguridad, elimine o renombre <code>instalar.php</code> después de usarlo en producción.</p>
        <?php else: ?>
            <div class="alerta alerta-error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <span>Ocurrió un error durante la instalación.</span>
            </div>
            <ul class="lista-pasos">
                <?php foreach ($pasos as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?>
                <?php foreach ($errores as $err): ?><li style="color:#c0392b"><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
            <p>Revise los datos de conexión en <code>config.php</code> (DB_HOST, DB_USER, DB_PASS) y vuelva a intentarlo.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
