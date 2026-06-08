<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['Administrador']);

$currentPage = 'farmacias';
// La rúbrica exige georreferencia chilena: dirección, comuna, provincia y región.
$pageTitle = 'Gestionar farmacias';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$record = ['codigo' => '', 'nombre' => '', 'direccion' => '', 'comuna' => '', 'provincia' => '', 'region' => '', 'telefono' => '', 'estado' => 'Activa'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $data = [
        'codigo' => trim($_POST['codigo'] ?? ''),
        'nombre' => trim($_POST['nombre'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
        'comuna' => trim($_POST['comuna'] ?? ''),
        'provincia' => trim($_POST['provincia'] ?? ''),
        'region' => trim($_POST['region'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'estado' => trim($_POST['estado'] ?? 'Activa'),
    ];

    if ($postAction === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM farmacias WHERE id = ?');
        $stmt->execute([$deleteId]);
        redirect_with('farmacias.php', 'ok', 'Farmacia eliminada correctamente.');
    }

    $errors = validate_required($data, [
        'codigo' => 'código',
        'nombre' => 'nombre',
        'direccion' => 'dirección',
        'comuna' => 'comuna',
        'provincia' => 'provincia',
        'region' => 'región',
        'telefono' => 'teléfono',
    ]);

    $currentId = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM farmacias WHERE codigo = ? AND id <> ?');
    $stmt->execute([$data['codigo'], $currentId]);
    if ((int)$stmt->fetchColumn() > 0) {
        $errors[] = 'Ya existe una farmacia registrada con el mismo código.';
    }

    if (!$errors) {
        if ($postAction === 'create') {
            $stmt = $pdo->prepare('INSERT INTO farmacias (codigo, nombre, direccion, comuna, provincia, region, telefono, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$data['codigo'], $data['nombre'], $data['direccion'], $data['comuna'], $data['provincia'], $data['region'], $data['telefono'], $data['estado']]);
            redirect_with('farmacias.php', 'ok', 'Farmacia agregada correctamente.');
        }
        if ($postAction === 'update') {
            $stmt = $pdo->prepare('UPDATE farmacias SET codigo = ?, nombre = ?, direccion = ?, comuna = ?, provincia = ?, region = ?, telefono = ?, estado = ? WHERE id = ?');
            $stmt->execute([$data['codigo'], $data['nombre'], $data['direccion'], $data['comuna'], $data['provincia'], $data['region'], $data['telefono'], $data['estado'], $currentId]);
            redirect_with('farmacias.php', 'ok', 'Farmacia modificada correctamente.');
        }
    }
    $record = $data;
    $id = $currentId;
    $action = $postAction === 'update' ? 'edit' : 'new';
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM farmacias WHERE id = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) {
        redirect_with('farmacias.php', 'error', 'No se encontró la farmacia solicitada.');
    }
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM farmacias WHERE codigo LIKE ? OR nombre LIKE ? OR comuna LIKE ? OR provincia LIKE ? OR region LIKE ? OR direccion LIKE ? ORDER BY id DESC');
    $like = "%{$search}%";
    $stmt->execute([$like, $like, $like, $like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM farmacias ORDER BY id DESC');
}
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
$flash = get_flash();
?>
<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="section-title h2 mb-1">Gestionar farmacias</h1>
        <p class="mb-0">Mantenedor para agregar, modificar, eliminar, listar y buscar farmacias.</p>
    </div>
    <a href="farmacias.php?action=new" class="btn btn-logico">Agregar farmacia</a>
</div>

<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger border-dark"><strong>Revise el formulario:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<section class="panel-card mb-4">
    <h2 class="h4 fw-bold mb-3"><?= $action === 'edit' ? 'Modificar farmacia' : 'Agregar farmacia' ?></h2>
    <form method="post" class="row g-3" novalidate>
        <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
        <input type="hidden" name="id" value="<?= e((string)$id) ?>">
        <div class="col-12 col-md-3"><label class="form-label">Código</label><input name="codigo" class="form-control" value="<?= e($record['codigo']) ?>" required></div>
        <div class="col-12 col-md-5"><label class="form-label">Nombre</label><input name="nombre" class="form-control" value="<?= e($record['nombre']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Comuna</label><input name="comuna" class="form-control" value="<?= e($record['comuna']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Provincia</label><input name="provincia" class="form-control" value="<?= e($record['provincia']) ?>" required></div>
        <div class="col-12 col-md-4"><label class="form-label">Región</label><input name="region" class="form-control" value="<?= e($record['region']) ?>" placeholder="Ej: Región Metropolitana de Santiago" required></div>
        <div class="col-12 col-md-7"><label class="form-label">Dirección</label><input name="direccion" class="form-control" value="<?= e($record['direccion']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Teléfono</label><input name="telefono" class="form-control" value="<?= e($record['telefono']) ?>" required></div>
        <div class="col-12 col-md-2"><label class="form-label">Estado</label><select name="estado" class="form-select"><option <?= $record['estado'] === 'Activa' ? 'selected' : '' ?>>Activa</option><option <?= $record['estado'] === 'Inactiva' ? 'selected' : '' ?>>Inactiva</option></select></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-logico" type="submit">Guardar</button><a class="btn btn-outline-logico" href="farmacias.php">Cancelar</a></div>
    </form>
</section>
<?php endif; ?>

<section class="panel-card">
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-9"><label class="form-label">Buscar farmacia</label><input type="search" name="q" class="form-control" placeholder="Buscar por código, nombre, comuna, provincia, región o dirección" value="<?= e($search) ?>"></div>
        <div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-logico w-100" type="submit">Buscar</button><a class="btn btn-outline-logico" href="farmacias.php">Limpiar</a></div>
    </form>
    <?php if (!$rows): ?>
        <div class="empty-state">No existen farmacias registradas para mostrar.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-logico align-middle mb-0">
                <thead><tr><th>Código</th><th>Nombre</th><th>Dirección completa</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['codigo']) ?></td><td><?= e($row['nombre']) ?></td><td><?= e($row['direccion'] . ', ' . $row['comuna'] . ', Provincia de ' . $row['provincia'] . ', ' . $row['region']) ?></td><td><?= e($row['telefono']) ?></td><td><span class="badge badge-logico"><?= e($row['estado']) ?></span></td>
                        <td><div class="action-grid"><a class="btn btn-sm btn-outline-logico" href="farmacias.php?action=edit&id=<?= e((string)$row['id']) ?>">Modificar</a><form method="post" onsubmit="return confirm('¿Eliminar esta farmacia?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$row['id']) ?>"><button class="btn btn-sm btn-outline-danger border-dark" type="submit">Eliminar</button></form></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
