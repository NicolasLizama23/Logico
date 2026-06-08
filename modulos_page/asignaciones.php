<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['Administrador']);

$currentPage = 'asignaciones';
$pageTitle = 'Gestionar asignaciones';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'asignar_moto') {
        $motoId = (int)($_POST['moto_id'] ?? 0);
        $motoristaId = (int)($_POST['motorista_id'] ?? 0);
        $observacion = trim($_POST['observacion'] ?? '');

        if ($motoId <= 0) { $errors[] = 'Debe seleccionar una moto.'; }
        if ($motoristaId <= 0) { $errors[] = 'Debe seleccionar un motorista.'; }

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                // Cierra asignaciones activas previas de la misma moto para mantener trazabilidad.
                $stmt = $pdo->prepare("UPDATE asignaciones_moto SET estado = 'Reemplazada', fecha_termino = NOW() WHERE moto_id = ? AND estado = 'Activa'");
                $stmt->execute([$motoId]);

                $stmt = $pdo->prepare('INSERT INTO asignaciones_moto (moto_id, motorista_id, estado, observacion) VALUES (?, ?, "Activa", ?)');
                $stmt->execute([$motoId, $motoristaId, $observacion !== '' ? $observacion : null]);

                $stmt = $pdo->prepare("UPDATE motos SET motorista_id = ?, estado = 'En uso' WHERE id = ? AND estado <> 'Inactiva'");
                $stmt->execute([$motoristaId, $motoId]);

                $pdo->commit();
                redirect_with('asignaciones.php', 'ok', 'Moto asignada al motorista correctamente.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'No fue posible registrar la asignación de moto: ' . $e->getMessage();
            }
        }
    }

    if ($postAction === 'asignar_farmacia') {
        $farmaciaId = (int)($_POST['farmacia_id'] ?? 0);
        $motoristaId = (int)($_POST['motorista_id'] ?? 0);
        $observacion = trim($_POST['observacion'] ?? '');

        if ($farmaciaId <= 0) { $errors[] = 'Debe seleccionar una farmacia.'; }
        if ($motoristaId <= 0) { $errors[] = 'Debe seleccionar un motorista.'; }

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                // HU018: reemplazo. Se cierra la asignación activa anterior del motorista.
                $stmt = $pdo->prepare("UPDATE asignaciones_farmacia SET estado = 'Reemplazada', fecha_termino = NOW() WHERE motorista_id = ? AND estado = 'Activa'");
                $stmt->execute([$motoristaId]);

                $stmt = $pdo->prepare('INSERT INTO asignaciones_farmacia (farmacia_id, motorista_id, estado, observacion) VALUES (?, ?, "Activa", ?)');
                $stmt->execute([$farmaciaId, $motoristaId, $observacion !== '' ? $observacion : null]);

                $stmt = $pdo->prepare('UPDATE motoristas SET farmacia_id = ? WHERE id = ?');
                $stmt->execute([$farmaciaId, $motoristaId]);

                $pdo->commit();
                redirect_with('asignaciones.php', 'ok', 'Motorista asignado o reemplazado en farmacia correctamente.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'No fue posible registrar la asignación de farmacia: ' . $e->getMessage();
            }
        }
    }

    if ($postAction === 'finalizar_moto') {
        $assignmentId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT moto_id FROM asignaciones_moto WHERE id = ?');
        $stmt->execute([$assignmentId]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE asignaciones_moto SET estado = 'Finalizada', fecha_termino = NOW() WHERE id = ?");
            $stmt->execute([$assignmentId]);
            $stmt = $pdo->prepare("UPDATE motos SET motorista_id = NULL, estado = 'Disponible' WHERE id = ?");
            $stmt->execute([(int)$row['moto_id']]);
            $pdo->commit();
            redirect_with('asignaciones.php', 'ok', 'Asignación de moto finalizada correctamente.');
        }
    }

    if ($postAction === 'finalizar_farmacia') {
        $assignmentId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT motorista_id FROM asignaciones_farmacia WHERE id = ?');
        $stmt->execute([$assignmentId]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE asignaciones_farmacia SET estado = 'Finalizada', fecha_termino = NOW() WHERE id = ?");
            $stmt->execute([$assignmentId]);
            $stmt = $pdo->prepare('UPDATE motoristas SET farmacia_id = NULL WHERE id = ?');
            $stmt->execute([(int)$row['motorista_id']]);
            $pdo->commit();
            redirect_with('asignaciones.php', 'ok', 'Asignación de farmacia finalizada correctamente.');
        }
    }
}

