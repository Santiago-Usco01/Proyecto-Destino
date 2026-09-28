<?php
/**
 * Conexión a MySQL/MariaDB mediante PDO.
 * Todas las consultas del proyecto deben usar prepare() con parámetros
 * para evitar inyección SQL.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NOMBRE . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USUARIO, DB_CLAVE, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Las fechas que genera MySQL (created_at) quedan en hora de Colombia.
            $pdo->exec("SET time_zone = '-05:00'");
        } catch (PDOException $e) {
            if (MODO_DESARROLLO) {
                die('Error de conexión a la base de datos: ' . $e->getMessage());
            }
            die('No fue posible conectar con la base de datos. Intenta más tarde.');
        }
    }

    return $pdo;
}
