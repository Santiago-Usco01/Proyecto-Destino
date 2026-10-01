<?php
/**
 * Cierra la sesión. Exige el token CSRF en la URL para que otro sitio no pueda cerrarla.
 */
require __DIR__ . '/includes/init.php';

$token = $_GET['csrf'] ?? '';
if (esta_logueado() && is_string($token) && hash_equals(csrf_token(), $token)) {
    cerrar_sesion();
    session_start();   // sesión nueva solo para mostrar el mensaje
    flash('info', 'Cerraste sesión.');
}
redirigir('index.php');
