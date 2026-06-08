<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Puerta de entrada del sistema.
// Si el usuario no inició sesión, se envía al login.
// Si ya inició sesión, se deriva al panel correspondiente según su rol.
require_login();
$user = current_user();
redirect_to(dashboard_url_for_role((string)$user['rol']));
