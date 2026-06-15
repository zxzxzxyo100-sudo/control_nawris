<?php
declare(strict_types=1);
// TEMP DIAGNOSTIC — reveal fatal errors as JSON (remove after debugging)
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_exception_handler(function ($e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'diagnostic' => true,
        'error'      => $e->getMessage(),
        'file'       => $e->getFile(),
        'line'       => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
$_REQ_START = microtime(true); // global timer — used in X-Timing header

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey, Prefer, Range');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';

// ── Config ────────────────────────────────────────────────────────────────────
define('NAWRIS_API_KEY', getenv('NAWRIS_API_KEY') ?: 'NAWRIS_SECRET_2025');

const ALLOWED_TABLES = [
    'settings', 'shipments', 'shipments_view', 'contact_results', 'drivers',
    'branches', 'regions', 'stores', 'wa_templates',
    'returns', 'transfers', 'contacted_log',
];

const RESERVED_PARAMS = [
    'select', 'limit', 'offset', 'order', 'count', 'on_conflict',
    'columns', 'XDEBUG_SESSION_START',
];

// Static/reference tables that change rarely → cache aggressively (10 min)
const STATIC_TABLES = ['branches', 'regions', 'stores', 'wa_templates'];

// Per-table hard caps on rows returned — prevents accidental full-table dumps
const TABLE_MAX_ROWS = [
    'shipments'      => 500,
    'shipments_view' => 1000,   // view joins shipments+drivers — higher cap for full load
    'contact_results'=> 2000,   // staff stats need all results
    'contacted_log'  => 5000,   // IDs only (shipment_id), needs all rows for contacted set
    'returns'        => 500,
    'transfers'      => 500,
    'drivers'        => 200,
    'branches'       => 200,
    'regions'        => 200,
    'stores'         => 200,
    'wa_templates'   => 100,
    'settings'       => 200,
];

// ── Auth ──────────────────────────────────────────────────────────────────────
$sentKey = $_SERVER['HTTP_APIKEY']
    ?? $_SERVER['HTTP_API_KEY']
    ?? trim(str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? ''))
    ?? '';
if ($sentKey !== NAWRIS_API_KEY) {
    json_response(['error' => 'Unauthorized'], 401);
}

// ── Table from URL ────────────────────────────────────────────────────────────
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!preg_match('#/rest/v1/([^/?]+)#', $uri, $m)) {
    json_response(['error' => 'Invalid endpoint — expected /rest/v1/{table}'], 404);
}
$table = preg_replace('/[^a-zA-Z0-9_]/', '', $m[1]);
if (!in_array($table, ALLOWED_TABLES, true)) {
    json_response(['error' => "Unknown table: $table"], 404);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function parseFilters(array $qp): array
{
    $where = [];
    $binds = [];
    foreach ($qp as $rawKey => $rawVal) {
        if (in_array($rawKey, RESERVED_PARAMS, true)) continue;
        $col = preg_replace('/[^a-zA-Z0-9_]/', '', $rawKey);
        if (!$col || !is_string($rawVal)) continue;

        $dotPos = strpos($rawVal, '.');
        if ($dotPos === false) continue;

        $op  = substr($rawVal, 0, $dotPos);
        $val = substr($rawVal, $dotPos + 1);
        $ph  = ':w_' . $col . '_' . count($binds);

        switch ($op) {
            case 'eq':
                $where[] = "`$col` = $ph";
                $binds[$ph] = ($val === 'null') ? null : $val;
                break;
            case 'neq':
                $where[] = "`$col` != $ph";
                $binds[$ph] = $val;
                break;
            case 'gt':  $where[] = "`$col` > $ph";  $binds[$ph] = $val; break;
            case 'lt':  $where[] = "`$col` < $ph";  $binds[$ph] = $val; break;
            case 'gte': $where[] = "`$col` >= $ph"; $binds[$ph] = $val; break;
            case 'lte': $where[] = "`$col` <= $ph"; $binds[$ph] = $val; break;
            case 'like':
            case 'ilike':
                $where[] = "`$col` LIKE $ph";
                $binds[$ph] = $val;
                break;
            case 'is':
                if (strtolower($val) === 'null') {
                    $where[] = "`$col` IS NULL";
                } else {
                    $where[] = "`$col` = $ph";
                    $binds[$ph] = $val;
                }
                break;
            case 'in':
                $vals = array_filter(array_map('trim', explode(',', trim($val, '()'))));
                $phs  = [];
                foreach (array_values($vals) as $i => $v) {
                    $p = $ph . '_in' . $i;
                    $phs[] = $p;
                    $binds[$p] = $v;
                }
                if ($phs) $where[] = "`$col` IN (" . implode(', ', $phs) . ')';
                break;
        }
    }
    return [$where, $binds];
}

// Whitelist column names: strip anything that isn't a word char or comma
function safeColumns(string $raw): string
{
    if (trim($raw) === '*' || trim($raw) === '') return '*';
    $cols = array_map(
        function($c) { return '`' . preg_replace('/[^a-zA-Z0-9_]/', '', trim($c)) . '`'; },
        explode(',', $raw)
    );
    $cols = array_filter($cols, function($c) { return $c !== '``'; });
    return $cols ? implode(', ', $cols) : '*';
}

// Sanitise ORDER BY: allow only letters, digits, underscores, commas, spaces, and "asc"/"desc"
function safeOrder(string $raw): string
{
    // e.g. "delay_days.desc" → "delay_days DESC"
    $parts = array_filter(array_map('trim', explode(',', $raw)));
    $safe  = [];
    foreach ($parts as $part) {
        if (preg_match('/^([a-zA-Z0-9_]+)(?:\.(asc|desc))?$/i', $part, $pm)) {
            $dir    = isset($pm[2]) ? strtoupper($pm[2]) : 'ASC';
            $safe[] = "`{$pm[1]}` $dir";
        }
    }
    return $safe ? implode(', ', $safe) : '';
}

// ── Main dispatch ─────────────────────────────────────────────────────────────
$method = strtoupper($_SERVER['REQUEST_METHOD']);
$qp     = $_GET;
$pdo    = crm_pdo();
$prefer = strtolower($_SERVER['HTTP_PREFER'] ?? '');
$body   = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];

