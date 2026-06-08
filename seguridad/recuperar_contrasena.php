<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$currentPage = 'login';
$pageTitle = 'Recuperar contraseña';
$errors = [];
$correo = trim($_POST['correo'] ?? '');
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_required(['correo' => $correo], ['correo' => 'correo']);
    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo electrónico no tiene un formato válido.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? AND estado = 'Activo' LIMIT 1");
        $stmt->execute([$correo]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(24));
            $stmt = $pdo->prepare('UPDATE usuarios SET reset_token = ?, reset_expira = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?');
            $stmt->execute([$token, (int)$user['id']]);
            $resetLink = app_url('seguridad/modificar_contrasena.php?token=' . urlencode($token));
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="hero-card auth-card">
    <h1 class="section-title h2 mb-2">Recuperar contraseña</h1>
    <p>Genera un enlace temporal para cambiar la contraseña del usuario registrado.</p>
    <?php if ($errors): ?><div class="alert alert-danger border-dark"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors): ?>
        <div class="alert alert-success border-dark">
            Si el correo existe en el sistema, se generó un enlace temporal de recuperación.
            <?php if ($resetLink): ?>
                <div class="mt-2"><strong>Modo prototipo local:</strong> <a href="<?= e($resetLink) ?>">abrir enlace para modificar contraseña</a>.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <form method="post" class="row g-3" novalidate>
        <div class="col-12"><label class="form-label">Correo registrado</label><input type="email" name="correo" class="form-control" value="<?= e($correo) ?>" required></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-logico" type="submit">Generar recuperación</button><a class="btn btn-outline-logico" href="<?= e(app_url('seguridad/login.php')) ?>">Volver</a></div>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
