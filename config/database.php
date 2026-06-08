<?php
// Configuración de conexión a MySQL para XAMPP.
// El proyecto queda preparado para la base de datos logico_entrega3.

$DB_HOST = 'localhost';
$DB_NAME = 'logico_entrega3';
$DB_USER = 'root';
$DB_PASS = '';
$DB_CHARSET = 'utf8mb4';

// Ruta base automática. Déjelo vacío para que el sistema detecte la carpeta en htdocs.
// Si necesita forzar una ruta, puede usar por ejemplo: define('APP_BASE_URL', '/logico_entrega3/');
define('APP_BASE_URL', '');

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Error de conexión</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body class="p-4">';
    echo '<div class="container"><div class="alert alert-danger border-dark">';
    echo '<h1 class="h4">No fue posible conectar con la base de datos.</h1>';
    echo '<p><strong>Detalle:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>Revise que MySQL esté iniciado en XAMPP y que exista la base de datos <strong>logico_entrega3</strong>.</p>';
    echo '</div></div></body></html>';
    exit;
}
