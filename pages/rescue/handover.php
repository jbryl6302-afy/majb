<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn()) redirect('../../index.php');

$transfer_id = intval($_GET['transfer_id'] ?? 0);
$stmt = $pdo->prepare("SELECT t.*, b.qr_code, b.blood_type, b.volume_ml, b.donation_date, b.expiry_date,
                       f.name AS facility_name, f.city, f.phone AS facility_phone
                       FROM Transfers t JOIN BloodBags b ON t.bag_id = b.bag_id
                       LEFT JOIN Facilities f ON t.to_facility_id = f.facility_id
                       WHERE t.transfer_id = ?");
$stmt->execute([$transfer_id]);
$t = $stmt->fetch();
if (!$t) die('❌ عملية النقل غير موجودة');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>ورقة تسليم نقل إنقاذي #<?php echo $t['transfer_id']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; background: #f0f0f0; display: flex; justify-content: center; padding: 20px; }
        .sheet { width: 100mm; background: white; border: 3px double #B03A2E; border-radius: 10px; padding: 15px; }
        h3 { color: #B03A2E; text-align: center; margin-bottom: 2px; }
        .sub { text-align: center; color: #666; font-size: 11px; margin-bottom: 12px; }
        table { width: 100%; font-size: 12px; border-collapse: collapse; margin-top: 10px; }
        td { padding: 6px 8px; border-bottom: 1px dashed #ccc; }
        td:first-child { color: #B03A2E; font-weight: bold; width: 40%; }
        .sig { display: flex; justify-content: space-between; margin-top: 25px; font-size: 11px; color: #444; }
        .sig div { text-align: center; width: 45%; border-top: 1px solid #999; padding-top: 5px; }
        .print-btn { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #2C3E50; color: white; border: none; padding: 12px 40px; border-radius: 25px; font-size: 16px; cursor: pointer; }
        @media print { body { background: white; } .sheet { border-color: #000; } .print-btn { display: none; } }
        @page { size: A6; margin: 0; }
    </style>
</head>
<body>
    <div class="sheet">
        <h3>🚑 نقل دم إنقاذي</h3>
        <div class="sub">Smart Blood Rescue Transfer Sheet</div>
        <table>
            <tr><td>رقم العملية</td><td>#<?php echo $t['transfer_id']; ?></td></tr>
            <tr><td>رمز التسليم</td><td><strong><?php echo htmlspecialchars($t['qr_token']); ?></strong></td></tr>
            <tr><td>كود الكيس</td><td><?php echo htmlspecialchars($t['qr_code']); ?></td></tr>
            <tr><td>فصيلة الدم</td><td><strong><?php echo $t['blood_type']; ?></strong> (<?php echo $t['volume_ml']; ?> مل)</td></tr>
            <tr><td>تاريخ الانتهاء</td><td><?php echo date('Y-m-d', strtotime($t['expiry_date'])); ?></td></tr>
            <tr><td>الجهة المستفيدة</td><td><?php echo htmlspecialchars($t['facility_name']); ?> — <?php echo htmlspecialchars($t['city']); ?></td></tr>
            <tr><td>هاتف الجهة</td><td><?php echo htmlspecialchars($t['facility_phone'] ?? '-'); ?></td></tr>
            <tr><td>درجة الأولوية</td><td><?php echo $t['priority_score']; ?>%</td></tr>
        </table>
        <div class="sig">
            <div>توقيع المُسلِّم</div>
            <div>توقيع المستلِم</div>
        </div>
    </div>
    <button class="print-btn" onclick="window.print()">🖨️ طباعة</button>
</body>
</html>