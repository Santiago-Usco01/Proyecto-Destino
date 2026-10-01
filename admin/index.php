<?php
/**
 * Panel de administración: resumen y pendientes.
 */
require __DIR__ . '/../includes/admin.php';

$conteo = fn(string $sql) => (int) db()->query($sql)->fetchColumn();

$pedidos_pendientes  = $conteo("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente_confirmacion'");
$reservas_pendientes = $conteo("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente'");
$reservas_hoy        = $conteo("SELECT COUNT(*) FROM reservas WHERE fecha = CURDATE() AND estado IN ('pendiente','confirmada')");
$productos_activos   = $conteo('SELECT COUNT(*) FROM productos WHERE activo = 1');

$ultimos_pedidos = db()->query("SELECT p.id, p.nombre_cliente, p.total, p.created_at, s.nombre AS sede
                                FROM pedidos p JOIN sedes s ON s.id = p.sede_id
                                WHERE p.estado = 'pendiente_confirmacion'
                                ORDER BY p.created_at DESC LIMIT 8")->fetchAll();

$proximas_reservas = db()->query("SELECT r.id, r.nombre_cliente, r.fecha, r.hora, r.num_personas, r.estado, s.nombre AS sede
                                  FROM reservas r JOIN sedes s ON s.id = r.sede_id
                                  WHERE r.fecha >= CURDATE() AND r.estado IN ('pendiente','confirmada')
                                  ORDER BY r.fecha, r.hora LIMIT 8")->fetchAll();

$titulo_pagina = 'Resumen';
$seccion = 'inicio';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin__titulo"><h1>Resumen</h1></div>

<div class="admin__tarjetas">
    <a class="tarjeta admin__dato" href="<?= url('admin/pedidos.php?estado=pendiente_confirmacion') ?>">
        <strong><?= $pedidos_pendientes ?></strong><span>Pedidos por confirmar</span>
    </a>
    <a class="tarjeta admin__dato" href="<?= url('admin/reservas.php?estado=pendiente') ?>">
        <strong><?= $reservas_pendientes ?></strong><span>Reservas por confirmar</span>
    </a>
    <a class="tarjeta admin__dato" href="<?= url('admin/reservas.php') ?>">
        <strong><?= $reservas_hoy ?></strong><span>Reservas para hoy</span>
    </a>
    <a class="tarjeta admin__dato" href="<?= url('admin/productos.php') ?>">
        <strong><?= $productos_activos ?></strong><span>Productos activos</span>
    </a>
</div>

<div class="admin__rejilla">
    <section>
        <h2>Pedidos pendientes</h2>
        <?php if (!$ultimos_pedidos): ?>
            <p class="tarjeta admin__vacio">No hay pedidos por confirmar.</p>
        <?php else: ?>
            <div class="tabla-envoltorio"><table class="tabla">
                <thead><tr><th>Pedido</th><th>Cliente</th><th>Sede</th><th class="num">Total</th></tr></thead>
                <tbody>
                <?php foreach ($ultimos_pedidos as $p): ?>
                    <tr>
                        <td><a href="<?= url('admin/pedidos.php?id=' . (int) $p['id']) ?>"><?= e(codigo_pedido((int) $p['id'])) ?></a></td>
                        <td><?= e($p['nombre_cliente']) ?></td>
                        <td><?= e($p['sede']) ?></td>
                        <td class="num"><?= $p['total'] === null ? 'Por definir' : e(precio((int) $p['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section>
        <h2>Próximas reservas</h2>
        <?php if (!$proximas_reservas): ?>
            <p class="tarjeta admin__vacio">No hay reservas próximas.</p>
        <?php else: ?>
            <div class="tabla-envoltorio"><table class="tabla">
                <thead><tr><th>Reserva</th><th>Cliente</th><th>Cuándo</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($proximas_reservas as $r): ?>
                    <tr>
                        <td><a href="<?= url('admin/reservas.php?estado=' . e($r['estado'])) ?>"><?= e(codigo_reserva((int) $r['id'])) ?></a></td>
                        <td><?= e($r['nombre_cliente']) ?> (<?= (int) $r['num_personas'] ?>)</td>
                        <td><?= e(fecha_legible($r['fecha'])) ?>, <?= e(hora_legible($r['hora'])) ?><br><small><?= e($r['sede']) ?></small></td>
                        <td><?= etiqueta_estado($r['estado'], ESTADOS_RESERVA) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
