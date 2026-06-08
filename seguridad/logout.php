<?php
require_once __DIR__ . '/../includes/functions.php';
logout_user();
redirect_with(app_url('seguridad/login.php'), 'ok', 'Sesión cerrada correctamente.');
