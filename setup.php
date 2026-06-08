<?php
// Instalador simple para XAMPP: crea la base de datos logico_entrega3 y sus tablas.
// Abrir en el navegador: http://localhost/logico_entrega3/setup.php

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$SQL_FILE = __DIR__ . '/database/logico.sql';

function render($title, $message, $type = 'success') {
    $alert = $type === 'success' ? 'alert-success' : 'alert-danger';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . htmlspecialchars($title) . '</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="public/assets/css/styles.css" rel="stylesheet"></head><body>';
    echo '<main class="container py-5"><section class="hero-card">';
    echo '<h1 class="section-title mb-3">' . htmlspecialchars($title) . '</h1>';
    echo '<div class="alert ' . $alert . ' border-dark">' . $message . '</div>';
    echo '<a class="btn btn-logico" href="index.php">Ir al sistema</a>';
    echo '</section></main></body></html>';
    exit;
}

try {
    if (!file_exists($SQL_FILE)) {
        render('Error de instalación', 'No se encontró el archivo <strong>database/logico.sql</strong>.', 'danger');
    }

    $pdo = new PDO("mysql:host={$DB_HOST};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $sql = file_get_contents($SQL_FILE);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    render('Instalación completada', 'La base de datos <strong>logico_entrega3</strong> y sus tablas fueron creadas correctamente. Ya puedes entrar al prototipo.');
} catch (PDOException $e) {
    render('Error de instalación', '<strong>Detalle:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>Revisa que MySQL esté iniciado en XAMPP.', 'danger');
}
