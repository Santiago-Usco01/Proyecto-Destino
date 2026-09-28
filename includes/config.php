<?php
/**
 * Configuración general de Destino.
 * Este archivo no debe ser accesible desde el navegador (ver includes/.htaccess).
 */

// ---------------------------------------------------------------- Configuración local
// Credenciales de la base de datos, modo desarrollo y hora de prueba.
// Ese archivo NO se sube a GitHub: se crea copiando config.ejemplo.php.
if (!is_file(__DIR__ . '/config.local.php')) {
    http_response_code(500);
    exit('Falta includes/config.local.php. Copia includes/config.ejemplo.php con ese nombre y ajusta los datos.');
}
require __DIR__ . '/config.local.php';

// ---------------------------------------------------------------- Rutas
// Carpeta del proyecto dentro de htdocs (http://localhost/destino).
define('BASE_URL', '/destino');
define('RAIZ', dirname(__DIR__));
define('CARPETA_UPLOADS', RAIZ . '/uploads/productos');

// ---------------------------------------------------------------- Horarios
date_default_timezone_set('America/Bogota');
define('HORA_APERTURA', '07:00:00');
define('HORA_CIERRE', '22:00:00');

// ---------------------------------------------------------------- Reglas del negocio
define('RESERVA_MAX_PERSONAS', 40);
define('RESERVA_DIAS_ANTELACION', 1);
define('RESERVA_DIAS_MAXIMO', 60);          // hasta cuántos días adelante se puede reservar
define('RESERVA_HORA_PRIMERA', '07:00:00'); // primera hora que se puede reservar
define('RESERVA_HORA_ULTIMA', '21:00:00');  // última hora (una hora antes del cierre)
define('RESERVA_INTERVALO_MIN', 30);        // horas disponibles cada 30 minutos
define('PASTELERIA_DIAS_ANTELACION', 7);

// ---------------------------------------------------------------- Información del negocio
define('NEGOCIO_NOMBRE', 'Destino Café Pizza Pan');
define('NEGOCIO_NOMBRE_CORTO', 'Destino');
define('NEGOCIO_CIUDAD', 'Neiva, Huila');
define('NEGOCIO_HORARIO', 'Lunes a domingo, 7:00 a. m. - 10:00 p. m.');
define('URL_INSTAGRAM', 'https://www.instagram.com/destinocafepizzapan/');
define('URL_FACEBOOK', 'https://www.facebook.com/p/Destino-Caf%C3%A9-Pizza-y-Pan-61558373340723/');

// Nombre de cada franja del menú, según el horario de sus categorías ("inicio-fin").
const FRANJAS_MENU = [
    '07:00:00-12:00:00' => 'Desayunos',
    '07:00:00-22:00:00' => 'Panadería y cafetería',
    '16:00:00-22:00:00' => 'Comidas rápidas',
];

// ---------------------------------------------------------------- Errores
// En desarrollo se muestran; en producción deben ocultarse (MODO_DESARROLLO en config.local.php).
ini_set('display_errors', MODO_DESARROLLO ? '1' : '0');
error_reporting(E_ALL);
