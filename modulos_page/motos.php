<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['Administrador']);

$currentPage = 'motos';
$pageTitle = 'Gestionar motos';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$record = ['patente' => '', 'marca' => '', 'modelo' => '', 'anio' => '', 'estado' => 'Disponible'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $data = [
        'patente' => strtoupper(trim($_POST['patente'] ?? '')),
        'marca' => trim($_POST['marca'] ?? ''),
        'modelo' => trim($_POST['modelo'] ?? ''),
        'anio' => trim($_POST['anio'] ?? ''),
        'estado' => trim($_POST['estado'] ?? 'Disponible'),
    ];

    if ($postAction === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM motos WHERE id = ?');
        $stmt->execute([$deleteId]);
        redirect_with('motos.php', 'ok', 'Moto eliminada correctamente.');
    }

    $errors = validate_required($data, [
        'patente' => 'patente',
        'marca' => 'marca',
        'modelo' => 'modelo',
        'anio' => 'año',
    ]);

    if ($data['anio'] !== '' && (!ctype_digit($data['anio']) || (int)$data['anio'] < 1990 || (int)$data['anio'] > 2035)) {
        $errors[] = 'El año debe ser numérico y estar entre 1990 y 2035.';
    }

    $currentId = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM motos WHERE patente = ? AND id <> ?');
    $stmt->execute([$data['patente'], $currentId]);
    if ((int)$stmt->fetchColumn() > 0) {
        $errors[] = 'Ya existe una moto registrada con la misma patente.';
    }

    if (!$errors) {
        if ($postAction === 'create') {
            $stmt = $pdo->prepare('INSERT INTO motos (patente, marca, modelo, anio, estado) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$data['patente'], $data['marca'], $data['modelo'], (int)$data['anio'], $data['estado']]);
            redirect_with('motos.php', 'ok', 'Moto agregada correctamente.');
        }
        if ($postAction === 'update') {
            $stmt = $pdo->prepare('UPDATE motos SET patente = ?, marca = ?, modelo = ?, anio = ?, estado = ? WHERE id = ?');
            $stmt->execute([$data['patente'], $data['marca'], $data['modelo'], (int)$data['anio'], $data['estado'], $currentId]);
            redirect_with('motos.php', 'ok', 'Moto modificada correctamente.');
        }
    }
    $record = $data;
    $id = $currentId;
    $action = $postAction === 'update' ? 'edit' : 'new';
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM motos WHERE id = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) {
        redirect_with('motos.php', 'error', 'No se encontró la moto solicitada.');
    }
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM motos WHERE patente LIKE ? OR marca LIKE ? OR modelo LIKE ? OR estado LIKE ? ORDER BY id DESC');
    $like = "%{$search}%";
    $stmt->execute([$like, $like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM motos ORDER BY id DESC');
}
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
$flash = get_flash();
?>
<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="section-title h2 mb-1">Gestionar motos</h1>
        <p class="mb-0">Mantenedor para agregar, modificar, eliminar, listar y buscar motos.</p>
    </div>
    <a href="motos.php?action=new" class="btn btn-logico">Agregar moto</a>
</div>

<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger border-dark"><strong>Revise el formulario:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<section class="panel-card mb-4">
    <h2 class="h4 fw-bold mb-3"><?= $action === 'edit' ? 'Modificar moto' : 'Agregar moto' ?></h2>
    <form method="post" class="row g-3" novalidate>
        <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
        <input type="hidden" name="id" value="<?= e((string)$id) ?>">
        <div class="col-12 col-md-3"><label class="form-label">Patente</label><input name="patente" class="form-control" value="<?= e($record['patente']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Marca</label><input name="marca" class="form-control" value="<?= e($record['marca']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Modelo</label><input name="modelo" class="form-control" value="<?= e($record['modelo']) ?>" required></div>
        <div class="col-12 col-md-1"><label class="form-label">Año</label><input name="anio" class="form-control" value="<?= e((string)$record['anio']) ?>" required></div>
        <div class="col-12 col-md-2"><label class="form-label">Estado</label><select name="estado" class="form-select"><option <?= $record['estado'] === 'Disponible' ? 'selected' : '' ?>>Disponible</option><option <?= $record['estado'] === 'En uso' ? 'selected' : '' ?>>En uso</option><option <?= $record['estado'] === 'Mantención' ? 'selected' : '' ?>>Mantención</option><option <?= $record['estado'] === 'Inactiva' ? 'selected' : '' ?>>Inactiva</option></select></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-logico" type="submit">Guardar</button><a class="btn btn-outline-logico" href="motos.php">Cancelar</a></div>
    </form>
</section>
<?php endif; ?>

<section class="panel-card">
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-9"><label class="form-label">Buscar moto</label><input type="search" name="q" class="form-control" placeholder="Buscar por patente, marca, modelo o estado" value="<?= e($search) ?>"></div>
        <div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-logico w-100" type="submit">Buscar</button><a class="btn btn-outline-logico" href="motos.php">Limpiar</a></div>
    </form>
    <?php if (!$rows): ?>
        <div class="empty-state">No existen motos registradas para mostrar.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-logico align-middle mb-0">
                <thead><tr><th>Patente</th><th>Marca</th><th>Modelo</th><th>Año</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['patente']) ?></td><td><?= e($row['marca']) ?></td><td><?= e($row['modelo']) ?></td><td><?= e((string)$row['anio']) ?></td><td><span class="badge badge-logico"><?= e($row['estado']) ?></span></td>
                        <td><div class="action-grid"><a class="btn btn-sm btn-outline-logico" href="motos.php?action=edit&id=<?= e((string)$row['id']) ?>">Modificar</a><form method="post" onsubmit="return confirm('¿Eliminar esta moto?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$row['id']) ?>"><button class="btn btn-sm btn-outline-danger border-dark" type="submit">Eliminar</button></form></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
