<?php
require_once __DIR__ . '/functions.php';
$currentPage = $currentPage ?? 'inicio';
$pageTitle = $pageTitle ?? 'LogiCo';
$user = current_user();
$role = $user ? normalize_role((string)$user['rol']) : null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | LogiCo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(app_url('public/assets/css/styles.css')) ?>" rel="stylesheet">
</head>
<body>
<header class="topbar border-bottom border-dark">
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid px-3 px-lg-4">
            <a class="navbar-brand logo" href="<?= e(app_url('index.php')) ?>" aria-label="Ir al inicio de LogiCo">
                <span class="logo-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                <span class="logo-text">LogiCo.</span>
            </a>
            <button class="navbar-toggler border-dark" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Abrir navegación">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav ms-auto gap-lg-2 mt-3 mt-lg-0 align-items-lg-center">
                    <?php if ($user): ?>
                        <?php if ($role === 'Administrador'): ?>
                            <li class="nav-item"><a class="nav-link <?= active_nav('inicio', $currentPage) ?>" href="<?= e(app_url('vistas_rol/administrador/index.php')) ?>">Panel administrador</a></li>
                            <li class="nav-item"><a class="nav-link <?= active_nav('farmacias', $currentPage) ?>" href="<?= e(app_url('modulos_page/farmacias.php')) ?>">Farmacias</a></li>
                            <li class="nav-item"><a class="nav-link <?= active_nav('motoristas', $currentPage) ?>" href="<?= e(app_url('modulos_page/motoristas.php')) ?>">Motoristas</a></li>
                            <li class="nav-item"><a class="nav-link <?= active_nav('motos', $currentPage) ?>" href="<?= e(app_url('modulos_page/motos.php')) ?>">Motos</a></li>
                            <li class="nav-item"><a class="nav-link <?= active_nav('asignaciones', $currentPage) ?>" href="<?= e(app_url('modulos_page/asignaciones.php')) ?>">Asignaciones</a></li>
                            <li class="nav-item"><a class="nav-link <?= active_nav('movimientos', $currentPage) ?>" href="<?= e(app_url('modulos_page/movimientos.php')) ?>">Movimientos</a></li>
                            <li class="nav-item"><a class="nav-link <?= active_nav('reportes', $currentPage) ?>" href="<?= e(app_url('modulos_page/reportes.php')) ?>">Reportes</a></li>
                        <?php elseif ($role === 'Motorista'): ?>
                            <li class="nav-item"><a class="nav-link <?= active_nav('inicio', $currentPage) ?>" href="<?= e(app_url('vistas_rol/motorista/index.php')) ?>">Panel motorista</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= e(app_url('vistas_rol/motorista/index.php#pedidos-asignados')) ?>">Pedidos asignados</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= e(app_url('vistas_rol/motorista/index.php#resumen-estados')) ?>">Estados</a></li>
                        <?php elseif ($role === 'Farmacia Central'): ?>
                            <li class="nav-item"><a class="nav-link <?= active_nav('inicio', $currentPage) ?>" href="<?= e(app_url('vistas_rol/farmacia_central/index.php')) ?>">Panel farmacia central</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= e(app_url('vistas_rol/farmacia_central/index.php#generar-orden')) ?>">Generar orden</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= e(app_url('vistas_rol/farmacia_central/index.php#estado-pedidos')) ?>">Estado pedidos</a></li>
                        <?php elseif ($role === 'Operador Control Despacho'): ?>
                            <li class="nav-item"><a class="nav-link <?= active_nav('inicio', $currentPage) ?>" href="<?= e(app_url('vistas_rol/operador_control/index.php')) ?>">Panel control despacho</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= e(app_url('vistas_rol/operador_control/index.php#monitoreo')) ?>">Monitoreo</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= e(app_url('vistas_rol/operador_control/index.php#asignar-motorista')) ?>">Asignar motorista</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= e(app_url('modulos_page/reportes.php')) ?>">Reportes</a></li>
                        <?php elseif ($role === 'Local Despacho'): ?>
                            <li class="nav-item"><a class="nav-link <?= active_nav('inicio', $currentPage) ?>" href="<?= e(app_url('vistas_rol/local_despacho/index.php')) ?>">Panel local despacho</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= e(app_url('vistas_rol/local_despacho/index.php#pedidos-recibidos')) ?>">Pedidos recibidos</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= e(app_url('vistas_rol/local_despacho/index.php#preparacion')) ?>">Preparación</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link <?= active_nav('inicio', $currentPage) ?>" href="<?= e(dashboard_url_for_role((string)$role)) ?>">Inicio</a></li>
                        <?php endif; ?>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle nav-user" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?= e((string)$role) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end border-dark">
                                <li><span class="dropdown-item-text small text-muted"><?= e((string)$user['nombre']) ?></span></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($role === 'Administrador'): ?>
                                    <li><a class="dropdown-item <?= active_nav('usuarios', $currentPage) ?>" href="<?= e(app_url('modulos_page/usuarios.php')) ?>">Crear cuentas de usuarios</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?= e(app_url('seguridad/modificar_contrasena.php')) ?>">Cambiar contraseña</a></li>
                                <li><a class="dropdown-item" href="<?= e(app_url('seguridad/logout.php')) ?>">Cerrar sesión</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link <?= active_nav('login', $currentPage) ?>" href="<?= e(app_url('seguridad/login.php')) ?>">Iniciar sesión</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
<main class="container-fluid px-3 px-lg-4 py-4">
