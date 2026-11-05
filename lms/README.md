# LMS de Vinculación Estudiantil — Santa Cruz de la Sierra

Sistema web en **PHP + MySQL** para vincular estudiantes según sus fortalezas y
necesidades académicas, con fines de intercambio de conocimientos entre
instituciones colaborativas del municipio de Santa Cruz de la Sierra.

## 1. Requisitos

- PHP 7.4 o superior (con extensión `pdo_mysql` habilitada)
- MySQL 5.7+ / MariaDB 10.3+
- Servidor web (Apache/Nginx) o el servidor embebido de PHP
- Herramienta tipo XAMPP / WAMP / Laragon si se instala en local

## 2. Estructura de carpetas

```
lms/
├── config.php                 Configuración de BD y autenticación base
├── funciones.php               Funciones auxiliares reutilizables
├── instalar.php                 Instalador automático (BD + tablas + datos de prueba)
├── login.php / logout.php      Autenticación
├── index.php                    Dashboard
├── estudiantes.php              CRUD de estudiantes
├── instituciones.php            CRUD de instituciones colaborativas
├── materias.php                 CRUD de materias
├── fortalezas.php               Catálogo de fortalezas
├── necesidades.php              Catálogo de necesidades académicas
├── perfil.php                   Perfil académico del estudiante
├── buscar.php                   Buscador de compañeros
├── vinculaciones.php            Solicitudes de intercambio de conocimientos
├── usuarios.php                 Administración de usuarios
├── plantilla/
│   ├── encabezado.php           Header + sidebar
│   └── pie.php                  Cierre de layout + scripts
├── css/
│   └── estilos.css              Hoja de estilos completa y responsive
└── README.md
```

## 3. Instalación paso a paso

1. Copie la carpeta `lms/` dentro de su directorio público del servidor
   (por ejemplo `C:\xampp\htdocs\lms` en XAMPP, o `/var/www/html/lms` en Linux).
2. Abra `config.php` y ajuste los datos de conexión si su MySQL no usa las
   credenciales por defecto:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'lms_vinculacion');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
3. Asegúrese de que el servicio de MySQL esté iniciado.
4. Abra en su navegador: **`http://localhost/lms/instalar.php`**
   Este script:
   - Crea la base de datos `lms_vinculacion` (si no existe).
   - Crea las 9 tablas con sus relaciones (`FOREIGN KEY`).
   - Carga datos de prueba realistas (instituciones, materias, fortalezas,
     necesidades, usuarios y estudiantes de ejemplo).
5. Al finalizar verá un mensaje de éxito con las credenciales de prueba.
   Haga clic en **"Ir al inicio de sesión"**.
6. **Importante (seguridad):** una vez instalado el sistema en un entorno de
   producción, elimine o renombre `instalar.php` para que nadie pueda
   volver a ejecutarlo y reiniciar la base de datos.

## 4. Credenciales de prueba

Todas las cuentas de prueba usan la misma contraseña: **`123456`**

| Rol            | Correo                              |
|----------------|--------------------------------------|
| Administrador  | admin@lms.edu.bo                     |
| Docente        | docente1@lms.edu.bo                  |
| Docente        | docente2@lms.edu.bo                  |
| Estudiante     | ana.gutierrez@lms.edu.bo             |
| Estudiante     | bruno.fernandez@lms.edu.bo           |
| Estudiante     | camila.suarez@lms.edu.bo             |
| Estudiante     | diego.vaca@lms.edu.bo                |
| Estudiante     | elena.rivero@lms.edu.bo              |
| Estudiante     | franco.ortiz@lms.edu.bo              |

## 5. Roles y funcionalidades

- **Administrador**: gestiona usuarios, estudiantes, instituciones, materias
  y los catálogos de fortalezas/necesidades. Ve estadísticas globales y puede
  eliminar cualquier registro, incluidas las vinculaciones.
- **Docente**: gestiona estudiantes y materias, y supervisa el listado
  completo de vinculaciones (solo lectura sobre catálogos globales).
- **Estudiante**: completa su perfil académico (fortalezas que puede enseñar
  y necesidades que desea cubrir), busca compañeros por materia/fortaleza, y
  envía, acepta, rechaza o completa (con calificación) solicitudes de
  vinculación.

## 6. Módulo central: Vinculaciones

1. El estudiante A entra a **Buscar compañeros** y filtra por materia o
   fortaleza.
2. Encuentra al estudiante B, que ofrece una fortaleza que A necesita, y
   pulsa **"Solicitar vinculación"**.
3. A completa el formulario (fortaleza de B, materia, su propia necesidad y
   un mensaje) y la solicitud queda en estado **pendiente**.
4. B revisa sus vinculaciones y la **acepta** o **rechaza**.
5. Si fue aceptada, tras realizar el intercambio, A la marca como
   **completada** y la califica de 1 a 5 estrellas.

## 7. Seguridad implementada

- Contraseñas con `password_hash()` / `password_verify()`.
- Consultas 100% preparadas con PDO (`prepare` + `execute`), previniendo
  inyección SQL.
- `htmlspecialchars()` en toda salida dinámica (función `e()`), previniendo XSS.
- `session_regenerate_id(true)` tras un login exitoso.
- Verificación de sesión y rol (`requerirSesion()` / `requerirRol()`) en
  cada página protegida.
- Token CSRF en todos los formularios POST.
- Transacciones (`beginTransaction`/`commit`/`rollBack`) en operaciones
  críticas que afectan varias tablas (crear/eliminar un estudiante junto a
  su usuario).

## 8. Exportación a CSV

El módulo **Estudiantes** incluye un botón **"Exportar CSV"** que genera un
archivo con codificación UTF-8 (BOM) compatible con Excel.

## 9. Solución de problemas

- **"Error de conexión a la base de datos"**: revise `DB_HOST`, `DB_USER` y
  `DB_PASS` en `config.php`, y que el servicio MySQL esté activo.
- **Página en blanco**: active `display_errors` en su `php.ini` para depurar,
  o revise el log de errores de PHP/Apache.
- **Quiero reinstalar desde cero**: vuelva a ejecutar `instalar.php`; el
  script elimina y recrea todas las tablas automáticamente.
