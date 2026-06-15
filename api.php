<?php
// api.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, apikey");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 1. بيانات الاتصال بقاعدة بيانات MySQL (تجدها في لوحة Hostinger)
$host = "localhost";
$dbname = "u495355717_ZIDON";
$username = "u495355717_zxzxzxyo100";
$password = "Zidona11$";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

// 2. التحقق من مفتاح الأمان (اختياري ولكن مهم لحماية بياناتك)
$headers = getallheaders();
$apiKey = $headers['apikey'] ?? $headers['Apikey'] ?? '';
if ($apiKey !== 'NAWRIS_SECRET_2025') {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

// 3. قراءة المسار والمعطيات
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// فك تشفير المسار المطلوب (مثلاً: api.php/settings)
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = strtok($requestUri, '?');
$parts = explode('/', trim($basePath, '/'));
$table = end($parts); // اسم الجدول (settings, drivers, etc.)

if (!$table || $table == 'api.php') {
    // إذا كان المسار يمرر كـ Parameter مثل: api.php?table=settings
    $table = $_GET['table'] ?? '';
}

// القوائم المسموح بالوصول إليها لحماية السيرفر (White-list)
$allowedTables = ['settings', 'drivers', 'branches', 'regions', 'stores', 'contacted_log', 'wa_templates', 'contact_results'];
if (!in_array($table, $allowedTables)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid table name"]);
    exit;
}

// 4. معالجة العمليات (GET و POST)
if ($method === 'GET') {
    // معالجة الفلترة البسيطة لـ key=eq.nawris_users
    $keyFilter = $_GET['key'] ?? '';
    if (strpos($keyFilter, 'eq.') === 0) {
        $keyValue = substr($keyFilter, 3);
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `key` = ?");
        $stmt->execute([$keyValue]);
        echo json_encode($stmt->fetchAll());
    } else {
        // جلب كل البيانات
        $stmt = $pdo->query("SELECT * FROM `$table`");
        echo json_encode($stmt->fetchAll());
    }
} 
elseif ($method === 'POST') {
    if (!empty($input)) {
        // تحديث أو إدخال البيانات (Upsert)
        // ملاحظة: هذا الكود يفترض جدول settings بمفتاح فرعي فريد (key)
        foreach ($input as $row) {
            if ($table === 'settings') {
                $stmt = $pdo->prepare("INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
                $stmt->execute([$row['key'], $row['value']]);
            } else {
                // يمكنك إضافة جمل الإدخال لبقية الجداول هنا عند الحاجة
            }
        }
        echo json_encode(["success" => true]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "No data provided"]);
    }
}
?>
