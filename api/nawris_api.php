<?php
declare(strict_types=1);

// ── CORS ─────────────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey, Prefer, Range');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';

// ── Config ────────────────────────────────────────────────────────────────────
// ضع مفتاحك السري هنا — نفس القيمة في index.html (SK)
define('NAWRIS_API_KEY', getenv('NAWRIS_API_KEY') ?: 'NAWRIS_SECRET_2025');

// الجداول المسموح بها فقط — يمنع SQL injection تماماً
const ALLOWED_TABLES = [
    'settings', 'shipments', 'contact_results', 'drivers',
    'branches', 'regions', 'stores', 'wa_templates',
    'returns', 'transfers', 'contacted_log',
];

// معاملات Supabase الخاصة — لا تُستخدم كفلاتر
const RESERVED_PARAMS = [
    'select', 'limit', 'offset', 'order', 'count', 'on_conflict',
    'columns', 'XDEBUG_SESSION_START',
];

// ── Auth ──────────────────────────────────────────────────────────────────────
$sentKey = $_SERVER['HTTP_APIKEY']
    ?? $_SERVER['HTTP_API_KEY']
    ?? trim(str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? ''))
    ?? '';
if ($sentKey !== NAWRIS_API_KEY) {
    json_response(['error' => 'Unauthorized'], 401);
}

// ── Table name from URL (/rest/v1/{table}) ────────────────────────────────────
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

function safeColumns(string $raw): string
{
    if (trim($raw) === '*' || trim($raw) === '') return '*';
    $cols = array_map(
        fn($c) => '`' . preg_replace('/[^a-zA-Z0-9_]/', '', trim($c)) . '`',
        explode(',', $raw)
    );
    $cols = array_filter($cols, fn($c) => $c !== '``');
    return $cols ? implode(', ', $cols) : '*';
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

        // count=exact mode (Supabase compat for sbCount)
        if (str_contains($prefer, 'count=exact')) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM `$table` $whereStr");
            $st->execute($binds);
            $count = (int)$st->fetchColumn();
            $range = $_SERVER['HTTP_RANGE'] ?? '0-0';
            header("Content-Range: $range/$count");
            json_response([]);
        }

        $cols   = safeColumns($qp['select'] ?? '*');
        $limit  = min((int)($qp['limit'] ?? 1000), 10000);
        $offset = max((int)($qp['offset'] ?? 0), 0);

        $orderStr = '';
        if (!empty($qp['order'])) {
            $ord = preg_replace('/[^a-zA-Z0-9_, ]/', '', $qp['order']);
            if ($ord) $orderStr = "ORDER BY $ord";
        }

        $sql = "SELECT $cols FROM `$table` $whereStr $orderStr LIMIT $limit OFFSET $offset";
        $st  = $pdo->prepare($sql);
        $st->execute($binds);
        json_response($st->fetchAll());
    }

    // ── POST (insert / upsert) ────────────────────────────────────────────────
    case 'POST': {
        $rows = is_array($body) && isset($body[0]) ? $body : [$body];
        $rows = array_filter($rows, fn($r) => is_array($r) && count($r));
        if (!$rows) { json_response(['success' => true], 201); }

        $rows     = array_values($rows);
        $colNames = array_map(
            fn($c) => preg_replace('/[^a-zA-Z0-9_]/', '', $c),
            array_keys($rows[0])
        );
        $colNames = array_filter($colNames);
        if (!$colNames) { json_response(['success' => true], 201); }

        $colStr = '`' . implode('`, `', $colNames) . '`';
        $phStr  = ':' . implode(', :', $colNames);

        $isUpsert = str_contains($prefer, 'merge-duplicates');
        $isIgnore = str_contains($prefer, 'ignore-duplicates');

        $verb   = $isIgnore ? 'INSERT IGNORE' : 'INSERT';
        $suffix = '';
        if ($isUpsert) {
            $updates = implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", $colNames));
            $suffix  = " ON DUPLICATE KEY UPDATE $updates";
        }

        $sql = "$verb INTO `$table` ($colStr) VALUES ($phStr)$suffix";
        $st  = $pdo->prepare($sql);

        foreach ($rows as $row) {
            $vals = [];
            foreach ($colNames as $c) $vals[$c] = isset($row[$c]) ? $row[$c] : null;
            $st->execute($vals);
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
            $c         = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            if (!$c) continue;
            $ph        = ':s_' . $c;
            $setCols[] = "`$c` = $ph";
            $setBinds[$ph] = $val;
        }
        if (!$setCols) { json_response(['success' => true]); }

        $sql = "UPDATE `$table` SET " . implode(', ', $setCols)
             . ' WHERE ' . implode(' AND ', $where);
        $st  = $pdo->prepare($sql);
        $st->execute(array_merge($setBinds, $filterBinds));
        json_response(['success' => true]);
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    case 'DELETE': {
        [$where, $binds] = parseFilters($qp);
        if (!$where) json_response(['error' => 'DELETE requires at least one filter'], 400);

        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $where);
        $st  = $pdo->prepare($sql);
        $st->execute($binds);
        json_response(['success' => true]);
    }

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
