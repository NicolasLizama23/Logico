<?php
/**
 * Módulo de administración de cuentas de usuario.
 *
 * Este archivo queda disponible solo para el rol Administrador. Permite crear
 * cuentas de acceso al sistema y vincularlas con entidades operativas cuando
 * corresponde:
 * - Rol Motorista: exige que el motorista exista previamente en la tabla motoristas.
 * - Rol Local Despacho: exige que la farmacia/local exista previamente en la tabla farmacias.
 * - Roles Administrador, Farmacia Central y Operador Control Despacho: no exigen vínculo previo.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Administrador']);

$currentPage = 'usuarios';
$pageTitle = 'Crear cuentas de usuarios';
$errors = [];

$rolesPermitidos = [
    'Administrador',
    'Motorista',
    'Farmacia Central',
    'Operador Control Despacho',
    'Local Despacho',
];

// Motoristas activos registrados previamente. Se muestran aunque tengan cuenta,
// pero el formulario bloquea la creación duplicada al validar en servidor.
$motoristas = $pdo->query("
    SELECT m.id, m.rut, m.nombres, m.apellidos, m.direccion, m.comuna, m.provincia, m.region, m.estado,
           f.nombre AS farmacia_nombre,
           u.id AS usuario_id
    FROM motoristas m
    LEFT JOIN farmacias f ON f.id = m.farmacia_id
    LEFT JOIN usuarios u ON u.motorista_id = m.id AND u.rol = 'Motorista'
    WHERE m.estado = 'Activo'
    ORDER BY m.apellidos, m.nombres
")->fetchAll();

// Locales de despacho registrados previamente. El rol Local Despacho debe
// quedar asociado a una farmacia/local existente para filtrar pedidos por local.
$locales = $pdo->query("
    SELECT f.id, f.codigo, f.nombre, f.direccion, f.comuna, f.provincia, f.region, f.estado,
           u.id AS usuario_id
    FROM farmacias f
    LEFT JOIN usuarios u ON u.farmacia_id = f.id AND u.rol = 'Local Despacho'
    WHERE f.tipo = 'Local' AND f.estado = 'Activa'
    ORDER BY f.region, f.comuna, f.nombre
")->fetchAll();

// Farmacias centrales opcionales para el rol Farmacia Central. No es obligatorio
// seleccionar una porque el usuario indicó que este rol no debe tener restricción.
$farmaciasCentrales = $pdo->query("
    SELECT id, codigo, nombre, direccion, comuna, provincia, region
    FROM farmacias
    WHERE tipo = 'Central' AND estado = 'Activa'
    ORDER BY nombre
")->fetchAll();

$form = [
    'nombre' => '',
    'correo' => '',
    'rol' => 'Motorista',
    'motorista_id' => '',
    'farmacia_local_id' => '',
    'farmacia_central_id' => '',
    'estado' => 'Activo',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'correo' => trim($_POST['correo'] ?? ''),
        'rol' => trim($_POST['rol'] ?? ''),
        'motorista_id' => trim($_POST['motorista_id'] ?? ''),
        'farmacia_local_id' => trim($_POST['farmacia_local_id'] ?? ''),
        'farmacia_central_id' => trim($_POST['farmacia_central_id'] ?? ''),
        'estado' => trim($_POST['estado'] ?? 'Activo'),
    ];
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    $errors = validate_required($form, [
        'correo' => 'correo electrónico',
        'rol' => 'rol',
        'estado' => 'estado',
    ]);

    if (!filter_var($form['correo'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Debe ingresar un correo electrónico válido.';
    }

    if ($password === '') {
        $errors[] = 'La contraseña es obligatoria.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'La confirmación de contraseña no coincide.';
    }

    if (!in_array($form['rol'], $rolesPermitidos, true)) {
        $errors[] = 'El rol seleccionado no es válido.';
    }

    if (!in_array($form['estado'], ['Activo', 'Inactivo'], true)) {
        $errors[] = 'El estado seleccionado no es válido.';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE correo = ?');
    $stmt->execute([$form['correo']]);
    if ((int)$stmt->fetchColumn() > 0) {
        $errors[] = 'Ya existe una cuenta registrada con ese correo electrónico.';
    }

    $motoristaId = null;
    $farmaciaId = null;
    $nombreCuenta = $form['nombre'];

    if ($form['rol'] === 'Motorista') {
        $motoristaId = (int)$form['motorista_id'];
        if ($motoristaId <= 0) {
            $errors[] = 'Para crear una cuenta de Motorista debe seleccionar un motorista registrado previamente.';
        } else {
            $stmt = $pdo->prepare("
                SELECT m.id, m.nombres, m.apellidos, m.rut, m.direccion, m.comuna, m.provincia, m.region,
                       f.nombre AS farmacia_nombre
                FROM motoristas m
                LEFT JOIN farmacias f ON f.id = m.farmacia_id
                WHERE m.id = ? AND m.estado = 'Activo'
            ");
            $stmt->execute([$motoristaId]);
            $motorista = $stmt->fetch();

            if (!$motorista) {
                $errors[] = 'El motorista seleccionado no existe o no se encuentra activo. Primero debe registrarlo en el mantenedor de motoristas.';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'Motorista' AND motorista_id = ?");
                $stmt->execute([$motoristaId]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $errors[] = 'El motorista seleccionado ya tiene una cuenta de usuario vinculada.';
                }
                if ($nombreCuenta === '') {
                    $nombreCuenta = trim($motorista['nombres'] . ' ' . $motorista['apellidos']);
                }
            }
        }
    } elseif ($form['rol'] === 'Local Despacho') {
        $farmaciaId = (int)$form['farmacia_local_id'];
        if ($farmaciaId <= 0) {
            $errors[] = 'Para crear una cuenta de Local de Despacho debe seleccionar una farmacia/local registrada previamente.';
        } else {
            $stmt = $pdo->prepare("SELECT id, nombre, codigo, direccion, comuna, provincia, region FROM farmacias WHERE id = ? AND tipo = 'Local' AND estado = 'Activa'");
            $stmt->execute([$farmaciaId]);
            $local = $stmt->fetch();

            if (!$local) {
                $errors[] = 'El local seleccionado no existe o no está activo. Primero debe registrarlo en el mantenedor de farmacias.';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'Local Despacho' AND farmacia_id = ?");
                $stmt->execute([$farmaciaId]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $errors[] = 'El local seleccionado ya tiene una cuenta de usuario vinculada.';
                }
                if ($nombreCuenta === '') {
                    $nombreCuenta = 'Local Despacho - ' . $local['nombre'];
                }
            }
        }
    } elseif ($form['rol'] === 'Farmacia Central') {
        // Vínculo opcional. Este rol puede existir aunque no se seleccione farmacia central.
        if ($form['farmacia_central_id'] !== '') {
            $farmaciaId = (int)$form['farmacia_central_id'];
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM farmacias WHERE id = ? AND tipo = 'Central' AND estado = 'Activa'");
            $stmt->execute([$farmaciaId]);
            if ((int)$stmt->fetchColumn() === 0) {
                $errors[] = 'La farmacia central seleccionada no existe o no está activa.';
            }
        }
        if ($nombreCuenta === '') {
            $nombreCuenta = 'Usuario Farmacia Central';
        }
    } elseif ($form['rol'] === 'Operador Control Despacho') {
        if ($nombreCuenta === '') {
            $nombreCuenta = 'Operador Control Despacho';
        }
    } elseif ($form['rol'] === 'Administrador') {
        if ($nombreCuenta === '') {
            $nombreCuenta = 'Administrador LogiCo';
        }
    }

    if (!$errors) {
        // password_hash permite almacenar contraseñas de forma segura sin guardar texto plano.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombre, correo, password_hash, rol, motorista_id, farmacia_id, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nombreCuenta,
            $form['correo'],
            $passwordHash,
            $form['rol'],
            $motoristaId,
            $farmaciaId,
            $form['estado'],
        ]);

        redirect_with(app_url('modulos_page/usuarios.php'), 'ok', 'Cuenta de usuario creada y vinculada correctamente.');
    }
}

$usuarios = $pdo->query("
    SELECT u.id, u.nombre, u.correo, u.rol, u.estado, u.ultimo_acceso, u.creado_en,
           CONCAT(m.nombres, ' ', m.apellidos, ' - ', m.rut) AS motorista_nombre,
           f.nombre AS farmacia_nombre,
           f.comuna AS farmacia_comuna,
           f.region AS farmacia_region
    FROM usuarios u
    LEFT JOIN motoristas m ON m.id = u.motorista_id
    LEFT JOIN farmacias f ON f.id = u.farmacia_id
    ORDER BY u.id DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
$flash = get_flash();
?>
<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="section-title h2 mb-1">Crear cuentas de usuarios</h1>
        <p class="mb-0">Módulo exclusivo del Administrador para crear accesos y vincularlos con motoristas o locales registrados.</p>
    </div>
    <a href="<?= e(app_url('vistas_rol/administrador/index.php')) ?>" class="btn btn-outline-logico">Volver al panel</a>
</div>

<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger border-dark"><strong>Revise el formulario:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="panel-card mb-4">
    <h2 class="h4 fw-bold mb-3">Nueva cuenta de acceso</h2>
    <form method="post" class="row g-3" novalidate>
        <div class="col-12 col-md-4">
            <label class="form-label">Correo electrónico</label>
            <input type="email" name="correo" class="form-control" value="<?= e($form['correo']) ?>" placeholder="usuario@logico.cl" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Nombre visible</label>
            <input name="nombre" class="form-control" value="<?= e($form['nombre']) ?>" placeholder="Opcional para motorista/local">
            <div class="form-text">Si se deja vacío, se completa con el motorista o local vinculado.</div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Rol</label>
            <select name="rol" id="rol" class="form-select" required>
                <?php foreach ($rolesPermitidos as $rol): ?>
                    <option value="<?= e($rol) ?>" <?= selected($form['rol'], $rol) ?>><?= e($rol) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-4">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" minlength="6" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Confirmar contraseña</label>
            <input type="password" name="password_confirm" class="form-control" minlength="6" required>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
                <option value="Activo" <?= selected($form['estado'], 'Activo') ?>>Activo</option>
                <option value="Inactivo" <?= selected($form['estado'], 'Inactivo') ?>>Inactivo</option>
            </select>
        </div>

        <div class="col-12 conditional-field" id="field-motorista">
            <label class="form-label">Vincular con motorista registrado</label>
            <select name="motorista_id" class="form-select">
                <option value="">Seleccione un motorista registrado previamente</option>
                <?php foreach ($motoristas as $motorista): ?>
                    <?php $disabled = $motorista['usuario_id'] ? 'disabled' : ''; ?>
                    <option value="<?= e((string)$motorista['id']) ?>" <?= selected($form['motorista_id'], (string)$motorista['id']) ?> <?= $disabled ?>>
                        <?= e($motorista['nombres'] . ' ' . $motorista['apellidos'] . ' | RUT ' . $motorista['rut'] . ' | ' . $motorista['direccion'] . ', ' . $motorista['comuna'] . ', ' . $motorista['region'] . ' | ' . ($motorista['farmacia_nombre'] ?? 'Sin farmacia')) ?><?= $motorista['usuario_id'] ? ' | Ya tiene usuario' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Si el motorista no aparece, primero debe registrarse con RUT, nombre, apellido, dirección, comuna, provincia y región en el mantenedor de Motoristas.</div>
        </div>

        <div class="col-12 conditional-field" id="field-local">
            <label class="form-label">Vincular con local de despacho registrado</label>
            <select name="farmacia_local_id" class="form-select">
                <option value="">Seleccione una farmacia/local registrada previamente</option>
                <?php foreach ($locales as $local): ?>
                    <?php $disabled = $local['usuario_id'] ? 'disabled' : ''; ?>
                    <option value="<?= e((string)$local['id']) ?>" <?= selected($form['farmacia_local_id'], (string)$local['id']) ?> <?= $disabled ?>>
                        <?= e($local['codigo'] . ' | ' . $local['nombre'] . ' | ' . $local['direccion'] . ', ' . $local['comuna'] . ', ' . $local['region']) ?><?= $local['usuario_id'] ? ' | Ya tiene usuario' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Si el local no aparece, primero debe registrarse como farmacia tipo Local en el mantenedor de Farmacias.</div>
        </div>

        <div class="col-12 conditional-field" id="field-central">
            <label class="form-label">Vincular con farmacia central, opcional</label>
            <select name="farmacia_central_id" class="form-select">
                <option value="">Sin vínculo obligatorio</option>
                <?php foreach ($farmaciasCentrales as $central): ?>
                    <option value="<?= e((string)$central['id']) ?>" <?= selected($form['farmacia_central_id'], (string)$central['id']) ?>>
                        <?= e($central['codigo'] . ' | ' . $central['nombre'] . ' | ' . $central['direccion'] . ', ' . $central['comuna'] . ', ' . $central['region']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Farmacia Central, Operador y Administrador pueden tener cuentas sin registro operativo previo.</div>
        </div>

        <div class="col-12 d-flex flex-column flex-md-row gap-2">
            <button class="btn btn-logico" type="submit">Crear cuenta</button>
            <a class="btn btn-outline-logico" href="<?= e(app_url('modulos_page/usuarios.php')) ?>">Limpiar formulario</a>
        </div>
    </form>
</section>

<section class="panel-card">
    <h2 class="h4 fw-bold mb-3">Usuarios registrados</h2>
    <?php if (!$usuarios): ?>
        <div class="empty-state">No existen usuarios registrados.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-logico align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Vínculo operativo</th>
                        <th>Estado</th>
                        <th>Último acceso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <?php
                        $vinculo = 'Sin restricción / sin vínculo requerido';
                        if ($usuario['rol'] === 'Motorista' && $usuario['motorista_nombre']) {
                            $vinculo = 'Motorista: ' . $usuario['motorista_nombre'];
                        } elseif ($usuario['rol'] === 'Local Despacho' && $usuario['farmacia_nombre']) {
                            $vinculo = 'Local: ' . $usuario['farmacia_nombre'] . ' - ' . $usuario['farmacia_comuna'] . ', ' . $usuario['farmacia_region'];
                        } elseif ($usuario['rol'] === 'Farmacia Central' && $usuario['farmacia_nombre']) {
                            $vinculo = 'Central: ' . $usuario['farmacia_nombre'] . ' - ' . $usuario['farmacia_comuna'];
                        }
                        ?>
                        <tr>
                            <td><?= e($usuario['nombre']) ?></td>
                            <td><?= e($usuario['correo']) ?></td>
                            <td><span class="badge badge-logico"><?= e($usuario['rol']) ?></span></td>
                            <td><?= e($vinculo) ?></td>
                            <td><?= e($usuario['estado']) ?></td>
                            <td><?= e($usuario['ultimo_acceso'] ?: 'Sin acceso registrado') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
(function () {
    const rol = document.getElementById('rol');
    const fieldMotorista = document.getElementById('field-motorista');
    const fieldLocal = document.getElementById('field-local');
    const fieldCentral = document.getElementById('field-central');

    function toggleConditionalFields() {
        const value = rol.value;
        fieldMotorista.style.display = value === 'Motorista' ? 'block' : 'none';
        fieldLocal.style.display = value === 'Local Despacho' ? 'block' : 'none';
        fieldCentral.style.display = value === 'Farmacia Central' ? 'block' : 'none';
    }

    rol.addEventListener('change', toggleConditionalFields);
    toggleConditionalFields();
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
