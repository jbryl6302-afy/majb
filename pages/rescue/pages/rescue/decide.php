<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/rescue_functions.php';

if (!isLoggedIn() || !hasRole('Admin')) redirect('../../dashboard.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) redirect('index.php');

$action      = $_POST['action'] ?? '';
$transfer_id = intval($_POST['transfer_id'] ?? 0);

// 🆕 طلب يدوي: نقل كيس مهدد بالهدر إلى جهة يختارها المستخدم
if ($action === 'request') {
    $bag_id      = intval($_POST['bag_id'] ?? 0);
    $facility_id = intval($_POST['facility_id'] ?? 0);

    $bag = $pdo->prepare("SELECT * FROM BloodBags WHERE bag_id = ? AND status='Available'");
    $bag->execute([$bag_id]);
    $bag = $bag->fetch();

    $fac = $pdo->prepare("SELECT * FROM Facilities WHERE facility_id = ? AND is_active = TRUE");
    $fac->execute([$facility_id]);
    $fac = $fac->fetch();

    $exists = $pdo->prepare("SELECT COUNT(*) FROM Transfers WHERE bag_id = ? AND status IN ('Suggested','Approved','InTransit')");
    $exists->execute([$bag_id]);

    if (!$bag) {
        setFlash('danger', 'الكيس غير موجود أو غير متاح (ممكن محجوز/منتهي)');
    } elseif (!$fac) {
        setFlash('danger', 'الجهة المختارة غير موجودة أو غير نشطة');
    } elseif ($exists->fetchColumn() > 0) {
        setFlash('warning', 'هذا الكيس عليه اقتراح/نقل نشط بالفعل');
    } else {
        // حساب الأولوية بنفس معادلة النظام: استعجال + انتهاء + احتياج + مسافة
        $daysLeft = rescueDaysLeft($bag['expiry_date']);
        $expW = $daysLeft <= 3 ? 1.0 : ($daysLeft <= 7 ? 0.7 : ($daysLeft <= 14 ? 0.4 : 0.2));
        $distW = 1 / (1 + ((float)$fac['distance_km'] / 10));

        $need = $pdo->prepare("SELECT units_needed, urgency FROM FacilityNeeds WHERE facility_id = ? AND blood_type = ?");
        $need->execute([$facility_id, $bag['blood_type']]);
        $need = $need->fetch();
        $urgW  = $need ? (['Critical' => 1.0, 'Urgent' => 0.7, 'Normal' => 0.4][$need['urgency']] ?? 0.4) : 0.4;
        $needW = $need ? min(1, $need['units_needed'] / 10) : 0.5;

        $score = round(100 * (0.40 * $urgW + 0.25 * $expW + 0.20 * $needW + 0.15 * $distW), 1);

        $pdo->prepare("INSERT INTO Transfers (bag_id, to_facility_id, priority_score, status, notes)
                       VALUES (?, ?, ?, 'Suggested', ?)")
            ->execute([$bag_id, $facility_id, $score, 'طلب يدوي لكيس مهدد بالهدر — متبقٍ ' . $daysLeft . ' يوم']);

        // لو الجهة ما عندها احتياج مسجل لهالفصيلة، سجّل احتياج تلقائي حتى يظهر لها مستقبلاً
        if (!$need) {
            $pdo->prepare("INSERT INTO FacilityNeeds (facility_id, blood_type, units_needed, urgency) VALUES (?,?,1,'Urgent')")
                ->execute([$facility_id, $bag['blood_type']]);
        }

        setFlash('success', "تم إرسال طلب النقل للكيس {$bag['qr_code']} إلى {$fac['name']} — بانتظار الموافقة ✅");
    }
    redirect('index.php');
}

$stmt = $pdo->prepare("SELECT t.*, b.qr_code, b.blood_type, f.name AS facility_name
                       FROM Transfers t JOIN BloodBags b ON t.bag_id = b.bag_id
                       LEFT JOIN Facilities f ON t.to_facility_id = f.facility_id
                       WHERE t.transfer_id = ?");
$stmt->execute([$transfer_id]);
$t = $stmt->fetch();

if ($t) {
    try {
        $pdo->beginTransaction();
        switch ($action) {
            case 'detect':
                break;
            case 'approve': // الموافقة: حجز الكيس + توليد رمز QR للتسليم
                $token = 'TRF-' . strtoupper(uniqid());
                $pdo->prepare("UPDATE Transfers SET status='Approved', decided_by=?, decided_at=NOW(), qr_token=? WHERE transfer_id=?")
                    ->execute([$_SESSION['user_id'], $token, $transfer_id]);
                $pdo->prepare("UPDATE BloodBags SET status='Reserved' WHERE bag_id=?")->execute([$t['bag_id']]);
                logTracking($pdo, $t['bag_id'], 'تمت الموافقة على نقل إنقاذي إلى: ' . $t['facility_name'], 'بنك الدم', $_SESSION['user_id']);
                setFlash('success', "تمت الموافقة على النقل #$transfer_id — اطبع ورقة التسليم ورمزها: $token");
                break;
            case 'ship': // الشحن
                $pdo->prepare("UPDATE Transfers SET status='InTransit' WHERE transfer_id=?")->execute([$transfer_id]);
                logTracking($pdo, $t['bag_id'], 'خرج في طريقه إلى: ' . $t['facility_name'], 'سيارة النقل', $_SESSION['user_id']);
                setFlash('info', "النقل #$transfer_id في الطريق الآن 🚑");
                break;
            case 'complete': // تأكيد الاستلام: خصم من احتياج الجهة + إغلاق الكيس
                $pdo->prepare("UPDATE Transfers SET status='Completed' WHERE transfer_id=?")->execute([$transfer_id]);
                $pdo->prepare("UPDATE BloodBags SET status='Transferred' WHERE bag_id=?")->execute([$t['bag_id']]);
                $pdo->prepare("UPDATE FacilityNeeds SET units_needed = GREATEST(units_needed - 1, 0) WHERE facility_id=? AND blood_type=?")
                    ->execute([$t['to_facility_id'], $t['blood_type']]);
                logTracking($pdo, $t['bag_id'], 'تم الاستلام بواسطة: ' . $t['facility_name'], 'الجهة المستفيدة', $_SESSION['user_id']);
                setFlash('success', "اكتمل النقل #$transfer_id وتم تحديث المخزون ✅");
                break;
            case 'reject':
                $pdo->prepare("UPDATE Transfers SET status='Rejected', decided_by=?, decided_at=NOW() WHERE transfer_id=?")
                    ->execute([$_SESSION['user_id'], $transfer_id]);
                setFlash('warning', "تم رفض الاقتراح #$transfer_id");
                break;
            case 'cancel':
                $pdo->prepare("UPDATE Transfers SET status='Cancelled', decided_by=?, decided_at=NOW() WHERE transfer_id=?")
                    ->execute([$_SESSION['user_id'], $transfer_id]);
                $pdo->prepare("UPDATE BloodBags SET status='Available' WHERE bag_id=? AND status='Reserved'")->execute([$t['bag_id']]);
                setFlash('warning', "تم إلغاء النقل #$transfer_id وإعادة الكيس للمخزون");
                break;
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', 'خطأ: ' . $e->getMessage());
    }
}
if ($action === 'detect') {
    $n = rescueGenerateSuggestions($pdo);
    setFlash($n > 0 ? 'success' : 'info', $n > 0 ? "تم إنشاء $n اقتراح نقل إنقاذي جديد" : 'لا يوجد فائض جديد يستحق النقل حالياً');
}
redirect('index.php');