switch ($method) {

    // ── GET ───────────────────────────────────────────────────────────────────
    case 'GET': {
        [$where, $binds] = parseFilters($qp);
        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // count=exact — lightweight COUNT(*) only, no row fetch
        if (strpos($prefer,'count=exact') !== false) {
            $cacheKey = "count:{$table}:{$whereStr}:" . serialize($binds);
            $count = cache_get($cacheKey);
            if ($count === null) {
                $st = $pdo->prepare("SELECT COUNT(*) FROM `$table` $whereStr");
                $st->execute($binds);
                $count = (int) $st->fetchColumn();
                cache_set($cacheKey, $count, 30); // 30-second cache for counts
            }
            $range = $_SERVER['HTTP_RANGE'] ?? '0-0';
            header("Content-Range: $range/$count");
            json_response([]);
        }

        // ── Static-table cache (branches, regions, stores, wa_templates) ──────
        // These tables change only when an admin adds/edits entries.
        // Cache for 10 minutes — eliminates repeated identical SELECTs.
        $isStatic  = in_array($table, STATIC_TABLES, true);
        $hasFilter = !empty($where);
        if ($isStatic && !$hasFilter) {
            $sCacheKey = "static:{$table}:" . ($qp['select'] ?? '*');
            $cached    = cache_get($sCacheKey);
            if ($cached !== null) {
                // الجداول الثابتة: أخبر المتصفح بحفظ الرد 5 دقائق
                header('Cache-Control: public, max-age=300, stale-while-revalidate=60');
                json_response($cached);
            }
        }

        $cols      = safeColumns($qp['select'] ?? '*');
        $tableMax  = TABLE_MAX_ROWS[$table] ?? 200;
        $requested = (int)($qp['limit'] ?? $tableMax);
        $limit     = min(max($requested, 1), $tableMax);
        $offset    = max((int)($qp['offset'] ?? 0), 0);

        $orderStr = '';
        if (!empty($qp['order'])) {
            $ord = safeOrder($qp['order']);
            if ($ord) $orderStr = "ORDER BY $ord";
        }

        $sql = "SELECT $cols FROM `$table` $whereStr $orderStr LIMIT $limit OFFSET $offset";
        $st  = $pdo->prepare($sql);

        // Measure exact DB execution time (visible as X-Timing-DB in response headers)
        global $_DB_TIME;
        $_t0 = microtime(true);
        $st->execute($binds);
        $rows = $st->fetchAll();
        $_DB_TIME = microtime(true) - $_t0;

        // Store in static-table cache
        if ($isStatic && !$hasFilter) {
            cache_set($sCacheKey, $rows, 600); // 10 minutes
            header('Cache-Control: public, max-age=300, stale-while-revalidate=60');
        } else {
            // البيانات الديناميكية: لا تُخزَّن في المتصفح
            header('Cache-Control: no-store');
        }

        json_response($rows);
    }

    // ── POST (insert / upsert) ────────────────────────────────────────────────
    case 'POST': {
        $rows = is_array($body) && isset($body[0]) ? $body : [$body];
        $rows = array_values(array_filter($rows, function($r) { return is_array($r) && count($r); }));
        if (!$rows) { json_response(['success' => true], 201); }

        $colNames = array_values(array_filter(array_map(
            function($c) { return preg_replace('/[^a-zA-Z0-9_]/', '', $c); },
            array_keys($rows[0])
        )));
        if (!$colNames) { json_response(['success' => true], 201); }

        $colStr = '`' . implode('`, `', $colNames) . '`';
        $phStr  = ':' . implode(', :', $colNames);

        $isUpsert = strpos($prefer,'merge-duplicates') !== false;
        $isIgnore = strpos($prefer,'ignore-duplicates') !== false;

        $verb   = $isIgnore ? 'INSERT IGNORE' : 'INSERT';
        $suffix = '';
        if ($isUpsert) {
            $updates = implode(', ', array_map(function($c) { return "`$c` = VALUES(`$c`)"; }, $colNames));
            $suffix  = " ON DUPLICATE KEY UPDATE $updates";
        }

        $sql = "$verb INTO `$table` ($colStr) VALUES ($phStr)$suffix";
        $st  = $pdo->prepare($sql);

        // Wrap all rows in a single transaction — one commit instead of N commits
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $vals = [];
                foreach ($colNames as $c) $vals[$c] = $row[$c] ?? null;
                $st->execute($vals);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            json_response(['error' => 'Insert failed: ' . $e->getMessage()], 500);
        }

        // Invalidate static-table cache on writes
        if (in_array($table, STATIC_TABLES, true)) {
            cache_del("static:{$table}:*");
            cache_del("static:{$table}:" . ($qp['select'] ?? '*'));
        }

        json_response(['success' => true], 201);
    }

    // ── PATCH (update) ────────────────────────────────────────────────────────
    case 'PATCH': {
        if (!$body) { json_response(['success' => true]); }
        [$where, $filterBinds] = parseFilters($qp);
        if (!$where) json_response(['error' => 'PATCH requires at least one filter'], 400);

        $setCols  = [];
        $setBinds = [];
        foreach ($body as $col => $val) {
            $c = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            if (!$c) continue;
            $ph           = ':s_' . $c;
            $setCols[]    = "`$c` = $ph";
            $setBinds[$ph] = $val;
        }
        if (!$setCols) { json_response(['success' => true]); }

        $sql = "UPDATE `$table` SET " . implode(', ', $setCols)
             . ' WHERE ' . implode(' AND ', $where);
        $st  = $pdo->prepare($sql);
        $st->execute(array_merge($setBinds, $filterBinds));

        if (in_array($table, STATIC_TABLES, true)) {
            cache_del("static:{$table}:" . ($qp['select'] ?? '*'));
        }

        json_response(['success' => true]);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    case 'DELETE': {
        [$where, $binds] = parseFilters($qp);
        if (!$where) json_response(['error' => 'DELETE requires at least one filter'], 400);

        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $where);
        $st  = $pdo->prepare($sql);
        $st->execute($binds);

        if (in_array($table, STATIC_TABLES, true)) {
            cache_del("static:{$table}:" . ($qp['select'] ?? '*'));
        }

        json_response(['success' => true]);
    }

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
