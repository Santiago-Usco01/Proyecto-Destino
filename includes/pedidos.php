<?php
/**
 * Lógica de pedidos: validar el carrito, guardar la solicitud y armar el mensaje de WhatsApp.
 * Un pedido guardado es una SOLICITUD en estado 'pendiente_confirmacion';
 * solo el encargado lo confirma, por WhatsApp.
 */

const METODOS_PAGO = [
    'efectivo'      => 'Efectivo',
    'transferencia' => 'Transferencia',
];

const PEDIDO_MAX_PRODUCTOS = 200;
const PEDIDO_MAX_CANTIDAD  = 999;

/**
 * Convierte el JSON del carrito ({"id": cantidad}) en [id => cantidad] válido.
 * Devuelve null si el formato no es válido.
 */
function leer_carrito_enviado(mixed $json): ?array
{
    if (!is_string($json) || strlen($json) > 10000) {
        return null;
    }
    $datos = json_decode($json, true);
    if (!is_array($datos) || count($datos) === 0 || count($datos) > PEDIDO_MAX_PRODUCTOS) {
        return null;
    }

    $carrito = [];
    foreach ($datos as $id => $cantidad) {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $cantidad = filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PEDIDO_MAX_CANTIDAD]]);
        if ($id === false || $cantidad === false) {
            return null;
        }
        $carrito[$id] = $cantidad;
    }
    return $carrito;
}

/**
 * Consulta los productos del carrito en la BD y arma las líneas del pedido
 * con el precio ACTUAL. Devuelve ['lineas' => [...], 'subtotal' => int, 'errores' => [...]].
 */
function preparar_lineas(array $carrito): array
{
    $ids = array_keys($carrito);
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $consulta = db()->prepare('SELECT p.id, p.nombre, p.precio, p.disponible_domicilio,
                                      (p.activo AND c.activo) AS activo, ' . SQL_FRANJA_PRODUCTO . '
                               FROM productos p
                               JOIN categorias c ON c.id = p.categoria_id
                               WHERE p.id IN (' . $marcadores . ')');
    $consulta->execute($ids);
    $productos = array_column($consulta->fetchAll(), null, 'id');

    $lineas = [];
    $errores = [];
    $subtotal = 0;

    foreach ($carrito as $id => $cantidad) {
        $p = $productos[$id] ?? null;
        if ($p === null) {
            $errores[] = 'Uno de los productos de tu carrito ya no existe en el menú.';
            continue;
        }
        $motivo = motivo_no_pedible($p);
        if ($motivo !== null) {
            $errores[] = $p['nombre'] . ': ' . $motivo . '.';
            continue;
        }
        $precio = (int) $p['precio'];
        $lineas[] = [
            'producto_id'     => (int) $p['id'],
            'nombre_producto' => $p['nombre'],
            'precio_unitario' => $precio,
            'cantidad'        => $cantidad,
            'subtotal_linea'  => $precio * $cantidad,
        ];
        $subtotal += $precio * $cantidad;
    }

    return ['lineas' => $lineas, 'subtotal' => $subtotal, 'errores' => $errores];
}

/**
 * Guarda el pedido y su detalle en una sola transacción.
 * $datos: usuario_id, sede_id, nombre_cliente, telefono_cliente, direccion_entrega,
 *         barrio, metodo_pago, observaciones, subtotal.
 * Devuelve el ID del pedido.
 */
function guardar_pedido(array $datos, array $lineas): int
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('INSERT INTO pedidos
                           (usuario_id, sede_id, nombre_cliente, telefono_cliente, direccion_entrega,
                            barrio, metodo_pago, observaciones, subtotal)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $datos['usuario_id'],
                $datos['sede_id'],
                $datos['nombre_cliente'],
                $datos['telefono_cliente'],
                $datos['direccion_entrega'],
                $datos['barrio'],
                $datos['metodo_pago'],
                $datos['observaciones'],
                $datos['subtotal'],
            ]);
        $pedido_id = (int) $pdo->lastInsertId();

        // subtotal_linea lo calcula MySQL (columna generada).
        $insertar = $pdo->prepare('INSERT INTO detalle_pedido
                                       (pedido_id, producto_id, nombre_producto, precio_unitario, cantidad)
                                   VALUES (?, ?, ?, ?, ?)');
        foreach ($lineas as $l) {
            $insertar->execute([$pedido_id, $l['producto_id'], $l['nombre_producto'], $l['precio_unitario'], $l['cantidad']]);
        }

        $pdo->commit();
        return $pedido_id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Texto del mensaje de WhatsApp para la sede. Usa *texto* para negrita. */
function mensaje_whatsapp_pedido(int $pedido_id, array $datos, array $lineas, array $sede): string
{
    $m = [];
    $m[] = '*Nuevo pedido ' . codigo_pedido($pedido_id) . '*';
    $m[] = $sede['nombre'];
    $m[] = '';
    $m[] = '*Cliente*';
    $m[] = 'Nombre: ' . $datos['nombre_cliente'];
    $m[] = 'Teléfono: ' . telefono_legible($datos['telefono_cliente']);
    $m[] = 'Dirección: ' . $datos['direccion_entrega'];
    if ($datos['barrio']) {
        $m[] = 'Barrio: ' . $datos['barrio'];
    }
    $m[] = '';
    $m[] = '*Productos*';
    foreach ($lineas as $l) {
        $m[] = $l['cantidad'] . ' x ' . $l['nombre_producto']
             . ' (' . precio($l['precio_unitario']) . ' c/u) = ' . precio($l['subtotal_linea']);
    }
    $m[] = '';
    $m[] = '*Subtotal: ' . precio($datos['subtotal']) . '*';
    $m[] = 'Domicilio: por confirmar';
    $m[] = 'Método de pago: ' . METODOS_PAGO[$datos['metodo_pago']];
    if ($datos['observaciones']) {
        $m[] = '';
        $m[] = '*Observaciones*';
        $m[] = $datos['observaciones'];
    }
    $m[] = '';
    $m[] = 'Quedo pendiente de la confirmación del pedido y del valor del domicilio.';

    return implode("\n", $m);
}
