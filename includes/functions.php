<?php
/**
 * Funciones comunes de LogiCo.
 * Centraliza sanitización, mensajes, validación, roles, rutas y control de sesión.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const SESSION_TIMEOUT_SECONDS = 1800;

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function app_base_url(): string
{
    // Si APP_BASE_URL está definido y no está vacío, se respeta esa configuración.
    // Si queda vacío, se detecta automáticamente la carpeta del proyecto en htdocs.
    if (defined('APP_BASE_URL') && trim((string)APP_BASE_URL) !== '') {
        return rtrim((string)APP_BASE_URL, '/') . '/';
    }

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $markers = ['/seguridad/', '/modulos_page/', '/vistas_rol/', '/public/', '/includes/', '/config/', '/database/'];

    foreach ($markers as $marker) {
        $pos = strpos($scriptName, $marker);
        if ($pos !== false) {
            return rtrim(substr($scriptName, 0, $pos), '/') . '/';
        }
    }

    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '.') {
        return '/';
    }
    return rtrim($dir, '/') . '/';
}

function app_url(string $path = ''): string
{
    return rtrim(app_base_url(), '/') . '/' . ltrim($path, '/');
}

function active_nav(string $page, string $current): string
{
    return $page === $current ? 'active' : '';
}

function redirect_with(string $url, string $status, string $message): void
{
    $separator = str_contains($url, '?') ? '&' : '?';
    header('Location: ' . $url . $separator . http_build_query([
        'status' => $status,
        'message' => $message,
    ]));
    exit;
}

function redirect_to(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function get_flash(): ?array
{
    if (!isset($_GET['status'], $_GET['message'])) {
        return null;
    }
    $status = $_GET['status'] === 'ok' ? 'success' : 'danger';
    return [
        'class' => $status,
        'message' => (string)$_GET['message'],
    ];
}

function validate_required(array $data, array $required): array
{
    $errors = [];
    foreach ($required as $field => $label) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $errors[] = "El campo {$label} es obligatorio.";
        }
    }
    return $errors;
}

function current_user(): ?array
{
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }
    return [
        'id' => (int)$_SESSION['usuario_id'],
        'nombre' => (string)($_SESSION['usuario_nombre'] ?? ''),
        'rol' => (string)($_SESSION['usuario_rol'] ?? ''),
        'motorista_id' => isset($_SESSION['motorista_id']) ? (int)$_SESSION['motorista_id'] : null,
        'farmacia_id' => isset($_SESSION['farmacia_id']) ? (int)$_SESSION['farmacia_id'] : null,
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!isset($_SESSION['usuario_id'])) {
        redirect_with(app_url('seguridad/login.php'), 'error', 'Debe iniciar sesión para acceder al sistema.');
    }

    $lastActivity = (int)($_SESSION['ultimo_movimiento'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > SESSION_TIMEOUT_SECONDS) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        redirect_with(app_url('seguridad/login.php'), 'error', 'La sesión expiró por inactividad. Inicie sesión nuevamente.');
    }

    $_SESSION['ultimo_movimiento'] = time();
}

function require_role(array $allowedRoles): void
{
    require_login();
    $user = current_user();
    $currentRole = normalize_role((string)($user['rol'] ?? ''));
    $allowed = array_map('normalize_role', $allowedRoles);

    if (!$user || !in_array($currentRole, $allowed, true)) {
        redirect_with(dashboard_url_for_role($currentRole), 'error', 'No tiene permisos para acceder a este módulo.');
    }
}

function normalize_role(string $rol): string
{
    $clean = trim(strtolower($rol));
    $clean = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $clean);
    $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

    return match ($clean) {
        'administrador', 'admin' => 'Administrador',
        'motorista' => 'Motorista',
        'farmacia central', 'central' => 'Farmacia Central',
        'operador control despacho', 'operador de control de despacho', 'control de despacho', 'operador' => 'Operador Control Despacho',
        'local despacho', 'local de despacho', 'local' => 'Local Despacho',
        default => trim($rol),
    };
}

function login_user(PDO $pdo, array $user): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int)$user['id'];
    $_SESSION['usuario_nombre'] = (string)$user['nombre'];
    $_SESSION['usuario_rol'] = normalize_role((string)$user['rol']);
    $_SESSION['motorista_id'] = isset($user['motorista_id']) ? (int)$user['motorista_id'] : null;
    $_SESSION['farmacia_id'] = isset($user['farmacia_id']) ? (int)$user['farmacia_id'] : null;
    $_SESSION['ultimo_movimiento'] = time();

    $stmt = $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?');
    $stmt->execute([(int)$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function selected(string $current, string $expected): string
{
    return $current === $expected ? 'selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function dashboard_url_for_role(string $rol): string
{
    return match (normalize_role($rol)) {
        'Administrador' => app_url('vistas_rol/administrador/index.php'),
        'Motorista' => app_url('vistas_rol/motorista/index.php'),
        'Farmacia Central' => app_url('vistas_rol/farmacia_central/index.php'),
        'Operador Control Despacho' => app_url('vistas_rol/operador_control/index.php'),
        'Local Despacho' => app_url('vistas_rol/local_despacho/index.php'),
        default => app_url('seguridad/login.php'),
    };
}

function estados_movimiento(): array
{
    return [
        'Pendiente local',
        'Producto no disponible',
        'Preparando',
        'Listo para retiro',
        'Asignado a motorista',
        'En curso',
        'Terminado',
        'No entregado',
        'Incidencia',
        'Anulado',
        'Reenviado',
    ];
}

function estados_finales_movimiento(): array
{
    return [
        'Terminado',
        'No entregado',
        'Incidencia',
        'Anulado',
    ];
}

function movimiento_cerrado(?string $estado): bool
{
    return in_array((string)$estado, estados_finales_movimiento(), true);
}

function log_historial_movimiento(PDO $pdo, int $movimientoId, ?int $usuarioId, ?string $estadoAnterior, string $estadoNuevo, ?string $observacion = null): void
{
    $stmt = $pdo->prepare('INSERT INTO historial_movimientos (movimiento_id, usuario_id, estado_anterior, estado_nuevo, observacion) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$movimientoId, $usuarioId, $estadoAnterior, $estadoNuevo, $observacion]);
}
