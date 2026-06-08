<?php
/**
 * Panel Operador Control de Despacho.
 * Monitorea movimientos, asigna motorista/moto cuando el local deja el pedido
 * listo para retiro y actualiza estados operativos. Los pedidos cerrados no se
 * modifican para mantener trazabilidad.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['Operador Control Despacho']);

$currentPage = 'inicio';
$pageTitle = 'Panel Operador Control de Despacho';
$user = current_user();
$errors = [];

$motoristas = $pdo->query("SELECT id, farmacia_id, rut, nombres, apellidos FROM motoristas WHERE estado = 'Activo' ORDER BY nombres, apellidos")->fetchAll();
$motos = $pdo->query("SELECT id, motorista_id, patente, marca, modelo FROM motos WHERE estado <> 'Inactiva' ORDER BY patente")->fetchAll();
$farmacias = $pdo->query("SELECT id, codigo, nombre, comuna, provincia, region FROM farmacias WHERE tipo = 'Local' AND estado = 'Activa' ORDER BY region, comuna, nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $movimientoId = (int)($_POST['movimiento_id'] ?? 0);
    $observacion = trim($_POST['observacion'] ?? '');

    $stmt = $pdo->prepare('SELECT id, estado, farmacia_origen_id FROM movimientos WHERE id = ? LIMIT 1');
    $stmt->execute([$movimientoId]);
    $movimiento = $stmt->fetch();

    if (!$movimiento) {
        $errors[] = 'No se encontró el pedido seleccionado.';
    } else {
        $estadoAnterior = (string)$movimiento['estado'];
        $farmaciaOrigenId = (int)$movimiento['farmacia_origen_id'];

        if (movimiento_cerrado($estadoAnterior)) {
            $errors[] = 'Este pedido ya está cerrado y no puede modificarse desde Control de Despacho.';
        }

        if ($postAction === 'asignar_motorista' && !$errors) {
            $motoristaId = (int)($_POST['motorista_id'] ?? 0);
            $motoId = (int)($_POST['moto_id'] ?? 0);

            if ($estadoAnterior !== 'Listo para retiro') {
                $errors[] = 'Solo se puede asignar motorista cuando el local deja el pedido en estado Listo para retiro.';
            }
            if ($motoristaId <= 0) { $errors[] = 'Debe seleccionar un motorista disponible.'; }
            if ($motoId <= 0) { $errors[] = 'Debe seleccionar una moto.'; }

            if (!$errors) {
                $stmt = $pdo->prepare('SELECT id, farmacia_id FROM motoristas WHERE id = ? AND estado = "Activo" LIMIT 1');
                $stmt->execute([$motoristaId]);
                $motorista = $stmt->fetch();
                if (!$motorista) {
                    $errors[] = 'El motorista seleccionado no existe o no está activo.';
                } elseif ((int)$motorista['farmacia_id'] !== $farmaciaOrigenId) {
                    $errors[] = 'El motorista seleccionado no está asociado al local de despacho de este pedido.';
                }
            }

            if (!$errors) {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('UPDATE movimientos SET motorista_id = ?, moto_id = ?, estado = "Asignado a motorista" WHERE id = ?');
                    $stmt->execute([$motoristaId, $motoId, $movimientoId]);
                    $stmt = $pdo->prepare('UPDATE motos SET motorista_id = ?, estado = "En uso" WHERE id = ?');
                    $stmt->execute([$motoristaId, $motoId]);
                    log_historial_movimiento($pdo, $movimientoId, $user['id'], $estadoAnterior, 'Asignado a motorista', $observacion ?: 'Asignación realizada por Control de Despacho');
                    $pdo->commit();
                    redirect_with(app_url('vistas_rol/operador_control/index.php'), 'ok', 'Pedido asignado al motorista correctamente.');
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $errors[] = 'No fue posible asignar el pedido. Revise los datos seleccionados.';
                }
            }
        }

        if ($postAction === 'actualizar_estado' && !$errors) {
            $nuevoEstado = trim($_POST['estado'] ?? '');
            if (!in_array($nuevoEstado, estados_movimiento(), true)) {
                $errors[] = 'Estado seleccionado no válido.';
            }
            if (!$errors) {
                $fechaEntregaSql = in_array($nuevoEstado, ['Terminado', 'No entregado'], true) ? ', fecha_entrega = NOW()' : '';
                $stmt = $pdo->prepare("UPDATE movimientos SET estado = ?, incidencia_descripcion = IF(? <> '', ?, incidencia_descripcion) {$fechaEntregaSql} WHERE id = ?");
                $stmt->execute([$nuevoEstado, $observacion, $observacion, $movimientoId]);
                log_historial_movimiento($pdo, $movimientoId, $user['id'], $estadoAnterior, $nuevoEstado, $observacion ?: 'Actualización desde Control de Despacho');
                redirect_with(app_url('vistas_rol/operador_control/index.php'), 'ok', 'Estado actualizado correctamente.');
            }
        }
    }
}

$filtroFarmacia = (int)($_GET['farmacia_id'] ?? 0);
$filtroMotorista = (int)($_GET['motorista_id'] ?? 0);
$filtroTipo = trim($_GET['tipo'] ?? '');
$filtroEstado = trim($_GET['estado'] ?? '');
$where = [];
$params = [];

if ($filtroFarmacia > 0) { $where[] = 'm.farmacia_origen_id = ?'; $params[] = $filtroFarmacia; }
if ($filtroMotorista > 0) { $where[] = 'm.motorista_id = ?'; $params[] = $filtroMotorista; }
if (in_array($filtroTipo, ['Directo', 'Receta', 'Traslado', 'Reenvio'], true)) { $where[] = 'm.tipo = ?'; $params[] = $filtroTipo; }
if ($filtroEstado !== '' && in_array($filtroEstado, estados_movimiento(), true)) { $where[] = 'm.estado = ?'; $params[] = $filtroEstado; }

$sql = "SELECT m.*, fo.nombre AS local_despacho, fo.comuna AS comuna_origen, fo.provincia AS provincia_origen, fo.region AS region_origen,
               fd.nombre AS local_destino, fd.comuna AS comuna_destino, fd.provincia AS provincia_destino, fd.region AS region_destino,
               CONCAT(mt.nombres, ' ', mt.apellidos) AS motorista, mo.patente
        FROM movimientos m
        LEFT JOIN farmacias fo ON fo.id = m.farmacia_origen_id
        LEFT JOIN farmacias fd ON fd.id = m.farmacia_destino_id
        LEFT JOIN motoristas mt ON mt.id = m.motorista_id
        LEFT JOIN motos mo ON mo.id = m.moto_id";
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY m.fecha_movimiento DESC, m.id DESC LIMIT 150';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$conteo = [];
foreach (estados_movimiento() as $estado) { $conteo[$estado] = 0; }
foreach ($pedidos as $pedido) { $conteo[$pedido['estado']] = ($conteo[$pedido['estado']] ?? 0) + 1; }

$resumenLocales = $pdo->query("SELECT fo.nombre, fo.comuna, fo.provincia, fo.region, COUNT(m.id) AS total
    FROM farmacias fo
    LEFT JOIN movimientos m ON m.farmacia_origen_id = fo.id
    WHERE fo.tipo = 'Local' AND fo.estado = 'Activa'
    GROUP BY fo.id, fo.nombre, fo.comuna, fo.provincia, fo.region
    ORDER BY fo.region, fo.comuna")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
$flash = get_flash();
?>
<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger border-dark"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="hero-card">
    <h1 class="section-title h2 mb-2">Panel Operador Control de Despacho</h1>
    <p class="mb-0">Monitorea movimientos por local, tipo, motorista y estado. La asignación a motorista se realiza cuando el local confirma disponibilidad y deja el pedido listo para retiro.</p>
</section>

<section class="row g-3 mb-4">
    <?php foreach ($resumenLocales as $local): ?>
        <div class="col-12 col-md-4"><div class="panel-card h-100"><span class="badge badge-logico mb-2"><?= e($local['comuna'] . ' / ' . $local['region']) ?></span><h2 class="h6 fw-bold mb-1"><?= e($local['nombre']) ?></h2><p class="mb-0"><strong><?= e((string)$local['total']) ?></strong> movimiento(s) registrados</p></div></div>
    <?php endforeach; ?>
</section>

<section id="monitoreo" class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="panel-card h-100"><span class="badge badge-logico mb-2">Pendiente local</span><h2 class="h3 fw-bold mb-0"><?= (int)($conteo['Pendiente local'] ?? 0) ?></h2></div></div>
    <div class="col-6 col-md-3"><div class="panel-card h-100"><span class="badge badge-logico mb-2">Listo retiro</span><h2 class="h3 fw-bold mb-0"><?= (int)($conteo['Listo para retiro'] ?? 0) ?></h2></div></div>
    <div class="col-6 col-md-3"><div class="panel-card h-100"><span class="badge badge-logico mb-2">En curso</span><h2 class="h3 fw-bold mb-0"><?= (int)($conteo['En curso'] ?? 0) ?></h2></div></div>
    <div class="col-6 col-md-3"><div class="panel-card h-100"><span class="badge badge-logico mb-2">Incidencias</span><h2 class="h3 fw-bold mb-0"><?= (int)($conteo['Incidencia'] ?? 0) ?></h2></div></div>
</section>

<section id="asignar-motorista" class="panel-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <div><h2 class="h4 fw-bold mb-1">Listado general de movimientos</h2><p class="mb-0">Incluye local, tipo de movimiento, motorista, moto y estado operativo.</p></div>
        <a class="btn btn-outline-logico" href="<?= e(app_url('modulos_page/reportes.php')) ?>">Generar reportes</a>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-3"><label class="form-label">Farmacia/local</label><select name="farmacia_id" class="form-select"><option value="0">Todos los locales</option><?php foreach ($farmacias as $f): ?><option value="<?= e((string)$f['id']) ?>" <?= $filtroFarmacia === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['region'] . ' / ' . $f['comuna'] . ' - ' . $f['nombre']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-3"><label class="form-label">Motorista</label><select name="motorista_id" class="form-select"><option value="0">Todos los motoristas</option><?php foreach ($motoristas as $m): ?><option value="<?= e((string)$m['id']) ?>" <?= $filtroMotorista === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nombres'] . ' ' . $m['apellidos']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-2"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><option value="">Todos</option><?php foreach (['Directo','Receta','Traslado','Reenvio'] as $tipo): ?><option value="<?= e($tipo) ?>" <?= $filtroTipo === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-2"><label class="form-label">Estado</label><select name="estado" class="form-select"><option value="">Todos</option><?php foreach (estados_movimiento() as $estado): ?><option value="<?= e($estado) ?>" <?= $filtroEstado === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-logico w-100" type="submit">Filtrar</button><a class="btn btn-outline-logico" href="<?= e(app_url('vistas_rol/operador_control/index.php')) ?>">Limpiar</a></div>
    </form>

    <div class="table-responsive">
        <table class="table table-logico align-middle mb-0">
            <thead><tr><th>Fecha</th><th>N°/Código pedido</th><th>Tipo</th><th>Local origen</th><th>Destino traslado</th><th>Cliente</th><th>Estado</th><th>Motorista</th><th>Moto</th><th>Asignar / actualizar</th></tr></thead>
            <tbody>
            <?php foreach ($pedidos as $pedido): ?>
                <?php
                    $cerrado = movimiento_cerrado($pedido['estado'] ?? '');
                    $motoristasLocal = array_values(array_filter($motoristas, fn($m) => (int)$m['farmacia_id'] === (int)$pedido['farmacia_origen_id']));
                    $motosLocal = array_values(array_filter($motos, fn($moto) => empty($moto['motorista_id']) || in_array((int)$moto['motorista_id'], array_map(fn($m) => (int)$m['id'], $motoristasLocal), true)));
                ?>
                <tr>
                    <td><?= e(substr((string)$pedido['fecha_movimiento'], 0, 16)) ?></td>
                    <td><?= e($pedido['codigo_pedido'] ?? ('PED-' . $pedido['id'])) ?></td>
                    <td><span class="badge badge-logico"><?= e($pedido['tipo']) ?></span></td>
                    <td><?= e(($pedido['local_despacho'] ?? '-') . ' / ' . ($pedido['comuna_origen'] ?? '-') . ' / ' . ($pedido['region_origen'] ?? '-')) ?></td>
                    <td><?= e(($pedido['local_destino'] ?? '-') . (($pedido['comuna_destino'] ?? '') ? ' / ' . $pedido['comuna_destino'] : '') . (($pedido['region_destino'] ?? '') ? ' / ' . $pedido['region_destino'] : '')) ?></td>
                    <td><?= e($pedido['cliente_nombre']) ?></td>
                    <td><span class="badge badge-logico"><?= e($pedido['estado']) ?></span></td>
                    <td><?= e($pedido['motorista'] ?? '-') ?></td>
                    <td><?= e($pedido['patente'] ?? '-') ?></td>
                    <td style="min-width: 450px;">
                        <?php if ($cerrado): ?>
                            <div class="alert alert-secondary border-dark py-2 mb-0"><strong>Pedido cerrado.</strong><br><span class="small">No admite nueva asignación ni cambio de estado.</span></div>
                        <?php else: ?>
                            <?php if ($pedido['estado'] === 'Listo para retiro'): ?>
                                <form method="post" class="row g-2 mb-2">
                                    <input type="hidden" name="action" value="asignar_motorista">
                                    <input type="hidden" name="movimiento_id" value="<?= e((string)$pedido['id']) ?>">
                                    <div class="col-12 col-lg-4"><select name="motorista_id" class="form-select form-select-sm"><option value="">Motorista del local</option><?php foreach ($motoristasLocal as $m): ?><option value="<?= e((string)$m['id']) ?>"><?= e($m['nombres'] . ' ' . $m['apellidos']) ?></option><?php endforeach; ?></select></div>
                                    <div class="col-12 col-lg-3"><select name="moto_id" class="form-select form-select-sm"><option value="">Moto</option><?php foreach ($motosLocal as $moto): ?><option value="<?= e((string)$moto['id']) ?>"><?= e($moto['patente']) ?></option><?php endforeach; ?></select></div>
                                    <div class="col-12 col-lg-3"><input name="observacion" class="form-control form-control-sm" placeholder="Obs."></div>
                                    <div class="col-12 col-lg-2"><button class="btn btn-sm btn-logico w-100" type="submit">Asignar</button></div>
                                </form>
                            <?php else: ?>
                                <div class="small text-muted mb-2">Asignación disponible cuando el local deje el pedido en estado <strong>Listo para retiro</strong>.</div>
                            <?php endif; ?>
                            <form method="post" class="row g-2">
                                <input type="hidden" name="action" value="actualizar_estado">
                                <input type="hidden" name="movimiento_id" value="<?= e((string)$pedido['id']) ?>">
                                <div class="col-12 col-lg-4"><select name="estado" class="form-select form-select-sm"><?php foreach (estados_movimiento() as $estado): ?><option value="<?= e($estado) ?>" <?= selected((string)$pedido['estado'], $estado) ?>><?= e($estado) ?></option><?php endforeach; ?></select></div>
                                <div class="col-12 col-lg-6"><input name="observacion" class="form-control form-control-sm" placeholder="Observación"></div>
                                <div class="col-12 col-lg-2"><button class="btn btn-sm btn-outline-logico w-100" type="submit">Guardar</button></div>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
