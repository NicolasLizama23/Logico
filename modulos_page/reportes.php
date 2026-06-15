<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['Administrador', 'Operador Control Despacho']);

$currentPage = 'reportes';
$pageTitle = 'Reportes';

$scope = $_GET['scope'] ?? 'diario';
if (!in_array($scope, ['diario', 'mensual', 'anual'], true)) {
    $scope = 'diario';
}

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$mes = $_GET['mes'] ?? date('Y-m');
$anio = $_GET['anio'] ?? date('Y');

$params = [];
$where = '';
$title = '';

if ($scope === 'diario') {
    $where = 'DATE(m.fecha_movimiento) = ?';
    $params[] = $fecha;
    $title = 'Reporte diario ' . $fecha;
} elseif ($scope === 'mensual') {
    $where = "DATE_FORMAT(m.fecha_movimiento, '%Y-%m') = ?";
    $params[] = $mes;
    $title = 'Reporte mensual ' . $mes;
} else {
    $where = 'YEAR(m.fecha_movimiento) = ?';
    $params[] = (int)$anio;
    $title = 'Reporte anual ' . $anio;
}

$stmt = $pdo->prepare("SELECT m.tipo, m.estado, COUNT(*) AS total
    FROM movimientos m
    WHERE {$where}
    GROUP BY m.tipo, m.estado
    ORDER BY m.tipo, m.estado");
$stmt->execute($params);
$resumen = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT m.*, fo.nombre AS farmacia_origen, fd.nombre AS farmacia_destino,
        mt.nombres, mt.apellidos, mo.patente
    FROM movimientos m
    LEFT JOIN farmacias fo ON fo.id = m.farmacia_origen_id
    LEFT JOIN farmacias fd ON fd.id = m.farmacia_destino_id
    LEFT JOIN motoristas mt ON mt.id = m.motorista_id
    LEFT JOIN motos mo ON mo.id = m.moto_id
    WHERE {$where}
    ORDER BY m.fecha_movimiento DESC, m.id DESC");
$stmt->execute($params);
$detalle = $stmt->fetchAll();

$totales = [
    'total' => count($detalle),
    'entregados' => 0,
    'anulados' => 0,
    'reenviados' => 0,
    'en_ruta' => 0,
];
foreach ($detalle as $row) {
    if (in_array($row['estado'], ['Terminado','Entregado'], true)) { $totales['entregados']++; }
    if ($row['estado'] === 'Anulado') { $totales['anulados']++; }
    if ($row['estado'] === 'Reenviado') { $totales['reenviados']++; }
    if (in_array($row['estado'], ['En curso','En ruta'], true)) { $totales['en_ruta']++; }
}
$porcentajeEntregados = $totales['total'] > 0 ? round(($totales['entregados'] / $totales['total']) * 100, 1) : 0;

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="section-title h2 mb-1">Reportes de movimientos</h1>
    </div>
</div>

<section class="panel-card mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-12 col-md-3"><label class="form-label">Tipo de reporte</label><select name="scope" class="form-select"><option value="diario" <?= $scope === 'diario' ? 'selected' : '' ?>>Diario</option><option value="mensual" <?= $scope === 'mensual' ? 'selected' : '' ?>>Mensual</option><option value="anual" <?= $scope === 'anual' ? 'selected' : '' ?>>Anual</option></select></div>
        <div class="col-12 col-md-3"><label class="form-label">Fecha diaria</label><input type="date" name="fecha" class="form-control" value="<?= e($fecha) ?>"></div>
        <div class="col-12 col-md-3"><label class="form-label">Mes</label><input type="month" name="mes" class="form-control" value="<?= e($mes) ?>"></div>
        <div class="col-12 col-md-2"><label class="form-label">Año</label><input type="number" name="anio" class="form-control" value="<?= e((string)$anio) ?>" min="2020" max="2035"></div>
        <div class="col-12 col-md-1"><button class="btn btn-logico w-100" type="submit">Ver</button></div>
    </form>
</section>

<section class="panel-card mb-4">
    <h2 class="h4 fw-bold mb-3"><?= e($title) ?></h2>
    <div class="row g-3">
        <div class="col-6 col-lg-3"><div class="metric-card"><span>Total movimientos</span><strong><?= e((string)$totales['total']) ?></strong></div></div>
        <div class="col-6 col-lg-3"><div class="metric-card"><span>Entregados</span><strong><?= e((string)$totales['entregados']) ?></strong></div></div>
        <div class="col-6 col-lg-3"><div class="metric-card"><span>Anulados/Reenviados</span><strong><?= e((string)($totales['anulados'] + $totales['reenviados'])) ?></strong></div></div>
        <div class="col-6 col-lg-3"><div class="metric-card"><span>% entregados</span><strong><?= e((string)$porcentajeEntregados) ?>%</strong></div></div>
    </div>
</section>

<section class="panel-card mb-4">
    <h2 class="h4 fw-bold mb-3">Resumen por tipo y estado</h2>
    <?php if (!$resumen): ?>
        <div class="empty-state">No existen datos para el período seleccionado.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-logico align-middle mb-0">
                <thead><tr><th>Tipo</th><th>Estado</th><th>Total</th></tr></thead>
                <tbody><?php foreach ($resumen as $row): ?><tr><td><?= e($row['tipo']) ?></td><td><?= e($row['estado']) ?></td><td><?= e((string)$row['total']) ?></td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel-card">
    <h2 class="h4 fw-bold mb-3">Detalle del período</h2>
    <?php if (!$detalle): ?>
        <div class="empty-state">No existen movimientos para mostrar.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-logico align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Cliente</th><th>Origen</th><th>Destino</th><th>Motorista</th><th>Moto</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($detalle as $row): ?>
                    <tr>
                        <td><?= e(substr((string)$row['fecha_movimiento'], 0, 16)) ?></td>
                        <td><?= e($row['tipo']) ?></td>
                        <td><?= e($row['cliente_nombre']) ?></td>
                        <td><?= e($row['farmacia_origen'] ?? '-') ?></td>
                        <td><?= e($row['farmacia_destino'] ?? '-') ?></td>
                        <td><?= e(trim(($row['nombres'] ?? '') . ' ' . ($row['apellidos'] ?? '')) ?: '-') ?></td>
                        <td><?= e($row['patente'] ?? '-') ?></td>
                        <td><span class="badge badge-logico"><?= e($row['estado']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
