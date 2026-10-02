<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$alerts = [];

$stmt = $pdo->query("
    SELECT bag_id, blood_type, expiry_date, DATEDIFF(expiry_date, NOW()) as days_left 
    FROM BloodBags 
    WHERE status = 'Available' 
    AND DATEDIFF(expiry_date, NOW()) BETWEEN 0 AND 7
");
while ($row = $stmt->fetch()) {
    $alerts[] = [
        'type' => 'warning',
        'title' => 'على وشك الانتهاء',
        'message' => "كيس #{$row['bag_id']} ({$row['blood_type']}) ينتهي خلال {$row['days_left']} أيام"
    ];
}

$stmt = $pdo->query("
    SELECT bag_id, blood_type FROM BloodBags 
    WHERE status = 'Available' AND expiry_date < NOW()
");
while ($row = $stmt->fetch()) {
    $alerts[] = [
        'type' => 'danger',
        'title' => 'كيس منتهي',
        'message' => "كيس #{$row['bag_id']} ({$row['blood_type']}) انتهت صلاحيته!"
    ];
    $pdo->prepare("UPDATE BloodBags SET status='Expired' WHERE bag_id=?")->execute([$row['bag_id']]);
}

$stmt = $pdo->query("
    SELECT blood_type, COUNT(*) as count FROM BloodBags 
    WHERE status = 'Available' GROUP BY blood_type HAVING count < 5
");
while ($row = $stmt->fetch()) {
    $alerts[] = [
        'type' => 'info',
        'title' => 'مخزون منخفض',
        'message' => "تبقت {$row['count']} وحدة فقط من فصيلة {$row['blood_type']}"
    ];
}

$pendingCount = $pdo->query("SELECT COUNT(*) FROM BloodRequests WHERE status='Pending'")->fetchColumn();
if ($pendingCount > 0) {
    $alerts[] = [
        'type' => 'primary',
        'title' => 'طلبات معلقة',
        'message' => "$pendingCount طلب/طلبات دم بانتظار الموافقة"
    ];
}

// 🚑 تنبيهات نظام الإنقاذ الذكي
require_once __DIR__ . '/../includes/rescue_functions.php';
$rc = rescueCounts($pdo);
if ($rc['suggested'] > 0) {
    $alerts[] = ['type' => 'primary', 'title' => 'نقل إنقاذي مقترح',
        'message' => "يوجد {$rc['suggested']} اقتراح نقل فائض دم بانتظار موافقتك"];
}
if ($rc['wasted_risk'] > 0) {
    $alerts[] = ['type' => 'danger', 'title' => 'هدر محتمل',
        'message' => "{$rc['wasted_risk']} كيس معرض للهدر خلال 7 أيام — راجع نظام الإنقاذ الذكي"];
}

echo json_encode($alerts);
