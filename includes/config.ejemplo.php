<?php
/**
 * Plantilla de configuración local.
 *
 * 1. Copia este archivo en la misma carpeta con el nombre config.local.php
 * 2. Ajusta los valores a tu instalación.
 *
 * config.local.php NO se sube a GitHub (está en .gitignore).
 */

// ---------------------------------------------------------------- Base de datos
define('DB_HOST', 'localhost');
define('DB_NOMBRE', 'destino');
define('DB_USUARIO', 'root');   // En XAMPP el usuario por defecto es root
define('DB_CLAVE', '');         // y no tiene contraseña

// ---------------------------------------------------------------- Entorno
// true: muestra los errores de PHP (desarrollo). false: los oculta (producción).
define('MODO_DESARROLLO', true);

// Hora simulada para probar o presentar el proyecto fuera del horario real.
// Ejemplo: '17:30:00'. Con null se usa la hora real. Solo funciona en modo desarrollo.
define('HORA_PRUEBA', null);
