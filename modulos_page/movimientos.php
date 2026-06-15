<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['Administrador']);

$currentPage = 'movimientos';
$pageTitle = 'Gestionar movimientos';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

$defaultTipo = $_GET['tipo'] ?? 'Directo';
$validTipos = ['Directo', 'Receta', 'Traslado', 'Reenvio'];
if (!in_array($defaultTipo, $validTipos, true)) {
    $defaultTipo = 'Directo';
}

$record = [
    'tipo' => $defaultTipo,
    'farmacia_origen_id' => '',
    'farmacia_destino_id' => '',
    'motorista_id' => '',
    'moto_id' => '',
    'fecha_movimiento' => date('Y-m-d\TH:i'),
    'cliente_nombre' => '',
    'direccion_entrega' => '',
    'telefono_cliente' => '',
    'descripcion' => '',
    'requiere_receta' => $defaultTipo === 'Receta' ? '1' : '0',
    'receta_retirada' => '0',
    'estado' => 'Pendiente local',
    'motivo_anulacion' => '',
];

$farmacias = $pdo->query("SELECT id, codigo, nombre, comuna, provincia, region FROM farmacias WHERE estado = 'Activa' ORDER BY nombre")->fetchAll();
$motoristas = $pdo->query("SELECT id, rut, nombres, apellidos FROM motoristas WHERE estado = 'Activo' ORDER BY nombres, apellidos")->fetchAll();
$motos = $pdo->query("SELECT id, patente, marca, modelo, estado FROM motos WHERE estado <> 'Inactiva' ORDER BY patente")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'anular') {
        $cancelId = (int)($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo_anulacion'] ?? 'Anulación manual desde prototipo');
        $stmt = $pdo->prepare("UPDATE movimientos SET estado = 'Anulado', motivo_anulacion = ? WHERE id = ?");
        $stmt->execute([$motivo, $cancelId]);
        redirect_with('movimientos.php', 'ok', 'Movimiento anulado correctamente.');
    }

    $data = [
        'tipo' => trim($_POST['tipo'] ?? 'Directo'),
        'farmacia_origen_id' => (int)($_POST['farmacia_origen_id'] ?? 0),
        'farmacia_destino_id' => (int)($_POST['farmacia_destino_id'] ?? 0),
        'motorista_id' => (int)($_POST['motorista_id'] ?? 0),
        'moto_id' => (int)($_POST['moto_id'] ?? 0),
        'fecha_movimiento' => trim($_POST['fecha_movimiento'] ?? ''),
        'cliente_nombre' => trim($_POST['cliente_nombre'] ?? ''),
        'direccion_entrega' => trim($_POST['direccion_entrega'] ?? ''),
        'telefono_cliente' => trim($_POST['telefono_cliente'] ?? ''),
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'requiere_receta' => isset($_POST['requiere_receta']) ? 1 : 0,
        'receta_retirada' => isset($_POST['receta_retirada']) ? 1 : 0,
        'estado' => trim($_POST['estado'] ?? 'Pendiente local'),
        'motivo_anulacion' => trim($_POST['motivo_anulacion'] ?? ''),
    ];

    if (!in_array($data['tipo'], $validTipos, true)) { $errors[] = 'Tipo de movimiento inválido.'; }
    if ($data['farmacia_origen_id'] <= 0) { $errors[] = 'Debe seleccionar la farmacia de origen.'; }
    if ($data['motorista_id'] <= 0) { $errors[] = 'Debe seleccionar el motorista.'; }
    if ($data['moto_id'] <= 0) { $errors[] = 'Debe seleccionar la moto.'; }
    $errors = array_merge($errors, validate_required($data, [
        'fecha_movimiento' => 'fecha de movimiento',
        'cliente_nombre' => 'cliente/destinatario',
        'direccion_entrega' => 'dirección de entrega',
    ]));

    if ($data['tipo'] === 'Traslado' && $data['farmacia_destino_id'] <= 0) {
        $errors[] = 'Para un traslado debe seleccionar una farmacia de destino.';
    }
    if ($data['tipo'] === 'Receta') {
        $data['requiere_receta'] = 1;
    }
    if ($data['estado'] === 'Anulado' && $data['motivo_anulacion'] === '') {
        $errors[] = 'Para anular un movimiento debe indicar un motivo.';
    }

    $currentId = (int)($_POST['id'] ?? 0);

    if (!$errors) {
        $fechaSql = str_replace('T', ' ', $data['fecha_movimiento']);
        $farmaciaDestino = $data['farmacia_destino_id'] > 0 ? $data['farmacia_destino_id'] : null;
        $motivoAnulacion = $data['motivo_anulacion'] !== '' ? $data['motivo_anulacion'] : null;

        if ($postAction === 'create') {
            $codigoPedido = 'PED-' . date('YmdHis') . random_int(10, 99);
            $stmt = $pdo->prepare('INSERT INTO movimientos (codigo_pedido, tipo, farmacia_origen_id, farmacia_destino_id, motorista_id, moto_id, fecha_movimiento, cliente_nombre, direccion_entrega, telefono_cliente, descripcion, requiere_receta, receta_retirada, estado, motivo_anulacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $codigoPedido, $data['tipo'], $data['farmacia_origen_id'], $farmaciaDestino, $data['motorista_id'], $data['moto_id'], $fechaSql,
                $data['cliente_nombre'], $data['direccion_entrega'], $data['telefono_cliente'], $data['descripcion'],
                $data['requiere_receta'], $data['receta_retirada'], $data['estado'], $motivoAnulacion,
            ]);
            log_historial_movimiento($pdo, (int)$pdo->lastInsertId(), current_user()['id'] ?? null, null, $data['estado'], 'Movimiento creado desde panel administrador');
            redirect_with('movimientos.php', 'ok', 'Movimiento agregado correctamente.');
        }

        if ($postAction === 'update') {
            $stmt = $pdo->prepare('UPDATE movimientos SET tipo = ?, farmacia_origen_id = ?, farmacia_destino_id = ?, motorista_id = ?, moto_id = ?, fecha_movimiento = ?, cliente_nombre = ?, direccion_entrega = ?, telefono_cliente = ?, descripcion = ?, requiere_receta = ?, receta_retirada = ?, estado = ?, motivo_anulacion = ? WHERE id = ?');
            $stmt->execute([
                $data['tipo'], $data['farmacia_origen_id'], $farmaciaDestino, $data['motorista_id'], $data['moto_id'], $fechaSql,
                $data['cliente_nombre'], $data['direccion_entrega'], $data['telefono_cliente'], $data['descripcion'],
                $data['requiere_receta'], $data['receta_retirada'], $data['estado'], $motivoAnulacion, $currentId,
            ]);
            redirect_with('movimientos.php', 'ok', 'Movimiento modificado correctamente.');
        }
    }

    $record = array_merge($record, $data);
    $record['fecha_movimiento'] = str_replace(' ', 'T', $record['fecha_movimiento']);
    $id = $currentId;
    $action = $postAction === 'update' ? 'edit' : 'new';
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM movimientos WHERE id = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) {
        redirect_with('movimientos.php', 'error', 'No se encontró el movimiento solicitado.');
    }
    $record['fecha_movimiento'] = str_replace(' ', 'T', substr((string)$record['fecha_movimiento'], 0, 16));
}

