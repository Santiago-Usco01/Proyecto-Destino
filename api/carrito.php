<?php
/**
 * API del carrito.
 * Recibe los ID de los productos que el cliente tiene en su carrito (localStorage)
 * y devuelve los datos REALES desde la base de datos: nombre, precio y si se
 * puede pedir en este momento. El navegador nunca decide el precio.
 *
 * GET api/carrito.php?ids=1,5,23
 */
require dirname(__DIR__) . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Solo enteros positivos, sin repetir, máximo 200.
$ids = array_values(array_unique(array_filter(
    array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))),
    fn(int $id) => $id > 0
)));
$ids = array_slice($ids, 0, 200);

$productos = [];

if ($ids) {
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $consulta = db()->prepare('SELECT p.id, p.nombre, p.precio, p.disponible_domicilio,
                                      (p.activo AND c.activo) AS activo, ' . SQL_FRANJA_PRODUCTO . '
                               FROM productos p
                               JOIN categorias c ON c.id = p.categoria_id
                               WHERE p.id IN (' . $marcadores . ')');
    $consulta->execute($ids);

    foreach ($consulta->fetchAll() as $p) {
        $motivo = motivo_no_pedible($p);
        $productos[] = [
            'id'         => (int) $p['id'],
            'nombre'     => $p['nombre'],
            'precio'     => (int) $p['precio'],
            'disponible' => $motivo === null,
            'motivo'     => $motivo,
        ];
    }
}

echo json_encode([
    'productos'     => $productos,
    'local_abierto' => local_abierto(),
    'estado_local'  => estado_local(),
], JSON_UNESCAPED_UNICODE);
