<?php
/**
 * Panel Motorista.
 * El motorista solo puede gestionar pedidos asignados a su cuenta. Cuando un
 * pedido queda Terminado, No entregado, Incidencia o Anulado, la gestión se
 * cierra para evitar cambios posteriores sobre un despacho ya finalizado.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['Motorista']);

$currentPage = 'inicio';
$pageTitle = 'Panel motorista';
$user = current_user();
$motoristaId = $user['motorista_id'] ?? null;
$errors = [];

if (!$motoristaId) {
    $errors[] = 'El usuario motorista no tiene un motorista asociado en la base de datos.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $motoristaId) {
    $postAction = $_POST['action'] ?? '';
    $movimientoId = (int)($_POST['movimiento_id'] ?? 0);
    $observacion = trim($_POST['observacion'] ?? '');

    $stmt = $pdo->prepare('SELECT id, estado FROM movimientos WHERE id = ? AND motorista_id = ? LIMIT 1');
    $stmt->execute([$movimientoId, $motoristaId]);
    $movimiento = $stmt->fetch();

    if (!$movimiento) {
        $errors[] = 'El pedido seleccionado no está asignado a este motorista.';
    } else {
        $estadoAnterior = (string)$movimiento['estado'];

        if (movimiento_cerrado($estadoAnterior)) {
            $errors[] = 'Este pedido ya está cerrado y no puede volver a modificarse desde el panel motorista.';
        }

        if ($postAction === 'actualizar_estado' && !$errors) {
            $nuevoEstado = trim($_POST['estado'] ?? '');
            if (!in_array($nuevoEstado, ['En curso', 'Terminado', 'No entregado'], true)) {
                $errors[] = 'Estado no permitido para el perfil motorista.';
            }
            if ($nuevoEstado === 'No entregado' && $observacion === '') {
                $errors[] = 'Para marcar un pedido como no entregado debe indicar una observación o motivo.';
            }
            if (!$errors) {
                $fechaEntregaSql = in_array($nuevoEstado, ['Terminado', 'No entregado'], true) ? ', fecha_entrega = NOW()' : '';
                $stmt = $pdo->prepare("UPDATE movimientos SET estado = ?, incidencia_descripcion = IF(? <> '', ?, incidencia_descripcion) {$fechaEntregaSql} WHERE id = ? AND motorista_id = ?");
                $stmt->execute([$nuevoEstado, $observacion, $observacion, $movimientoId, $motoristaId]);
                log_historial_movimiento($pdo, $movimientoId, $user['id'], $estadoAnterior, $nuevoEstado, $observacion ?: 'Actualización desde panel motorista');
                redirect_with(app_url('vistas_rol/motorista/index.php'), 'ok', 'Estado del pedido actualizado correctamente.');
            }
        }

        if ($postAction === 'reportar_incidencia' && !$errors) {
            if ($observacion === '') {
                $errors[] = 'Debe indicar la incidencia detectada.';
            }
            if (!$errors) {
                $stmt = $pdo->prepare('INSERT INTO incidencias (movimiento_id, motorista_id, usuario_id, descripcion, estado) VALUES (?, ?, ?, ?, "Abierta")');
                $stmt->execute([$movimientoId, $motoristaId, $user['id'], $observacion]);
                $stmt = $pdo->prepare('UPDATE movimientos SET estado = "Incidencia", incidencia_descripcion = ?, fecha_entrega = NOW() WHERE id = ? AND motorista_id = ?');
                $stmt->execute([$observacion, $movimientoId, $motoristaId]);
                log_historial_movimiento($pdo, $movimientoId, $user['id'], $estadoAnterior, 'Incidencia', $observacion);
                redirect_with(app_url('vistas_rol/motorista/index.php'), 'ok', 'Incidencia reportada correctamente. El pedido quedó cerrado para nuevas modificaciones.');
            }
        }
    }
}

$pedidos = [];
if ($motoristaId) {
    $stmt = $pdo->prepare("SELECT m.*, fo.nombre AS farmacia_origen, fo.comuna AS comuna_origen, fo.region AS region_origen, fd.nombre AS farmacia_destino, fd.comuna AS comuna_destino, fd.region AS region_destino, mo.patente
        FROM movimientos m
        LEFT JOIN farmacias fo ON fo.id = m.farmacia_origen_id
        LEFT JOIN farmacias fd ON fd.id = m.farmacia_destino_id
        LEFT JOIN motos mo ON mo.id = m.moto_id
        WHERE m.motorista_id = ?
        ORDER BY m.fecha_movimiento DESC, m.id DESC");
    $stmt->execute([$motoristaId]);
    $pedidos = $stmt->fetchAll();
}

$enCurso = array_filter($pedidos, fn($p) => in_array($p['estado'], ['Asignado a motorista', 'En curso'], true));
$terminados = array_filter($pedidos, fn($p) => $p['estado'] === 'Terminado');
$noEntregados = array_filter($pedidos, fn($p) => in_array($p['estado'], ['No entregado', 'Incidencia'], true));

require_once __DIR__ . '/../../includes/header.php';
$flash = get_flash();
?>
<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger border-dark"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="hero-card">
    <h1 class="section-title h2 mb-2">Panel Motorista</h1>
    <p class="mb-0">Permite consultar pedidos asignados, registrar estados de entrega, confirmar pedidos terminados/no entregados y reportar incidencias. Los pedidos cerrados no pueden ser modificados nuevamente.</p>
</section>

<section id="resumen-estados" class="row g-3 mb-4">
    <div class="col-12 col-md-4"><div class="panel-card h-100"><span class="badge badge-logico mb-2">En curso</span><h2 class="h3 fw-bold mb-0"><?= count($enCurso) ?></h2></div></div>
    <div class="col-12 col-md-4"><div class="panel-card h-100"><span class="badge badge-logico mb-2">Terminados / entregados</span><h2 class="h3 fw-bold mb-0"><?= count($terminados) ?></h2></div></div>
    <div class="col-12 col-md-4"><div class="panel-card h-100"><span class="badge badge-logico mb-2">No entregados / incidencias</span><h2 class="h3 fw-bold mb-0"><?= count($noEntregados) ?></h2></div></div>
</section>

<section id="pedidos-asignados" class="panel-card">
    <h2 class="h4 fw-bold mb-3">Pedidos asignados</h2>
    <?php if (!$pedidos): ?>
        <div class="empty-state">No existen pedidos asignados a este motorista.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-logico align-middle mb-0">
                <thead><tr><th>N°/Código pedido</th><th>Cliente / destino</th><th>Dirección</th><th>Origen</th><th>Destino traslado</th><th>Moto</th><th>Estado</th><th>Acción motorista</th></tr></thead>
                <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                    <?php $cerrado = movimiento_cerrado($pedido['estado'] ?? ''); ?>
                    <tr>
                        <td><?= e($pedido['codigo_pedido'] ?? ('PED-' . $pedido['id'])) ?></td>
                        <td><?= e($pedido['cliente_nombre']) ?></td>
                        <td><?= e($pedido['direccion_entrega']) ?></td>
                        <td><?= e(($pedido['farmacia_origen'] ?? '-') . (($pedido['comuna_origen'] ?? '') ? ' / ' . $pedido['comuna_origen'] : '') . (($pedido['region_origen'] ?? '') ? ' / ' . $pedido['region_origen'] : '')) ?></td>
                        <td><?= e(($pedido['farmacia_destino'] ?? '-') . (($pedido['comuna_destino'] ?? '') ? ' / ' . $pedido['comuna_destino'] : '') . (($pedido['region_destino'] ?? '') ? ' / ' . $pedido['region_destino'] : '')) ?></td>
                        <td><?= e($pedido['patente'] ?? '-') ?></td>
                        <td><span class="badge badge-logico"><?= e($pedido['estado']) ?></span></td>
                        <td style="min-width: 360px;">
                            <?php if ($cerrado): ?>
                                <div class="alert alert-secondary border-dark py-2 mb-0">
                                    <strong>Gestión cerrada.</strong><br>
                                    <span class="small">El pedido quedó como <?= e($pedido['estado']) ?> y no puede volver a modificarse.</span>
                                    <?php if (!empty($pedido['incidencia_descripcion'])): ?><div class="small mt-1">Obs.: <?= e($pedido['incidencia_descripcion']) ?></div><?php endif; ?>
                                </div>
                            <?php else: ?>
                                <form method="post" class="row g-2 mb-2">
                                    <input type="hidden" name="action" value="actualizar_estado">
                                    <input type="hidden" name="movimiento_id" value="<?= e((string)$pedido['id']) ?>">
                                    <div class="col-12 col-lg-5">
                                        <select name="estado" class="form-select form-select-sm">
                                            <option value="En curso" <?= selected((string)$pedido['estado'], 'En curso') ?>>En curso</option>
                                            <option value="Terminado" <?= selected((string)$pedido['estado'], 'Terminado') ?>>Terminado / entregado</option>
                                            <option value="No entregado" <?= selected((string)$pedido['estado'], 'No entregado') ?>>No entregado</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-lg-5"><input name="observacion" class="form-control form-control-sm" placeholder="Observación opcional"></div>
                                    <div class="col-12 col-lg-2"><button class="btn btn-sm btn-logico w-100" type="submit">OK</button></div>
                                </form>
                                <form method="post" class="row g-2">
                                    <input type="hidden" name="action" value="reportar_incidencia">
                                    <input type="hidden" name="movimiento_id" value="<?= e((string)$pedido['id']) ?>">
                                    <div class="col-9"><input name="observacion" class="form-control form-control-sm" placeholder="Problema para ubicar cliente, producto dañado, etc."></div>
                                    <div class="col-3"><button class="btn btn-sm btn-outline-danger border-dark w-100" type="submit">Incidencia</button></div>
                                </form>
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
