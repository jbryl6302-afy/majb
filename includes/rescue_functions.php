<?php
/**
 * 🚑 نظام الإنقاذ الذكي لفائض الدم
 * محرك اكتشاف الفائض + ترتيب الأولويات + الحماية CSRF
 */

const RESCUE_EXPIRY_WINDOW_DAYS = 21; // نافذة الأكياس المعرضة للهدر
const RESCUE_SURPLUS_RATIO      = 1.2; // فائض = المتاح > 1.2 × الطلب المتوقع
const RESCUE_MIN_STOCK          = 5;   // حد أدنى للمخزون قبل الحديث عن فائض

function csrfToken() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrfCheck() {
    return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
}

function rescueDaysLeft($expiryDate) {
    return max(0, (int)(new DateTime())->diff(new DateTime($expiryDate))->format('%a'));
}

/**
 * الطلب المتوقع لكل فصيلة: من نموذج الذكاء الاصطناعي أولاً، ثم احتياجات المنشآت كاحتياطي
 */
function rescuePredictedDemand($pdo) {
    $demand = [];
    try {
        $res = @file_get_contents('http://localhost:5000/rescue/demand/' . date('n') . '/' . date('Y'));
        if ($res) {
            foreach ((array)json_decode($res, true) as $row) {
                $demand[$row['blood_type']] = (int)$row['predicted_units'];
            }
        }
    } catch (Exception $e) { /* خدمة AI متوقفة */ }

    $stmt = $pdo->query("SELECT blood_type, SUM(units_needed) AS units FROM FacilityNeeds GROUP BY blood_type");
    while ($row = $stmt->fetch()) {
        if (!isset($demand[$row['blood_type']])) $demand[$row['blood_type']] = (int)$row['units'];
    }
    foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt) {
        if (!isset($demand[$bt])) $demand[$bt] = 10;
    }
    return $demand;
}

/**
 * اكتشاف أكياس الفائض المعرضة للهدر (متاحة + فوق الطلب المتوقع + قرب انتهاء الصلاحية)
 */
function rescueDetectSurplusBags($pdo, $predictedDemand = null) {
    if ($predictedDemand === null) $predictedDemand = rescuePredictedDemand($pdo);

    $stmt = $pdo->query("SELECT blood_type, COUNT(*) AS available_count FROM BloodBags WHERE status='Available' GROUP BY blood_type");
    $surplusTypes = [];
    while ($row = $stmt->fetch()) {
        $expected = max($predictedDemand[$row['blood_type']] ?? 10, 1);
        if ($row['available_count'] > RESCUE_MIN_STOCK && $row['available_count'] > $expected * RESCUE_SURPLUS_RATIO) {
            $surplusTypes[] = $row['blood_type'];
        }
    }
    if (empty($surplusTypes)) return [];

    $ph = implode(',', array_fill(0, count($surplusTypes), '?'));
    $bagStmt = $pdo->prepare("SELECT b.*, DATEDIFF(expiry_date, NOW()) AS days_left
                              FROM BloodBags b
                              WHERE b.status='Available' AND b.blood_type IN ($ph)
                                AND b.expiry_date <= DATE_ADD(NOW(), INTERVAL " . RESCUE_EXPIRY_WINDOW_DAYS . " DAY)
                              ORDER BY b.expiry_date ASC");
    $bagStmt->execute($surplusTypes);
    return $bagStmt->fetchAll();
}

/**
 * ترتيب الجهات المستفيدة:
 * 40% استعجال الحاجة + 25% قرب انتهاء الكيس + 20% حجم الاحتياج + 15% قرب المسافة
 */
function rescueRankFacilities($pdo, $bloodType, $bagDaysLeft) {
    $stmt = $pdo->prepare("SELECT f.*, n.units_needed, n.urgency
                           FROM Facilities f
                           JOIN FacilityNeeds n ON n.facility_id = f.facility_id
                           WHERE f.is_active = TRUE AND n.blood_type = ? AND n.units_needed > 0");
    $stmt->execute([$bloodType]);
    $out = [];
    foreach ($stmt->fetchAll() as $f) {
        $urgencyW = ['Critical' => 1.0, 'Urgent' => 0.7, 'Normal' => 0.4][$f['urgency']] ?? 0.4;
        $expiryW  = $bagDaysLeft <= 3 ? 1.0 : ($bagDaysLeft <= 7 ? 0.7 : ($bagDaysLeft <= 14 ? 0.4 : 0.2));
        $needW    = min(1, $f['units_needed'] / 10);
        $distW    = 1 / (1 + ($f['distance_km'] / 10));
        $f['priority_score'] = round(100 * (0.40 * $urgencyW + 0.25 * $expiryW + 0.20 * $needW + 0.15 * $distW), 1);
        $out[] = $f;
    }
    usort($out, fn($a, $b) => $b['priority_score'] <=> $a['priority_score']);
    return $out;
}

/**
 * توليد اقتراحات النقل (أفضل جهة لكل كيس) وإرجاع عدد الاقتراحات الجديدة
 */
function rescueGenerateSuggestions($pdo) {
    $created = 0;
    foreach (rescueDetectSurplusBags($pdo) as $bag) {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM Transfers WHERE bag_id = ? AND status IN ('Suggested','Approved','InTransit')");
        $exists->execute([$bag['bag_id']]);
        if ($exists->fetchColumn() > 0) continue;

        $ranked = rescueRankFacilities($pdo, $bag['blood_type'], (int)$bag['days_left']);
        if (empty($ranked)) continue;

        $best = $ranked[0];
        $pdo->prepare("INSERT INTO Transfers (bag_id, to_facility_id, priority_score, status, notes)
                       VALUES (?, ?, ?, 'Suggested', ?)")
            ->execute([$bag['bag_id'], $best['facility_id'], $best['priority_score'],
                       'أفضل خيار من بين ' . count($ranked) . ' جهات مرشحة']);
        $created++;
    }
    return $created;
}

function rescueCounts($pdo) {
    return [
        'suggested'  => (int)$pdo->query("SELECT COUNT(*) FROM Transfers WHERE status='Suggested'")->fetchColumn(),
        'in_transit' => (int)$pdo->query("SELECT COUNT(*) FROM Transfers WHERE status IN ('Approved','InTransit')")->fetchColumn(),
        'completed'  => (int)$pdo->query("SELECT COUNT(*) FROM Transfers WHERE status='Completed'")->fetchColumn(),
        'wasted_risk'=> (int)$pdo->query("SELECT COUNT(*) FROM BloodBags WHERE status='Available' AND expiry_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    ];
}
