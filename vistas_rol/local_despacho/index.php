<?php
/**
 * Panel Local de Despacho.
 * El local confirma disponibilidad, prepara el pedido y lo entrega al motorista.
 * Los pedidos cerrados o ya entregados al motorista quedan bloqueados para no
 * alterar estados finales del flujo logístico.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['Local Despacho']);

$currentPage = 'inicio';
$pageTitle = 'Panel Local de Despacho';
$user = current_user();
$farmaciaId = $user['farmacia_id'] ?? null;
$errors = [];

if (!$farmaciaId) {
    $errors[] = 'El usuario local de despacho no tiene una farmacia/local asociado en la base de datos.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $farmaciaId) {
    $postAction = $_POST['action'] ?? '';
    $movimientoId = (int)($_POST['movimiento_id'] ?? 0);
    $observacion = trim($_POST['observacion'] ?? '');

    $stmt = $pdo->prepare('SELECT id, estado, disponibilidad_producto, motorista_id FROM movimientos WHERE id = ? AND farmacia_origen_id = ? LIMIT 1');
    $stmt->execute([$movimientoId, $farmaciaId]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        $errors[] = 'El pedido seleccionado no corresponde a este local de despacho.';
    } else {
        $estadoAnterior = (string)$pedido['estado'];

        if (movimiento_cerrado($estadoAnterior)) {
            $errors[] = 'Este pedido ya está cerrado y no puede modificarse desde el Local de Despacho.';
        }

        if ($postAction === 'confirmar_disponibilidad' && !$errors) {
            if (in_array($estadoAnterior, ['Asignado a motorista', 'En curso'], true)) {
                $errors[] = 'No se puede modificar disponibilidad porque el pedido ya fue asignado o entregado al motorista.';
            }
            $disponibilidad = trim($_POST['disponibilidad_producto'] ?? '');
            if (!in_array($disponibilidad, ['Disponible', 'No disponible'], true)) {
                $errors[] = 'Debe indicar si el producto está disponible o no disponible.';
            }
            if (!$errors) {
                $nuevoEstado = $disponibilidad === 'Disponible' ? 'Preparando' : 'Producto no disponible';
                $stmt = $pdo->prepare('UPDATE movimientos SET disponibilidad_producto = ?, estado = ?, incidencia_descripcion = IF(? <> "", ?, incidencia_descripcion) WHERE id = ? AND farmacia_origen_id = ?');
                $stmt->execute([$disponibilidad, $nuevoEstado, $observacion, $observacion, $movimientoId, $farmaciaId]);
                log_historial_movimiento($pdo, $movimientoId, $user['id'], $estadoAnterior, $nuevoEstado, $observacion ?: 'Confirmación de disponibilidad desde Local de Despacho');
                redirect_with(app_url('vistas_rol/local_despacho/index.php'), 'ok', 'Disponibilidad del pedido actualizada correctamente.');
            }
        }

        if ($postAction === 'preparar_pedido' && !$errors) {
            if (($pedido['disponibilidad_producto'] ?? '') !== 'Disponible' && $estadoAnterior !== 'Preparando') {
                $errors[] = 'Primero debe confirmar que el pedido está disponible.';
            }
            if ($estadoAnterior === 'Producto no disponible') {
                $errors[] = 'El pedido fue marcado sin stock. Farmacia Central debe gestionar el traslado desde otro local.';
            }
            if (in_array($estadoAnterior, ['Asignado a motorista', 'En curso'], true)) {
                $errors[] = 'El pedido ya fue asignado o entregado al motorista.';
            }
            if (!$errors) {
                $stmt = $pdo->prepare('UPDATE movimientos SET estado = "Listo para retiro", disponibilidad_producto = "Disponible" WHERE id = ? AND farmacia_origen_id = ?');
                $stmt->execute([$movimientoId, $farmaciaId]);
                log_historial_movimiento($pdo, $movimientoId, $user['id'], $estadoAnterior, 'Listo para retiro', $observacion ?: 'Pedido preparado por local de despacho');
                redirect_with(app_url('vistas_rol/local_despacho/index.php'), 'ok', 'Pedido marcado como listo para retiro.');
            }
        }

        if ($postAction === 'entregar_motorista' && !$errors) {
            if (empty($pedido['motorista_id'])) {
                $errors[] = 'El pedido aún no tiene motorista asignado por Control de Despacho.';
            }
            if (!in_array($estadoAnterior, ['Listo para retiro', 'Asignado a motorista'], true)) {
                $errors[] = 'El pedido debe estar listo para retiro o asignado a motorista antes de entregarlo.';
            }
            if (!$errors) {
                $stmt = $pdo->prepare('UPDATE movimientos SET estado = "En curso" WHERE id = ? AND farmacia_origen_id = ?');
                $stmt->execute([$movimientoId, $farmaciaId]);
                log_historial_movimiento($pdo, $movimientoId, $user['id'], $estadoAnterior, 'En curso', $observacion ?: 'Pedido entregado al motorista asignado');
                redirect_with(app_url('vistas_rol/local_despacho/index.php'), 'ok', 'Pedido entregado al motorista y marcado en curso.');
            }
        }
    }
}

$local = null;
$pedidos = [];
if ($farmaciaId) {
    $stmt = $pdo->prepare('SELECT * FROM farmacias WHERE id = ? LIMIT 1');
    $stmt->execute([$farmaciaId]);
    $local = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT m.*, CONCAT(mt.nombres, ' ', mt.apellidos) AS motorista, mo.patente
        FROM movimientos m
        LEFT JOIN motoristas mt ON mt.id = m.motorista_id
        LEFT JOIN motos mo ON mo.id = m.moto_id
        WHERE m.farmacia_origen_id = ?
        ORDER BY m.fecha_movimiento DESC, m.id DESC");
    $stmt->execute([$farmaciaId]);
    $pedidos = $stmt->fetchAll();
}

require_once __DIR__ . '/../../includes/header.php';
$flash = get_flash();
?>
<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger border-dark"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="hero-card">
    <h1 class="section-title h2 mb-2">Panel Local de Despacho</h1>
    <p class="mb-0">Permite preparar pedidos recibidos desde Farmacia Central, confirmar disponibilidad y entregar pedidos a motoristas asignados por Control de Despacho.</p>
    <?php if ($local): ?><p class="mt-3 mb-0"><strong>Local asociado:</strong> <?= e($local['nombre'] . ' / ' . $local['comuna'] . ' / ' . $local['region']) ?></p><?php endif; ?>
</section>

<section id="pedidos-recibidos" class="panel-card">
    <h2 class="h4 fw-bold mb-3">Pedidos recibidos desde Farmacia Central</h2>
    <?php if (!$pedidos): ?>
        <div class="empty-state">No existen pedidos asignados a este local.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-logico align-middle mb-0">
                <thead><tr><th>N°/Código pedido</th><th>Cliente / destino</th><th>Dirección</th><th>Motorista</th><th>Disponibilidad</th><th>Estado</th><th id="preparacion">Gestión del local</th></tr></thead>
                <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                    <?php
                        $estado = (string)$pedido['estado'];
                        $cerrado = movimiento_cerrado($estado);
                        $bloqueadoPorRuta = in_array($estado, ['En curso'], true);
                        $sinStock = $estado === 'Producto no disponible' || ($pedido['disponibilidad_producto'] ?? '') === 'No disponible';
                    ?>
                    <tr>
                        <td><?= e($pedido['codigo_pedido'] ?? ('PED-' . $pedido['id'])) ?></td>
                        <td><?= e($pedido['cliente_nombre']) ?></td>
                        <td><?= e($pedido['direccion_entrega']) ?></td>
                        <td><?= e(trim(($pedido['motorista'] ?? '') . ' / ' . ($pedido['patente'] ?? '')) ?: '-') ?></td>
                        <td><?= e($pedido['disponibilidad_producto'] ?? 'Pendiente') ?></td>
                        <td><span class="badge badge-logico"><?= e($estado) ?></span></td>
                        <td style="min-width: 420px;">
                            <?php if ($cerrado): ?>
                                <div class="alert alert-secondary border-dark py-2 mb-0"><strong>Gestión cerrada.</strong><br><span class="small">El pedido está <?= e($estado) ?> y no puede actualizarse desde el local.</span></div>
                            <?php elseif ($bloqueadoPorRuta): ?>
                                <div class="alert alert-info border-dark py-2 mb-0"><strong>Pedido entregado al motorista.</strong><br><span class="small">La siguiente actualización corresponde al motorista.</span></div>
                            <?php elseif ($sinStock): ?>
                                <div class="alert alert-warning border-dark py-2 mb-0"><strong>Producto no disponible.</strong><br><span class="small">Farmacia Central debe modificar el local de despacho y registrar el traslado.</span></div>
                            <?php else: ?>
                                <?php if (in_array($estado, ['Pendiente local', 'Preparando'], true)): ?>
                                    <form method="post" class="row g-2 mb-2">
                                        <input type="hidden" name="action" value="confirmar_disponibilidad">
                                        <input type="hidden" name="movimiento_id" value="<?= e((string)$pedido['id']) ?>">
                                        <div class="col-12 col-lg-4"><select name="disponibilidad_producto" class="form-select form-select-sm"><option value="Disponible" <?= selected((string)$pedido['disponibilidad_producto'], 'Disponible') ?>>Disponible</option><option value="No disponible" <?= selected((string)$pedido['disponibilidad_producto'], 'No disponible') ?>>No disponible</option></select></div>
                                        <div class="col-12 col-lg-5"><input name="observacion" class="form-control form-control-sm" placeholder="Observación a Farmacia Central"></div>
                                        <div class="col-12 col-lg-3"><button class="btn btn-sm btn-outline-logico w-100" type="submit">Disponibilidad</button></div>
                                    </form>
                                <?php endif; ?>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if (in_array($estado, ['Preparando', 'Pendiente local'], true) && ($pedido['disponibilidad_producto'] ?? '') === 'Disponible'): ?>
                                        <form method="post"><input type="hidden" name="action" value="preparar_pedido"><input type="hidden" name="movimiento_id" value="<?= e((string)$pedido['id']) ?>"><button class="btn btn-sm btn-logico" type="submit">Listo para retiro</button></form>
                                    <?php endif; ?>
                                    <?php if (in_array($estado, ['Listo para retiro', 'Asignado a motorista'], true)): ?>
                                        <form method="post"><input type="hidden" name="action" value="entregar_motorista"><input type="hidden" name="movimiento_id" value="<?= e((string)$pedido['id']) ?>"><button class="btn btn-sm btn-outline-logico" type="submit">Entregar a motorista</button></form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
