<?php
declare(strict_types=1);

// 1. استدعاء ملف الاتصال المباشر الجاهز الموجود في السيرفر
require_once __DIR__ . '/db.php';

// 2. التحقق من مفتاح الأمان (API Key) القادم من الـ JavaScript
$headers = getallheaders();
$apiKey = $headers['apikey'] ?? $headers['Apikey'] ?? '';

if ($apiKey !== 'NAWRIS_SECRET_2025') {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

// 3. قراءة نوع العملية وجسم الطلب
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$table  = $_GET['table'] ?? '';

// الجداول المسموح بالوصول إليها
$allowedTables = ['settings', 'drivers', 'branches', 'regions', 'stores', 'contacted_log', 'wa_templates', 'contact_results', 'shipments_view'];

if (!in_array($table, $allowedTables)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid table name: " . $table]);
    exit;
}

try {
    // 4. استدعاء دالة الاتصال المباشرة الجاهزة من ملف db.php
    $pdo = crm_pdo();
    
    if ($method === 'GET') {
        $keyFilter = $_GET['key'] ?? '';
        
        // إذا كان الطلب يبحث عن إعداد معين (مثل حسابات الموظفين)
        if (strpos($keyFilter, 'eq.') === 0) {
            $keyValue = substr($keyFilter, 3);
            $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `key` = ?");
            $stmt->execute([$keyValue]);
            
            // استخدام دالة الاستجابة الجاهزة في السيرفر لديك
            json_response($stmt->fetchAll());
        } else {
            // جلب البيانات بالكامل للجدول المطلوب
            $stmt = $pdo->query("SELECT * FROM `$table`");
            json_response($stmt->fetchAll());
        }
    } 
    elseif ($method === 'POST') {
        if (!empty($input)) {
            foreach ($input as $row) {
                if ($table === 'settings') {
                    $stmt = $pdo->prepare("INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) 
                                           ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
                    $stmt->execute([$row['key'], $row['value']]);
                }
                // يمكنك إضافة جمل الإدخال لبقية الجداول هنا عند الحاجة
            }
            json_response(["success" => true]);
        } else {
            http_response_code(400);
            json_response(["error" => "No data provided"]);
        }
    }
} catch (\Throwable $e) {
    // في حال حدوث أي خطأ، يتم عرضه برمجياً بدلاً من انهيار السيرفر بخطأ 500 غامض
    http_response_code(500);
    echo json_encode(["error" => "API Error: " . $e->getMessage()]);
    exit;
}