$q = trim($_GET['q'] ?? '');
$filtroTipo = trim($_GET['filtro_tipo'] ?? '');
$filtroEstado = trim($_GET['filtro_estado'] ?? '');
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(m.cliente_nombre LIKE ? OR m.direccion_entrega LIKE ? OR mt.nombres LIKE ? OR mt.apellidos LIKE ? OR mo.patente LIKE ?)';
    $like = "%{$q}%";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($filtroTipo !== '' && in_array($filtroTipo, $validTipos, true)) {
    $where[] = 'm.tipo = ?';
    $params[] = $filtroTipo;
}
if ($filtroEstado !== '') {
    $where[] = 'm.estado = ?';
    $params[] = $filtroEstado;
}

$sql = "SELECT m.*, fo.nombre AS farmacia_origen, fo.comuna AS comuna_origen, fo.region AS region_origen, fd.nombre AS farmacia_destino, fd.comuna AS comuna_destino, fd.region AS region_destino,
               mt.nombres, mt.apellidos, mo.patente
        FROM movimientos m
        LEFT JOIN farmacias fo ON fo.id = m.farmacia_origen_id
        LEFT JOIN farmacias fd ON fd.id = m.farmacia_destino_id
        LEFT JOIN motoristas mt ON mt.id = m.motorista_id
        LEFT JOIN motos mo ON mo.id = m.moto_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY m.fecha_movimiento DESC, m.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
