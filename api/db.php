<?php
declare(strict_types=1);

// ── CORS (runs before any route logic in endpoints that require db.php) ──
// CT_CORS_ORIGINS: comma list, e.g. "https://nawris.nawras-ly.com,http://localhost:5173"
// Empty or * → Access-Control-Allow-Origin: * (no credentials from browser with *)
$ctCors = trim((string) (getenv('CT_CORS_ORIGINS') ?: ''));
$reqOrigin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));

if ($ctCors === '' || $ctCors === '*') {
    header('Access-Control-Allow-Origin: *');
} else {
    $list = array_values(array_filter(array_map('trim', explode(',', $ctCors)), static fn ($s) => $s !== ''));
    if ($reqOrigin !== '' && in_array($reqOrigin, $list, true)) {
        header('Access-Control-Allow-Origin: ' . $reqOrigin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
    } elseif (in_array('*', $list, true)) {
        header('Access-Control-Allow-Origin: *');
    }
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header(
    'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-TOKEN, X-Api-Token, Accept, apikey'
);
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function crm_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'logistics_crm';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
