<?php
declare(strict_types=1);

// ── CORS Headers ──
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, apikey, Prefer, Range, X-Requested-With");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── PDO Singleton ─────────────────────────────────────────────────────────────
function crm_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'u495355717_ZIDON';
    $user = getenv('DB_USER') ?: 'u495355717_zxzxzxyol00';
    $pass = getenv('DB_PASS') ?: 'ziDONA11';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);

    // Session-level hints for shared-hosting MySQL
    $pdo->exec("SET
        SESSION innodb_lock_wait_timeout = 10,
        SESSION group_concat_max_len     = 1000000,
        SESSION wait_timeout             = 28800
    ");

    return $pdo;
}

// ── JSON response helper ──────────────────────────────────────────────────────
function json_response(array $payload, int $status = 200): void
{
    global $_REQ_START, $_DB_TIME;
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    // ── Profiling headers — visible in DevTools → Network → Headers tab ──────
    $encStart = microtime(true);
    $json     = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $encMs    = round((microtime(true) - $encStart) * 1000, 1);
    $totalMs  = isset($_REQ_START) ? round((microtime(true) - $_REQ_START) * 1000, 1) : 0;
    $dbMs     = isset($_DB_TIME)   ? round($_DB_TIME * 1000, 1) : 0;

    header("X-Timing-DB: {$dbMs}ms");
    header("X-Timing-Encode: {$encMs}ms");
    header("X-Timing-Total: {$totalMs}ms");
    header("X-Row-Count: " . count($payload));
    header("X-Payload-KB: " . round(strlen($json) / 1024, 1));

    // ── gzip compression — يُقلل الحجم 70-80٪ على الاتصالات البطيئة ──────────
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    if (
        strpos($acceptEncoding, 'gzip') !== false
        && strlen($json) > 1024          // لا فائدة من ضغط الردود الصغيرة
        && function_exists('gzencode')
    ) {
        $compressed = gzencode($json, 6); // مستوى 6: توازن جيد بين السرعة والحجم
        if ($compressed !== false && strlen($compressed) < strlen($json)) {
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
            echo $compressed;
            exit;
        }
    }

    echo $json;
    exit;
}

// ── Lightweight APCu-then-file TTL cache ──────────────────────────────────────
// Works on shared hosting (no Redis required).
// APCu is preferred (in-process, zero I/O); falls back to /tmp files.

function _cache_key(string $key): string
{
    return 'nawris_' . md5($key);
}

function cache_get(string $key): mixed
{
    $ck = _cache_key($key);

    // APCu path (fastest — shared memory, no disk I/O)
    if (function_exists('apcu_fetch')) {
        $val = apcu_fetch($ck, $ok);
        return $ok ? $val : null;
    }

    // File fallback
    $f = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $ck . '.json';
    if (!is_file($f)) return null;
    $raw = @file_get_contents($f);
    if (!$raw) return null;
    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['exp'] ?? 0) < time()) {
        @unlink($f);
        return null;
    }
    return $data['v'];
}

function cache_set(string $key, mixed $value, int $ttl = 300): void
{
    $ck = _cache_key($key);

    if (function_exists('apcu_store')) {
        apcu_store($ck, $value, $ttl);
        return;
    }

    $f = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $ck . '.json';
    @file_put_contents($f, json_encode(['exp' => time() + $ttl, 'v' => $value]), LOCK_EX);
}

function cache_del(string $key): void
{
    $ck = _cache_key($key);
    if (function_exists('apcu_delete')) { apcu_delete($ck); return; }
    @unlink(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $ck . '.json');
}
