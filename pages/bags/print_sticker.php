<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn()) redirect('../../index.php');

$bag_id = intval($_GET['bag_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT b.*, d.full_name as donor_name, d.blood_type as donor_blood, d.bag_number, s.unit_name, s.location
    FROM BloodBags b 
    LEFT JOIN Donors d ON b.donor_id = d.donor_id 
    LEFT JOIN StorageUnits s ON b.storage_unit_id = s.unit_id
    WHERE b.bag_id = ?
");
$stmt->execute([$bag_id]);
$bag = $stmt->fetch();

if (!$bag) {
    die('❌ عذراً، كيس الدم غير موجود في النظام.');
}

$qrUrl = getQrCodeUrl($bag['qr_code'], 220);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>ملصق كيس الدم #<?php echo $bag['bag_id']; ?> - <?php echo $bag['blood_type']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Cairo", sans-serif;
            background: #e2e8f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .sticker {
            width: 85mm;
            background: white;
            border: 2px solid #b91c1c;
            border-radius: 10px;
            padding: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .sticker-header {
            text-align: center;
            border-bottom: 2px dashed #b91c1c;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .sticker-header h3 {
            color: #b91c1c;
            font-size: 16px;
            font-weight: 800;
        }
        .sticker-header small {
            color: #64748b;
            font-size: 10px;
        }
        .qr-section {
            text-align: center;
            padding: 4px 0;
        }
        .qr-section img {
            width: 130px;
            height: 130px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 3px;
        }
        .qr-code-text {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 3px;
            letter-spacing: 0.5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-top: 8px;
            font-size: 11px;
        }
        .info-item {
            background: #f8fafc;
            padding: 4px 8px;
            border-radius: 4px;
            border-right: 3px solid #b91c1c;
        }
        .info-item.full {
            grid-column: 1 / -1;
        }
        .info-item strong {
            color: #b91c1c;
            display: block;
            font-size: 9px;
        }
        .blood-type-badge {
            display: inline-block;
            background: #b91c1c;
            color: white;
            padding: 1px 8px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 13px;
        }
        .expiry-box {
            text-align: center;
            margin-top: 8px;
            padding: 4px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            color: #991b1b;
        }
        .print-btn {
            position: fixed;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            background: #ef4444;
            color: white;
            border: none;
            padding: 12px 35px;
            border-radius: 25px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(239,68,68,0.4);
            z-index: 1000;
            font-family: "Cairo", sans-serif;
            transition: 0.2s;
        }
        .print-btn:hover { background: #dc2626; transform: translateX(-50%) scale(1.03); }

        @media print {
            body { background: white; padding: 0; }
            .sticker { 
                box-shadow: none; 
                border: 2px solid #000;
                page-break-after: always;
            }
            .print-btn { display: none !important; }
            .sticker-header h3 { color: #000; }
            .info-item { border-right-color: #000; background: #fff; }
            .blood-type-badge { background: #000; }
            .expiry-box { border-color: #000; color: #000; background: #fff; }
        }

        @page {
            size: 85mm 120mm;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="sticker">
        <div class="sticker-header">
            <h3><i class="fas fa-tint"></i> بنك الدم الذكي</h3>
            <small>ملصق كيس دم معتمد | Smart Blood Bank</small>
        </div>

        <div class="qr-section">
            <img src="<?php echo $qrUrl; ?>" alt="QR Code">
            <div class="qr-code-text"><?php echo htmlspecialchars($bag['qr_code']); ?></div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <strong>رقم الكيس</strong>
                #<?php echo $bag['bag_id']; ?>
            </div>
            <div class="info-item">
                <strong>فصيلة الدم</strong>
                <span class="blood-type-badge"><?php echo $bag['blood_type']; ?></span>
            </div>
            <div class="info-item">
                <strong>الكمية</strong>
                <?php echo $bag['volume_ml']; ?> مل
            </div>
            <div class="info-item">
                <strong>تاريخ السحب</strong>
                <?php echo date('Y-m-d', strtotime($bag['donation_date'])); ?>
            </div>
            <div class="info-item full">
                <strong>المتبرع</strong>
                <?php echo htmlspecialchars($bag['donor_name'] ?? 'غير متوفر'); ?>
                <?php if (!empty($bag['bag_number'])): ?>
                (رقم: <?php echo htmlspecialchars($bag['bag_number']); ?>)
                <?php endif; ?>
            </div>
            <div class="info-item full">
                <strong>وحدة التخزين</strong>
                <?php echo htmlspecialchars($bag['unit_name'] ?? 'المخزن الرئيسي'); ?> 
                (<?php echo htmlspecialchars($bag['location'] ?? '-'); ?>)
            </div>
        </div>

        <div class="expiry-box">
            <i class="fas fa-exclamation-triangle me-1"></i>
            تاريخ الانتهاء: <?php echo date('Y-m-d', strtotime($bag['expiry_date'])); ?>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print me-1"></i> طباعة الملصق الآن
    </button>
</body>
</html>