$flash = get_flash();
?>
<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="section-title h2 mb-1">Gestionar movimientos</h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="movimientos.php?action=new&tipo=Directo" class="btn btn-logico">Directo</a>
        <a href="movimientos.php?action=new&tipo=Receta" class="btn btn-logico">Con receta</a>
        <a href="movimientos.php?action=new&tipo=Traslado" class="btn btn-logico">Traslado</a>
        <a href="movimientos.php?action=new&tipo=Reenvio" class="btn btn-logico">Reenvío</a>
    </div>
</div>

<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger border-dark"><strong>Revise el formulario:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<section class="panel-card mb-4">
    <h2 class="h4 fw-bold mb-3"><?= $action === 'edit' ? 'Modificar movimiento' : 'Agregar movimiento' ?></h2>
    <form method="post" class="row g-3" novalidate>
        <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
        <input type="hidden" name="id" value="<?= e((string)$id) ?>">
        <div class="col-12 col-md-3"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><option <?= selected((string)$record['tipo'], 'Directo') ?>>Directo</option><option <?= selected((string)$record['tipo'], 'Receta') ?>>Receta</option><option <?= selected((string)$record['tipo'], 'Traslado') ?>>Traslado</option><option <?= selected((string)$record['tipo'], 'Reenvio') ?>>Reenvio</option></select></div>
        <div class="col-12 col-md-3"><label class="form-label">Fecha</label><input type="datetime-local" name="fecha_movimiento" class="form-control" value="<?= e((string)$record['fecha_movimiento']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Farmacia origen</label><select name="farmacia_origen_id" class="form-select" required><option value="">Seleccione</option><?php foreach ($farmacias as $farmacia): ?><option value="<?= e((string)$farmacia['id']) ?>" <?= ((int)$record['farmacia_origen_id'] === (int)$farmacia['id']) ? 'selected' : '' ?>><?= e($farmacia['codigo'] . ' - ' . $farmacia['nombre'] . ' / ' . $farmacia['comuna'] . ' / ' . $farmacia['region']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-3"><label class="form-label">Farmacia destino</label><select name="farmacia_destino_id" class="form-select"><option value="">No aplica</option><?php foreach ($farmacias as $farmacia): ?><option value="<?= e((string)$farmacia['id']) ?>" <?= ((int)$record['farmacia_destino_id'] === (int)$farmacia['id']) ? 'selected' : '' ?>><?= e($farmacia['codigo'] . ' - ' . $farmacia['nombre'] . ' / ' . $farmacia['comuna'] . ' / ' . $farmacia['region']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-4"><label class="form-label">Motorista</label><select name="motorista_id" class="form-select" required><option value="">Seleccione</option><?php foreach ($motoristas as $motorista): ?><option value="<?= e((string)$motorista['id']) ?>" <?= ((int)$record['motorista_id'] === (int)$motorista['id']) ? 'selected' : '' ?>><?= e($motorista['rut'] . ' - ' . $motorista['nombres'] . ' ' . $motorista['apellidos']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-4"><label class="form-label">Moto</label><select name="moto_id" class="form-select" required><option value="">Seleccione</option><?php foreach ($motos as $moto): ?><option value="<?= e((string)$moto['id']) ?>" <?= ((int)$record['moto_id'] === (int)$moto['id']) ? 'selected' : '' ?>><?= e($moto['patente'] . ' - ' . $moto['marca'] . ' ' . $moto['modelo']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-4"><label class="form-label">Estado</label><select name="estado" class="form-select"><?php foreach (estados_movimiento() as $estado): ?><option value="<?= e($estado) ?>" <?= selected((string)$record['estado'], $estado) ?>><?= e($estado) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-4"><label class="form-label">Cliente / destinatario</label><input name="cliente_nombre" class="form-control" value="<?= e((string)$record['cliente_nombre']) ?>" required></div>
        <div class="col-12 col-md-5"><label class="form-label">Dirección entrega</label><input name="direccion_entrega" class="form-control" value="<?= e((string)$record['direccion_entrega']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Teléfono</label><input name="telefono_cliente" class="form-control" value="<?= e((string)$record['telefono_cliente']) ?>"></div>
        <div class="col-12"><label class="form-label">Descripción</label><input name="descripcion" class="form-control" value="<?= e((string)$record['descripcion']) ?>" placeholder="Detalle del despacho, traslado o reenvío"></div>
        <div class="col-12 col-md-3"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="requiere_receta" id="requiere_receta" <?= checked((bool)$record['requiere_receta']) ?>><label class="form-check-label" for="requiere_receta">Requiere receta</label></div></div>
        <div class="col-12 col-md-3"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="receta_retirada" id="receta_retirada" <?= checked((bool)$record['receta_retirada']) ?>><label class="form-check-label" for="receta_retirada">Receta retirada</label></div></div>
        <div class="col-12 col-md-6"><label class="form-label">Motivo anulación</label><input name="motivo_anulacion" class="form-control" value="<?= e((string)$record['motivo_anulacion']) ?>"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-logico" type="submit">Guardar</button><a class="btn btn-outline-logico" href="movimientos.php">Cancelar</a></div>
    </form>
</section>
<?php endif; ?>

<section class="panel-card">
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-5"><label class="form-label">Buscar movimiento</label><input type="search" name="q" class="form-control" placeholder="Cliente, dirección, motorista o patente" value="<?= e($q) ?>"></div>
        <div class="col-12 col-md-3"><label class="form-label">Tipo</label><select name="filtro_tipo" class="form-select"><option value="">Todos</option><?php foreach ($validTipos as $tipo): ?><option value="<?= e($tipo) ?>" <?= $filtroTipo === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-2"><label class="form-label">Estado</label><select name="filtro_estado" class="form-select"><option value="">Todos</option><?php foreach (estados_movimiento() as $estado): ?><option value="<?= e($estado) ?>" <?= $filtroEstado === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-logico w-100" type="submit">Filtrar</button><a class="btn btn-outline-logico" href="movimientos.php">Limpiar</a></div>
    </form>
    <?php if (!$rows): ?>
        <div class="empty-state">No existen movimientos para mostrar.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-logico align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Cliente</th><th>Origen</th><th>Destino</th><th>Motorista</th><th>Moto</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e(substr((string)$row['fecha_movimiento'], 0, 16)) ?></td>
                        <td><span class="badge badge-logico"><?= e($row['tipo']) ?></span></td>
                        <td><?= e($row['cliente_nombre']) ?></td>
                        <td><?= e(($row['farmacia_origen'] ?? '-') . (($row['comuna_origen'] ?? '') ? ' / ' . $row['comuna_origen'] : '') . (($row['region_origen'] ?? '') ? ' / ' . $row['region_origen'] : '')) ?></td>
                        <td><?= e(($row['farmacia_destino'] ?? '-') . (($row['comuna_destino'] ?? '') ? ' / ' . $row['comuna_destino'] : '') . (($row['region_destino'] ?? '') ? ' / ' . $row['region_destino'] : '')) ?></td>
                        <td><?= e(trim(($row['nombres'] ?? '') . ' ' . ($row['apellidos'] ?? '')) ?: '-') ?></td>
                        <td><?= e($row['patente'] ?? '-') ?></td>
                        <td><span class="badge badge-logico"><?= e($row['estado']) ?></span></td>
                        <td><div class="action-grid"><a class="btn btn-sm btn-outline-logico" href="movimientos.php?action=edit&id=<?= e((string)$row['id']) ?>">Modificar</a><?php if ($row['estado'] !== 'Anulado'): ?><form method="post" onsubmit="return confirm('¿Anular este movimiento?');"><input type="hidden" name="action" value="anular"><input type="hidden" name="id" value="<?= e((string)$row['id']) ?>"><input type="hidden" name="motivo_anulacion" value="Anulación desde listado"><button class="btn btn-sm btn-outline-danger border-dark" type="submit">Anular</button></form><?php endif; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
