<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$currentPage = 'login';
$pageTitle = 'Iniciar sesión';
$errors = [];
$correo = trim($_POST['correo'] ?? '');

if (is_logged_in()) {
    $user = current_user();
    redirect_to(dashboard_url_for_role($user['rol']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $errors = validate_required(['correo' => $correo, 'password' => $password], [
        'correo' => 'correo',
        'password' => 'contraseña',
    ]);

    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo electrónico no tiene un formato válido.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ? AND estado = 'Activo' LIMIT 1");
        $stmt->execute([$correo]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($pdo, $user);
            redirect_with(dashboard_url_for_role((string)$user['rol']), 'ok', 'Sesión iniciada correctamente.');
        }
        $errors[] = 'Correo o contraseña incorrectos.';
    }
}

require_once __DIR__ . '/../includes/header.php';
$flash = get_flash();
?>
<section class="hero-card auth-card">
    <h1 class="section-title h2 mb-2">Iniciar sesión</h1>
    <p>Acceso protegido al sistema LogiCo. El sistema deriva automáticamente al panel correspondiente según el rol del usuario.</p>
    <?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger border-dark"><strong>Revise los datos:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="row g-3" novalidate>
        <div class="col-12">
            <label class="form-label">Correo</label>
            <input type="email" name="correo" class="form-control" value="<?= e($correo) ?>" placeholder="admin@logico.cl" required>
        </div>
        <div class="col-12">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" placeholder="Logico123" required>
        </div>
        <div class="col-12 d-grid gap-2">
            <button class="btn btn-logico" type="submit">Ingresar</button>
            <a class="btn btn-outline-logico" href="<?= e(app_url('seguridad/recuperar_contrasena.php')) ?>">Recuperar contraseña</a>
        </div>
    </form>
    <div class="alert alert-info border-dark mt-3 mb-0 small">
        <strong>Usuarios de prueba:</strong><br>
        admin@logico.cl / motorista@logico.cl / central@logico.cl / operador@logico.cl / local@logico.cl<br>
        <strong>Contraseña para todos:</strong> Logico123
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
