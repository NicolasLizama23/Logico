<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$currentPage = 'seguridad';
$pageTitle = 'Cambiar contraseña';
$errors = [];
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$targetUser = null;
$user = current_user();

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE reset_token = ? AND reset_expira >= NOW() AND estado = "Activo" LIMIT 1');
    $stmt->execute([$token]);
    $targetUser = $stmt->fetch();
    if (!$targetUser) {
        redirect_with(app_url('seguridad/login.php'), 'error', 'El enlace de recuperación no es válido o expiró.');
    }
} else {
    require_login();
    $targetUser = ['id' => $user['id'], 'nombre' => $user['nombre']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (strlen($password) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($password !== $confirm) {
        $errors[] = 'La confirmación no coincide con la contraseña.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = ?, reset_token = NULL, reset_expira = NULL WHERE id = ?');
        $stmt->execute([$hash, (int)$targetUser['id']]);
        if ($token !== '') {
            redirect_with(app_url('seguridad/login.php'), 'ok', 'Contraseña modificada correctamente. Ya puede iniciar sesión.');
        }
        redirect_with(dashboard_url_for_role((string)$user['rol']), 'ok', 'Contraseña modificada correctamente.');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="hero-card auth-card">
    <h1 class="section-title h2 mb-2">Cambiar contraseña</h1>
    <p>Actualización de credenciales para <?= e((string)$targetUser['nombre']) ?>.</p>
    <?php if ($errors): ?><div class="alert alert-danger border-dark"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="row g-3" novalidate>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="col-12"><label class="form-label">Nueva contraseña</label><input type="password" name="password" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Confirmar contraseña</label><input type="password" name="confirm_password" class="form-control" required></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-logico" type="submit">Guardar contraseña</button><a class="btn btn-outline-logico" href="<?= e($user ? dashboard_url_for_role($user['rol']) : app_url('seguridad/login.php')) ?>">Volver</a></div>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
