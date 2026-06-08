<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['Administrador']);

$currentPage = 'inicio';
$pageTitle = 'Panel administrador';

$counts = [
    'farmacias' => (int)$pdo->query('SELECT COUNT(*) FROM farmacias')->fetchColumn(),
    'motoristas' => (int)$pdo->query('SELECT COUNT(*) FROM motoristas')->fetchColumn(),
    'motos' => (int)$pdo->query('SELECT COUNT(*) FROM motos')->fetchColumn(),
    'asignaciones' => (int)$pdo->query("SELECT COUNT(*) FROM asignaciones_moto WHERE estado = 'Activa'")->fetchColumn()
        + (int)$pdo->query("SELECT COUNT(*) FROM asignaciones_farmacia WHERE estado = 'Activa'")->fetchColumn(),
    'movimientos' => (int)$pdo->query('SELECT COUNT(*) FROM movimientos')->fetchColumn(),
    'usuarios' => (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(),
];

require_once __DIR__ . '/../../includes/header.php';
$flash = get_flash();
?>
<?php if ($flash): ?><div class="alert alert-<?= e($flash['class']) ?> border-dark"><?= e($flash['message']) ?></div><?php endif; ?>
<section class="hero-card text-center">
    <div class="d-inline-flex flex-column align-items-center mb-3">
        <span class="logo-mark mb-2" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
        <h1 class="section-title mb-1">Panel Administrador</h1>
    </div>
    <h2 class="h4 fw-bold mb-3">LogiCo - Entrega 3 por roles</h2>
    <p class="mb-4">Vista general para administración completa: mantenedores, asignaciones, movimientos, reportes y seguridad.</p>
    <div class="row g-3 text-start">
        <div class="col-12 col-md-4"><a class="module-card d-block text-decoration-none text-dark" href="<?= e(app_url('modulos_page/farmacias.php')) ?>"><span class="badge badge-logico mb-3">Administración</span><h3 class="h5 fw-bold">Farmacias</h3><p class="mb-2">Agregar, listar, buscar, modificar y eliminar farmacias.</p><strong><?= $counts['farmacias'] ?></strong> registros</a></div>
        <div class="col-12 col-md-4"><a class="module-card d-block text-decoration-none text-dark" href="<?= e(app_url('modulos_page/motoristas.php')) ?>"><span class="badge badge-logico mb-3">Administración</span><h3 class="h5 fw-bold">Motoristas</h3><p class="mb-2">Mantenedor y relación con farmacia asignada.</p><strong><?= $counts['motoristas'] ?></strong> registros</a></div>
        <div class="col-12 col-md-4"><a class="module-card d-block text-decoration-none text-dark" href="<?= e(app_url('modulos_page/motos.php')) ?>"><span class="badge badge-logico mb-3">Administración</span><h3 class="h5 fw-bold">Motos</h3><p class="mb-2">Mantenedor y relación con motorista asignado.</p><strong><?= $counts['motos'] ?></strong> registros</a></div>
        <div class="col-12 col-md-4"><a class="module-card d-block text-decoration-none text-dark" href="<?= e(app_url('modulos_page/asignaciones.php')) ?>"><span class="badge badge-logico mb-3">HU016 - HU018</span><h3 class="h5 fw-bold">Asignaciones</h3><p class="mb-2">Asignar motos, asignar motoristas a farmacias y reemplazar asignaciones.</p><strong><?= $counts['asignaciones'] ?></strong> activas</a></div>
        <div class="col-12 col-md-4"><a class="module-card d-block text-decoration-none text-dark" href="<?= e(app_url('modulos_page/movimientos.php')) ?>"><span class="badge badge-logico mb-3">Pedidos y movimientos</span><h3 class="h5 fw-bold">Movimientos</h3><p class="mb-2">Registrar movimientos directos, con receta, traslado o reenvío.</p><strong><?= $counts['movimientos'] ?></strong> movimientos</a></div>
        <div class="col-12 col-md-4"><a class="module-card d-block text-decoration-none text-dark" href="<?= e(app_url('modulos_page/reportes.php')) ?>"><span class="badge badge-logico mb-3">HU026 - HU028</span><h3 class="h5 fw-bold">Reportes</h3><p class="mb-2">Consultar reportes diarios, mensuales y anuales.</p><strong><?= $counts['movimientos'] ?></strong> movimientos analizados</a></div>
        <div class="col-12 col-md-4"><a class="module-card d-block text-decoration-none text-dark" href="<?= e(app_url('modulos_page/usuarios.php')) ?>"><span class="badge badge-logico mb-3">Seguridad</span><h3 class="h5 fw-bold">Usuarios</h3><p class="mb-2">Crear cuentas por rol y vincularlas con motoristas o locales registrados.</p><strong><?= $counts['usuarios'] ?></strong> usuario(s)</a></div>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
