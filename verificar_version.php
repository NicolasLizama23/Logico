<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');
$user = current_user();
$role = $user ? normalize_role((string)$user['rol']) : 'Sin sesión';
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Verificar versión LogiCo</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="p-4">
<div class="container">
    <h1>Verificación de versión LogiCo</h1>
    <p>Esta versión corrige la separación de interfaces por rol.</p>
    <table class="table table-bordered">
        <tr><th>Ruta base detectada</th><td><?= e(app_base_url()) ?></td></tr>
        <tr><th>Usuario en sesión</th><td><?= $user ? e($user['nombre']) : 'No iniciado' ?></td></tr>
        <tr><th>Rol en sesión</th><td><?= e($role) ?></td></tr>
        <tr><th>Dashboard esperado</th><td><?= e($user ? dashboard_url_for_role($role) : app_url('seguridad/login.php')) ?></td></tr>
        <tr><th>Archivo index.php</th><td>Debe redirigir por rol; no debe mostrar tarjetas administrativas.</td></tr>
    </table>
    <a class="btn btn-primary" href="<?= e(app_url('index.php')) ?>">Ir al inicio</a>
    <a class="btn btn-outline-danger" href="<?= e(app_url('seguridad/logout.php')) ?>">Cerrar sesión</a>
</div>
</body></html>