$farmacias = $pdo->query("SELECT id, codigo, nombre, comuna, provincia, region FROM farmacias WHERE estado = 'Activa' ORDER BY nombre")->fetchAll();
$motoristas = $pdo->query("SELECT id, rut, nombres, apellidos FROM motoristas WHERE estado = 'Activo' ORDER BY nombres, apellidos")->fetchAll();
$motos = $pdo->query("SELECT id, patente, marca, modelo, estado FROM motos WHERE estado <> 'Inactiva' ORDER BY patente")->fetchAll();

$asignacionesMoto = $pdo->query("SELECT am.*, mo.patente, mo.marca, mo.modelo, mt.rut, mt.nombres, mt.apellidos
    FROM asignaciones_moto am
    INNER JOIN motos mo ON mo.id = am.moto_id
    INNER JOIN motoristas mt ON mt.id = am.motorista_id
    ORDER BY am.id DESC")->fetchAll();

$asignacionesFarmacia = $pdo->query("SELECT af.*, f.codigo, f.nombre AS farmacia_nombre, f.comuna, f.provincia, f.region, mt.rut, mt.nombres, mt.apellidos
    FROM asignaciones_farmacia af
    INNER JOIN farmacias f ON f.id = af.farmacia_id
    INNER JOIN motoristas mt ON mt.id = af.motorista_id
    ORDER BY af.id DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
$flash = get_flash();
?>
<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="section-title h2 mb-1">Gestionar asignaciones</h1>
        <p class="mb-0">Cubre HU016, HU017 y HU018: asignar motos, asignar motoristas a farmacias y reemplazar asignaciones.</p>
    </div>
</div>

<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger border-dark"><strong>Revise el formulario:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-12 col-lg-6">
        <section class="panel-card h-100">
            <h2 class="h4 fw-bold mb-3">Asignar moto a motorista</h2>
            <form method="post" class="row g-3" novalidate>
                <input type="hidden" name="action" value="asignar_moto">
                <div class="col-12"><label class="form-label">Moto</label><select name="moto_id" class="form-select" required><option value="">Seleccione</option><?php foreach ($motos as $moto): ?><option value="<?= e((string)$moto['id']) ?>"><?= e($moto['patente'] . ' - ' . $moto['marca'] . ' ' . $moto['modelo'] . ' (' . $moto['estado'] . ')') ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label">Motorista</label><select name="motorista_id" class="form-select" required><option value="">Seleccione</option><?php foreach ($motoristas as $motorista): ?><option value="<?= e((string)$motorista['id']) ?>"><?= e($motorista['rut'] . ' - ' . $motorista['nombres'] . ' ' . $motorista['apellidos']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label">Observación</label><input name="observacion" class="form-control" placeholder="Ejemplo: asignación por turno mañana"></div>
                <div class="col-12"><button class="btn btn-logico" type="submit">Guardar asignación</button></div>
            </form>
        </section>
    </div>
    <div class="col-12 col-lg-6">
        <section class="panel-card h-100">
            <h2 class="h4 fw-bold mb-3">Asignar o reemplazar motorista en farmacia</h2>
            <form method="post" class="row g-3" novalidate>
                <input type="hidden" name="action" value="asignar_farmacia">
                <div class="col-12"><label class="form-label">Farmacia</label><select name="farmacia_id" class="form-select" required><option value="">Seleccione</option><?php foreach ($farmacias as $farmacia): ?><option value="<?= e((string)$farmacia['id']) ?>"><?= e($farmacia['codigo'] . ' - ' . $farmacia['nombre'] . ' / ' . $farmacia['comuna'] . ' / ' . $farmacia['region']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label">Motorista</label><select name="motorista_id" class="form-select" required><option value="">Seleccione</option><?php foreach ($motoristas as $motorista): ?><option value="<?= e((string)$motorista['id']) ?>"><?= e($motorista['rut'] . ' - ' . $motorista['nombres'] . ' ' . $motorista['apellidos']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label">Observación</label><input name="observacion" class="form-control" placeholder="Ejemplo: reemplazo por cambio de local"></div>
                <div class="col-12"><button class="btn btn-logico" type="submit">Guardar asignación/reemplazo</button></div>
            </form>
        </section>
    </div>
</div>

<section class="panel-card mb-4">
    <h2 class="h4 fw-bold mb-3">Historial de motos asignadas</h2>
    <div class="table-responsive">
        <table class="table table-logico align-middle mb-0">
            <thead><tr><th>Moto</th><th>Motorista</th><th>Fecha asignación</th><th>Fecha término</th><th>Estado</th><th>Observación</th><th>Acción</th></tr></thead>
            <tbody>
            <?php foreach ($asignacionesMoto as $row): ?>
                <tr>
                    <td><?= e($row['patente'] . ' - ' . $row['marca'] . ' ' . $row['modelo']) ?></td>
                    <td><?= e($row['rut'] . ' - ' . $row['nombres'] . ' ' . $row['apellidos']) ?></td>
                    <td><?= e($row['fecha_asignacion']) ?></td>
                    <td><?= e($row['fecha_termino'] ?? '-') ?></td>
                    <td><span class="badge badge-logico"><?= e($row['estado']) ?></span></td>
                    <td><?= e($row['observacion'] ?? '-') ?></td>
                    <td><?php if ($row['estado'] === 'Activa'): ?><form method="post" onsubmit="return confirm('¿Finalizar asignación de moto?');"><input type="hidden" name="action" value="finalizar_moto"><input type="hidden" name="id" value="<?= e((string)$row['id']) ?>"><button class="btn btn-sm btn-outline-danger border-dark" type="submit">Finalizar</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel-card">
    <h2 class="h4 fw-bold mb-3">Historial de motoristas por farmacia</h2>
    <div class="table-responsive">
        <table class="table table-logico align-middle mb-0">
            <thead><tr><th>Farmacia</th><th>Motorista</th><th>Fecha asignación</th><th>Fecha término</th><th>Estado</th><th>Observación</th><th>Acción</th></tr></thead>
            <tbody>
            <?php foreach ($asignacionesFarmacia as $row): ?>
                <tr>
                    <td><?= e($row['codigo'] . ' - ' . $row['farmacia_nombre'] . ' / ' . $row['comuna'] . ' / ' . $row['region']) ?></td>
                    <td><?= e($row['rut'] . ' - ' . $row['nombres'] . ' ' . $row['apellidos']) ?></td>
                    <td><?= e($row['fecha_asignacion']) ?></td>
                    <td><?= e($row['fecha_termino'] ?? '-') ?></td>
                    <td><span class="badge badge-logico"><?= e($row['estado']) ?></span></td>
                    <td><?= e($row['observacion'] ?? '-') ?></td>
                    <td><?php if ($row['estado'] === 'Activa'): ?><form method="post" onsubmit="return confirm('¿Finalizar asignación de farmacia?');"><input type="hidden" name="action" value="finalizar_farmacia"><input type="hidden" name="id" value="<?= e((string)$row['id']) ?>"><button class="btn btn-sm btn-outline-danger border-dark" type="submit">Finalizar</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
