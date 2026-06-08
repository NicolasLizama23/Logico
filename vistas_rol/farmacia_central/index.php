<?php
/**
 * Panel Farmacia Central.
 * Permite registrar órdenes de despacho con un código externo de pedido,
 * asignarlas a un local de despacho y gestionar traslados cuando el local
 * inicialmente asignado informa falta de stock.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['Farmacia Central']);

$currentPage = 'inicio';
$pageTitle = 'Panel Farmacia Central';
$user = current_user();
$errors = [];

$locales = $pdo->query("SELECT id, codigo, nombre, direccion, comuna, provincia, region FROM farmacias WHERE tipo = 'Local' AND estado = 'Activa' ORDER BY region, comuna, nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? 'crear_orden';

    if ($postAction === 'crear_orden') {
        $tipo = trim($_POST['tipo'] ?? 'Directo');
        $farmaciaOrigenId = (int)($_POST['farmacia_origen_id'] ?? 0);
        $farmaciaDestinoId = (int)($_POST['farmacia_destino_id'] ?? 0);
        $codigoPedido = strtoupper(trim($_POST['codigo_pedido'] ?? ''));
        $clienteNombre = trim($_POST['cliente_nombre'] ?? '');
        $direccionEntrega = trim($_POST['direccion_entrega'] ?? '');
        $telefonoCliente = trim($_POST['telefono_cliente'] ?? '');
        $requiereReceta = isset($_POST['requiere_receta']) ? 1 : 0;

        if (!in_array($tipo, ['Directo', 'Receta', 'Traslado', 'Reenvio'], true)) {
            $errors[] = 'Tipo de pedido inválido.';
        }
        if ($farmaciaOrigenId <= 0) {
            $errors[] = 'Debe seleccionar el local de despacho asignado.';
        }
        if ($tipo === 'Traslado' && $farmaciaDestinoId <= 0) {
            $errors[] = 'Para registrar un traslado inicial debe seleccionar local de destino.';
        }
        if ($farmaciaDestinoId > 0 && $farmaciaDestinoId === $farmaciaOrigenId) {
            $errors[] = 'El local de despacho asignado y el local destino para traslado no pueden ser el mismo.';
        }
        if ($codigoPedido === '') {
            $errors[] = 'Debe ingresar el número o código del pedido externo.';
        } elseif (strlen($codigoPedido) > 30) {
            $errors[] = 'El código del pedido no puede superar 30 caracteres.';
        } elseif (!preg_match('/^[A-Z0-9\-_.]+$/', $codigoPedido)) {
            $errors[] = 'El código del pedido solo puede contener letras, números, guiones, puntos o guion bajo.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM movimientos WHERE codigo_pedido = ?');
            $stmt->execute([$codigoPedido]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Ya existe una orden registrada con ese código de pedido.';
            }
        }

        $errors = array_merge($errors, validate_required([
            'cliente_nombre' => $clienteNombre,
            'direccion_entrega' => $direccionEntrega,
        ], [
            'cliente_nombre' => 'cliente o destinatario',
            'direccion_entrega' => 'dirección de entrega',
        ]));

        if (!$errors) {
            if ($tipo === 'Receta') {
                $requiereReceta = 1;
            }
            $farmaciaDestino = $tipo === 'Traslado' ? $farmaciaDestinoId : null;
            $descripcion = 'Orden externa registrada desde Farmacia Central. LogiCo solo controla despacho, estados y movimientos; no administra el detalle de productos.';
            $stmt = $pdo->prepare('INSERT INTO movimientos (codigo_pedido, tipo, farmacia_origen_id, farmacia_destino_id, fecha_movimiento, cliente_nombre, direccion_entrega, telefono_cliente, descripcion, requiere_receta, estado, disponibilidad_producto, creado_por_usuario_id) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, "Pendiente local", "Pendiente", ?)');
            $stmt->execute([$codigoPedido, $tipo, $farmaciaOrigenId, $farmaciaDestino, $clienteNombre, $direccionEntrega, $telefonoCliente ?: null, $descripcion, $requiereReceta, $user['id']]);
            $movimientoId = (int)$pdo->lastInsertId();
            log_historial_movimiento($pdo, $movimientoId, $user['id'], null, 'Pendiente local', 'Orden generada desde Farmacia Central y enviada al local de despacho. Código externo: ' . $codigoPedido);
            redirect_with(app_url('vistas_rol/farmacia_central/index.php'), 'ok', 'Orden de despacho generada y asignada al local seleccionado.');
        }
    }

    if ($postAction === 'programar_traslado') {
        $movimientoId = (int)($_POST['movimiento_id'] ?? 0);
        $nuevoLocalOrigenId = (int)($_POST['nuevo_local_origen_id'] ?? 0);
        $localDestinoId = (int)($_POST['local_destino_id'] ?? 0);
        $observacion = trim($_POST['observacion_traslado'] ?? '');

        if ($movimientoId <= 0) {
            $errors[] = 'Debe seleccionar un pedido válido para generar el traslado.';
        }
        if ($nuevoLocalOrigenId <= 0) {
            $errors[] = 'Debe seleccionar el nuevo local de despacho que cuenta con stock.';
        }
        if ($localDestinoId <= 0) {
            $errors[] = 'Debe seleccionar el local destino que recibirá el medicamento.';
        }
        if ($nuevoLocalOrigenId > 0 && $nuevoLocalOrigenId === $localDestinoId) {
            $errors[] = 'El local origen del traslado y el local destino no pueden ser el mismo.';
        }

        $stmt = $pdo->prepare('SELECT id, estado, disponibilidad_producto, farmacia_origen_id, farmacia_destino_id, codigo_pedido FROM movimientos WHERE id = ? LIMIT 1');
        $stmt->execute([$movimientoId]);
        $pedidoActual = $stmt->fetch();

        if (!$pedidoActual) {
            $errors[] = 'El pedido seleccionado no existe.';
        } elseif (movimiento_cerrado($pedidoActual['estado'])) {
            $errors[] = 'No se puede modificar el traslado de un pedido cerrado.';
        } elseif (($pedidoActual['disponibilidad_producto'] ?? '') !== 'No disponible' && ($pedidoActual['estado'] ?? '') !== 'Producto no disponible') {
            $errors[] = 'Solo se puede programar traslado cuando el local informa producto no disponible.';
        }

        if (!$errors) {
            $estadoAnterior = (string)$pedidoActual['estado'];
            $nota = $observacion ?: 'Farmacia Central modifica local de despacho y destino por falta de stock en el local inicialmente asignado.';

            $stmt = $pdo->prepare('UPDATE movimientos
                SET tipo = "Traslado",
                    farmacia_origen_id = ?,
                    farmacia_destino_id = ?,
                    motorista_id = NULL,
                    moto_id = NULL,
                    estado = "Pendiente local",
                    disponibilidad_producto = "Pendiente",
                    incidencia_descripcion = NULL,
                    descripcion = CONCAT(COALESCE(descripcion, ""), " | Traslado por falta de stock: ", ?)
                WHERE id = ?');
            $stmt->execute([$nuevoLocalOrigenId, $localDestinoId, $nota, $movimientoId]);

            log_historial_movimiento($pdo, $movimientoId, $user['id'], $estadoAnterior, 'Pendiente local', $nota);
            redirect_with(app_url('vistas_rol/farmacia_central/index.php#estado-pedidos'), 'ok', 'Se modificó el local de despacho y el destino para registrar el traslado de medicamento.');
        }
    }
}

$stmt = $pdo->query("SELECT m.*, fo.nombre AS local_despacho, fo.comuna, fo.provincia, fo.region,
    fd.nombre AS local_destino, fd.comuna AS comuna_destino, fd.provincia AS provincia_destino, fd.region AS region_destino,
    CONCAT(mt.nombres, ' ', mt.apellidos) AS motorista, mo.patente
    FROM movimientos m
    LEFT JOIN farmacias fo ON fo.id = m.farmacia_origen_id
    LEFT JOIN farmacias fd ON fd.id = m.farmacia_destino_id
    LEFT JOIN motoristas mt ON mt.id = m.motorista_id
    LEFT JOIN motos mo ON mo.id = m.moto_id
    ORDER BY m.fecha_movimiento DESC, m.id DESC
    LIMIT 100");
$pedidos = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
$flash = get_flash();
?>
<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger border-dark"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="hero-card">
    <h1 class="section-title h2 mb-2">Panel Farmacia Central</h1>
    <p class="mb-0">Genera órdenes de despacho, selecciona el local de despacho asignado y consulta el estado del pedido. LogiCo controla el flujo logístico, no el detalle clínico o comercial de los productos.</p>
</section>

<section id="generar-orden" class="panel-card mb-4">
    <h2 class="h4 fw-bold mb-3">Generar orden de despacho</h2>
    <form method="post" class="row g-3" novalidate>
        <input type="hidden" name="action" value="crear_orden">
        <div class="col-12 col-md-3"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><option>Directo</option><option>Receta</option><option>Traslado</option><option>Reenvio</option></select></div>
        <div class="col-12 col-md-5"><label class="form-label">Local de despacho asignado</label><select name="farmacia_origen_id" class="form-select" required><option value="">Seleccione local cercano</option><?php foreach ($locales as $local): ?><option value="<?= e((string)$local['id']) ?>"><?= e($local['codigo'] . ' - ' . $local['nombre'] . ' / ' . $local['comuna'] . ' / ' . $local['region']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-4"><label class="form-label">Local destino para traslado</label><select name="farmacia_destino_id" class="form-select"><option value="">No aplica</option><?php foreach ($locales as $local): ?><option value="<?= e((string)$local['id']) ?>"><?= e($local['codigo'] . ' - ' . $local['nombre'] . ' / ' . $local['comuna'] . ' / ' . $local['region']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-4"><label class="form-label">Cliente / destinatario</label><input name="cliente_nombre" class="form-control" required></div>
        <div class="col-12 col-md-5"><label class="form-label">Dirección cliente</label><input name="direccion_entrega" class="form-control" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Teléfono</label><input name="telefono_cliente" class="form-control"></div>
        <div class="col-12 col-md-3"><label class="form-label">Receta</label><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="requiere_receta" id="requiere_receta"><label class="form-check-label" for="requiere_receta">Requiere receta</label></div></div>
        <div class="col-12 col-md-9"><label class="form-label">N° / código del pedido</label><input name="codigo_pedido" class="form-control" placeholder="Ej: PED-EXT-10235" maxlength="30" required><div class="form-text">Este código corresponde al pedido externo que maneja Farmacia Central o el local. LogiCo no administra el detalle de productos solicitados.</div></div>
        <div class="col-12"><button class="btn btn-logico" type="submit">Generar orden y enviar al local</button></div>
    </form>
</section>

<section id="estado-pedidos" class="panel-card">
    <h2 class="h4 fw-bold mb-3">Consultar estado de pedidos</h2>
    <p class="small mb-3">Cuando el local informa <strong>Producto no disponible</strong>, Farmacia Central puede modificar el <strong>local de despacho asignado</strong> y el <strong>local destino para traslado</strong>. De esta forma el movimiento queda registrado como traslado de medicamento entre locales.</p>
    <div class="table-responsive">
        <table class="table table-logico align-middle mb-0">
            <thead><tr><th>Fecha</th><th>N°/Código pedido</th><th>Tipo</th><th>Cliente</th><th>Local despacho asignado</th><th>Local destino traslado</th><th>Motorista</th><th>Moto</th><th>Disponibilidad</th><th>Estado</th><th>Gestión traslado</th></tr></thead>
            <tbody>
            <?php foreach ($pedidos as $pedido): ?>
                <?php $permiteTraslado = !movimiento_cerrado($pedido['estado'] ?? '') && ((($pedido['disponibilidad_producto'] ?? '') === 'No disponible') || (($pedido['estado'] ?? '') === 'Producto no disponible')); ?>
                <tr>
                    <td><?= e(substr((string)$pedido['fecha_movimiento'], 0, 16)) ?></td>
                    <td><?= e($pedido['codigo_pedido'] ?? ('PED-' . $pedido['id'])) ?></td>
                    <td><span class="badge badge-logico"><?= e($pedido['tipo']) ?></span></td>
                    <td><?= e($pedido['cliente_nombre']) ?></td>
                    <td><?= e(($pedido['local_despacho'] ?? '-') . ' / ' . ($pedido['comuna'] ?? '-') . ' / ' . ($pedido['region'] ?? '-')) ?></td>
                    <td><?= e(($pedido['local_destino'] ?? '-') . (($pedido['comuna_destino'] ?? '') ? ' / ' . $pedido['comuna_destino'] : '') . (($pedido['region_destino'] ?? '') ? ' / ' . $pedido['region_destino'] : '')) ?></td>
                    <td><?= e($pedido['motorista'] ?? '-') ?></td>
                    <td><?= e($pedido['patente'] ?? '-') ?></td>
                    <td><?= e($pedido['disponibilidad_producto'] ?? 'Pendiente') ?></td>
                    <td><span class="badge badge-logico"><?= e($pedido['estado']) ?></span></td>
                    <td style="min-width: 430px;">
                        <?php if ($pedido['incidencia_descripcion']): ?><div class="small mb-2"><?= e($pedido['incidencia_descripcion']) ?></div><?php endif; ?>

                        <?php if ($permiteTraslado): ?>
                            <form method="post" class="border rounded p-2 bg-white">
                                <input type="hidden" name="action" value="programar_traslado">
                                <input type="hidden" name="movimiento_id" value="<?= e((string)$pedido['id']) ?>">
                                <div class="fw-bold small mb-2">Modificar local de despacho y destino por falta de stock</div>
                                <div class="row g-2">
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label small mb-1">Nuevo local de despacho con stock</label>
                                        <select name="nuevo_local_origen_id" class="form-select form-select-sm" required>
                                            <option value="">Seleccione local con stock</option>
                                            <?php foreach ($locales as $local): ?>
                                                <?php if ((int)$local['id'] !== (int)$pedido['farmacia_origen_id']): ?>
                                                    <option value="<?= e((string)$local['id']) ?>"><?= e($local['codigo'] . ' - ' . $local['nombre'] . ' / ' . $local['comuna']) ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label small mb-1">Local destino que recibirá producto</label>
                                        <select name="local_destino_id" class="form-select form-select-sm" required>
                                            <option value="">Seleccione destino</option>
                                            <?php foreach ($locales as $local): ?>
                                                <option value="<?= e((string)$local['id']) ?>" <?= ((int)$local['id'] === (int)$pedido['farmacia_origen_id']) ? 'selected' : '' ?>><?= e($local['codigo'] . ' - ' . $local['nombre'] . ' / ' . $local['comuna']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12"><input name="observacion_traslado" class="form-control form-control-sm" placeholder="Observación: stock disponible en otro local, traslado solicitado, etc."></div>
                                    <div class="col-12"><button class="btn btn-sm btn-logico" type="submit">Registrar traslado</button></div>
                                </div>
                            </form>
                        <?php elseif (movimiento_cerrado($pedido['estado'] ?? '')): ?>
                            <span class="badge bg-secondary">Pedido cerrado</span>
                        <?php else: ?>
                            <span class="small text-muted">Sin gestión de traslado pendiente.</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
