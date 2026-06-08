<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['Administrador']);

$currentPage = 'motoristas';
// Cada motorista registra dirección completa bajo división político-administrativa chilena.
$pageTitle = 'Gestionar motoristas';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$record = ['rut' => '', 'nombres' => '', 'apellidos' => '', 'direccion' => '', 'comuna' => '', 'provincia' => '', 'region' => '', 'telefono' => '', 'correo' => '', 'licencia' => '', 'estado' => 'Activo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $data = [
        'rut' => trim($_POST['rut'] ?? ''),
        'nombres' => trim($_POST['nombres'] ?? ''),
        'apellidos' => trim($_POST['apellidos'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
        'comuna' => trim($_POST['comuna'] ?? ''),
        'provincia' => trim($_POST['provincia'] ?? ''),
        'region' => trim($_POST['region'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'correo' => trim($_POST['correo'] ?? ''),
        'licencia' => trim($_POST['licencia'] ?? ''),
        'estado' => trim($_POST['estado'] ?? 'Activo'),
    ];

    if ($postAction === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM motoristas WHERE id = ?');
        $stmt->execute([$deleteId]);
        redirect_with('motoristas.php', 'ok', 'Motorista eliminado correctamente.');
    }

    $errors = validate_required($data, [
        'rut' => 'RUT',
        'nombres' => 'nombres',
        'apellidos' => 'apellidos',
        'direccion' => 'dirección',
        'comuna' => 'comuna',
        'provincia' => 'provincia',
        'region' => 'región',
        'telefono' => 'teléfono',
        'correo' => 'correo',
        'licencia' => 'licencia',
    ]);

    if ($data['correo'] !== '' && !filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo electrónico no tiene un formato válido.';
    }

    $currentId = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM motoristas WHERE rut = ? AND id <> ?');
    $stmt->execute([$data['rut'], $currentId]);
    if ((int)$stmt->fetchColumn() > 0) {
        $errors[] = 'Ya existe un motorista registrado con el mismo RUT.';
    }

    if (!$errors) {
        if ($postAction === 'create') {
            $stmt = $pdo->prepare('INSERT INTO motoristas (rut, nombres, apellidos, direccion, comuna, provincia, region, telefono, correo, licencia, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$data['rut'], $data['nombres'], $data['apellidos'], $data['direccion'], $data['comuna'], $data['provincia'], $data['region'], $data['telefono'], $data['correo'], $data['licencia'], $data['estado']]);
            redirect_with('motoristas.php', 'ok', 'Motorista agregado correctamente.');
        }
        if ($postAction === 'update') {
            $stmt = $pdo->prepare('UPDATE motoristas SET rut = ?, nombres = ?, apellidos = ?, direccion = ?, comuna = ?, provincia = ?, region = ?, telefono = ?, correo = ?, licencia = ?, estado = ? WHERE id = ?');
            $stmt->execute([$data['rut'], $data['nombres'], $data['apellidos'], $data['direccion'], $data['comuna'], $data['provincia'], $data['region'], $data['telefono'], $data['correo'], $data['licencia'], $data['estado'], $currentId]);
            redirect_with('motoristas.php', 'ok', 'Motorista modificado correctamente.');
        }
    }
    $record = $data;
    $id = $currentId;
    $action = $postAction === 'update' ? 'edit' : 'new';
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM motoristas WHERE id = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) {
        redirect_with('motoristas.php', 'error', 'No se encontró el motorista solicitado.');
    }
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM motoristas WHERE rut LIKE ? OR nombres LIKE ? OR apellidos LIKE ? OR telefono LIKE ? OR direccion LIKE ? OR comuna LIKE ? OR provincia LIKE ? OR region LIKE ? ORDER BY id DESC');
    $like = "%{$search}%";
    $stmt->execute([$like, $like, $like, $like, $like, $like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM motoristas ORDER BY id DESC');
}
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
$flash = get_flash();
?>
<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="section-title h2 mb-1">Gestionar motoristas</h1>
        <p class="mb-0">Mantenedor para agregar, modificar, eliminar, listar y buscar motoristas.</p>
    </div>
    <a href="motoristas.php?action=new" class="btn btn-logico">Agregar motorista</a>
</div>

<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger border-dark"><strong>Revise el formulario:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<section class="panel-card mb-4">
    <h2 class="h4 fw-bold mb-3"><?= $action === 'edit' ? 'Modificar motorista' : 'Agregar motorista' ?></h2>
    <form method="post" class="row g-3" novalidate>
        <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
        <input type="hidden" name="id" value="<?= e((string)$id) ?>">
        <div class="col-12 col-md-3"><label class="form-label">RUT</label><input name="rut" class="form-control" value="<?= e($record['rut']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Nombres</label><input name="nombres" class="form-control" value="<?= e($record['nombres']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Apellidos</label><input name="apellidos" class="form-control" value="<?= e($record['apellidos']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Teléfono</label><input name="telefono" class="form-control" value="<?= e($record['telefono']) ?>" required></div>
        <div class="col-12 col-md-5"><label class="form-label">Dirección</label><input name="direccion" class="form-control" value="<?= e($record['direccion']) ?>" required></div>
        <div class="col-12 col-md-3"><label class="form-label">Comuna</label><input name="comuna" class="form-control" value="<?= e($record['comuna']) ?>" required></div>
        <div class="col-12 col-md-2"><label class="form-label">Provincia</label><input name="provincia" class="form-control" value="<?= e($record['provincia']) ?>" required></div>
        <div class="col-12 col-md-4"><label class="form-label">Región</label><input name="region" class="form-control" value="<?= e($record['region']) ?>" placeholder="Ej: Región de Arica y Parinacota" required></div>
        <div class="col-12 col-md-4"><label class="form-label">Correo</label><input name="correo" type="email" class="form-control" value="<?= e($record['correo']) ?>" required></div>
        <div class="col-12 col-md-4"><label class="form-label">Licencia</label><input name="licencia" class="form-control" value="<?= e($record['licencia']) ?>" required></div>
        <div class="col-12 col-md-4"><label class="form-label">Estado</label><select name="estado" class="form-select"><option <?= $record['estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option><option <?= $record['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option></select></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-logico" type="submit">Guardar</button><a class="btn btn-outline-logico" href="motoristas.php">Cancelar</a></div>
    </form>
</section>
<?php endif; ?>

<section class="panel-card">
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-9"><label class="form-label">Buscar motorista</label><input type="search" name="q" class="form-control" placeholder="Buscar por RUT, nombre, apellido, teléfono, comuna, provincia o región" value="<?= e($search) ?>"></div>
        <div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-logico w-100" type="submit">Buscar</button><a class="btn btn-outline-logico" href="motoristas.php">Limpiar</a></div>
    </form>
    <?php if (!$rows): ?>
        <div class="empty-state">No existen motoristas registrados para mostrar.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-logico align-middle mb-0">
                <thead><tr><th>RUT</th><th>Nombre</th><th>Dirección completa</th><th>Teléfono</th><th>Correo</th><th>Licencia</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['rut']) ?></td><td><?= e($row['nombres'] . ' ' . $row['apellidos']) ?></td><td><?= e($row['direccion'] . ', ' . $row['comuna'] . ', Provincia de ' . $row['provincia'] . ', ' . $row['region']) ?></td><td><?= e($row['telefono']) ?></td><td><?= e($row['correo']) ?></td><td><?= e($row['licencia']) ?></td><td><span class="badge badge-logico"><?= e($row['estado']) ?></span></td>
                        <td><div class="action-grid"><a class="btn btn-sm btn-outline-logico" href="motoristas.php?action=edit&id=<?= e((string)$row['id']) ?>">Modificar</a><form method="post" onsubmit="return confirm('¿Eliminar este motorista?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$row['id']) ?>"><button class="btn btn-sm btn-outline-danger border-dark" type="submit">Eliminar</button></form></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
