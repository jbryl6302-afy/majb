<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn()) redirect('../../index.php');

$pageTitle = 'مسح وتتبع رمز QR';
$bag = null; 
$tracking = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    $qrData = trim($_POST['qr_data']);

    // محاولة فك JSON إذا كان الـ QR يحتوي على JSON
    $decoded = json_decode($qrData, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['qr_code'])) {
        $qr_code = $decoded['qr_code'];
    } elseif (json_last_error() === JSON_ERROR_NONE && isset($decoded['bag_id'])) {
        $qr_code = $decoded['bag_id'];
    } else {
        $qr_code = $qrData;
    }

    // البحث بالكود أو بمعرف الكيس
    $stmt = $pdo->prepare("
        SELECT b.*, d.full_name as donor_name, d.blood_type as donor_blood, 
               d.age, d.weight, d.bag_number, s.unit_name, s.location 
        FROM BloodBags b 
        LEFT JOIN Donors d ON b.donor_id = d.donor_id 
        LEFT JOIN StorageUnits s ON b.storage_unit_id = s.unit_id 
        WHERE b.qr_code = ? OR b.bag_id = ?
    ");
    $stmt->execute([$qr_code, is_numeric($qr_code) ? intval($qr_code) : 0]);
    $bag = $stmt->fetch();

    if ($bag) {
        $trackStmt = $pdo->prepare("
            SELECT t.*, u.full_name as performer 
            FROM TrackingLog t 
            LEFT JOIN Users u ON t.performed_by = u.user_id 
            WHERE t.bag_id = ? 
            ORDER BY t.log_time DESC
        ");
        $trackStmt->execute([$bag['bag_id']]); 
        $tracking = $trackStmt->fetchAll();
        
        // تسجيل عملية المسح
        logTracking($pdo, $bag['bag_id'], 'تم مسح رمز QR وفحص البيانات', 'محطة مسح QR', $_SESSION['user_id']);
    } else {
        $error = '❌ لم يتم العثور على كيس دم مسجل بهذا الرمز: ' . htmlspecialchars($qr_code);
    }
}

require_once '../../includes/header.php';
?>

<div class="d-flex">
    <!-- السايدبار -->
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom border-secondary">
            <h5 class="fw-bold mb-1"><i class="fas fa-tint text-danger me-2"></i> بنك الدم الذكي</h5>
            <span class="badge bg-danger mt-1"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
        </div>
        <div class="py-2">
            <a href="../../dashboard.php"><i class="fas fa-chart-line"></i> لوحة التحكم</a>
            <div class="section-title">المتبرعون والأكياس</div>
            <a href="../donors/list.php"><i class="fas fa-users"></i> قائمة المتبرعين</a>
            <a href="../donors/screening.php"><i class="fas fa-microscope"></i> الفحص المبدئي</a>
            <?php if (hasRole(['LabTechnician', 'Admin'])): ?>
            <a href="../donors/add.php"><i class="fas fa-hand-holding-medical"></i> تسجيل تبرع جديد</a>
            <?php endif; ?>
            <a href="list.php"><i class="fas fa-syringe"></i> مخزون أكياس الدم</a>
            <a href="scan.php" class="active"><i class="fas fa-qrcode"></i> مسح وتتبع QR</a>
            <div class="section-title">المختبر والطلبات</div>
            <a href="../tests/enter.php"><i class="fas fa-vial"></i> الفحوصات المخبرية</a>
            <a href="../requests/list.php"><i class="fas fa-file-medical"></i> طلبات الدم</a>
            <div class="border-top border-secondary mt-3 pt-2">
                <a href="../../logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
        </div>
    </div>

    <!-- المحتوى الرئيسي -->
    <div class="main-content w-100">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="fas fa-qrcode text-primary me-2"></i> مسح وتتبع كيس الدم عبر QR Code</h3>
                <p class="text-muted mb-0">مسح كود الـ QR بالكاميرا أو الإدخال اليدوي لعرض رحلة الكيس وتفاصيله الكاملة</p>
            </div>
            <a href="list.php" class="btn btn-outline-secondary"><i class="fas fa-syringe me-1"></i> قائمة الأكياس</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4">
                <i class="fas fa-times-circle fs-4 me-2"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <?php if (!$bag): ?>
        <!-- شاشة المسح والبحث -->
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white py-3">
                        <h5 class="fw-bold mb-0 text-center"><i class="fas fa-camera me-2"></i> الماسح الضوئي والإدخال</h5>
                    </div>
                    <div class="card-body p-4 text-center">
                        <div id="reader" style="width: 100%; border-radius: 12px; overflow: hidden; background: #000; min-height: 250px;"></div>
                        
                        <div class="my-3 text-muted">
                            <span class="badge bg-secondary px-3 py-2">أو أدخل كود الـ QR يدوياً</span>
                        </div>

                        <form method="POST" id="scanForm">
                            <input type="hidden" name="qr_data" id="qrData">
                            <div class="input-group input-group-lg mb-2">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                <input type="text" id="manualQr" class="form-control fs-6" 
                                       placeholder="أدخل كود الكيس (مثال: BAG-2026-XXXX-XXXX)"
                                       onkeypress="if(event.key==='Enter'){event.preventDefault();submitManual();}">
                                <button type="button" class="btn btn-primary" onclick="submitManual()">
                                    <i class="fas fa-search me-1"></i> فحص
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
        <script>
        try {
            const html5QrCode = new Html5Qrcode("reader");
            html5QrCode.start(
                { facingMode: "environment" }, 
                { fps: 10, qrbox: { width: 220, height: 220 } }, 
                (decodedText) => {
                    document.getElementById('qrData').value = decodedText;
                    document.getElementById('scanForm').submit();
                    html5QrCode.stop();
                }
            ).catch(err => {
                document.getElementById('reader').innerHTML = '<div class="p-4 text-white"><i class="fas fa-video-slash fa-2x mb-2"></i><br>الكاميرا غير متوفرة أو لم يتم منح الإذن. يمكنك استخدام الإدخال اليدوي أدناه.</div>';
            });
        } catch(e) {
            console.log(e);
        }

        function submitManual() {
            const val = document.getElementById('manualQr').value.trim();
            if (val) {
                document.getElementById('qrData').value = val;
                document.getElementById('scanForm').submit();
            } else {
                alert('⚠️ يرجى كتابة كود الـ QR أو رقم الكيس أولاً');
            }
        }
        </script>

        <?php else: ?>
        <!-- نتائج المسح -->
        <div class="row g-4">
            <!-- تفاصيل الكيس -->
            <div class="col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="fas fa-syringe me-2"></i> تفاصيل الكيس #<?php echo $bag['bag_id']; ?></h5>
                        <span class="badge bg-white text-dark"><?php echo statusBadge($bag['blood_type']); ?></span>
                    </div>
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <img src="<?php echo getQrCodeUrl($bag['qr_code'], 180); ?>" alt="QR Code" class="img-fluid border rounded p-2 bg-white" style="max-width: 170px;">
                            <div class="mt-2">
                                <code class="fs-6 fw-bold"><?php echo htmlspecialchars($bag['qr_code']); ?></code>
                            </div>
                        </div>

                        <table class="table table-borderless text-start align-middle mb-4">
                            <tr><td class="text-muted">فصيلة الدم:</td><td><?php echo statusBadge($bag['blood_type']); ?></td></tr>
                            <tr><td class="text-muted">الكمية:</td><td><strong><?php echo $bag['volume_ml']; ?> مل</strong></td></tr>
                            <tr><td class="text-muted">المتبرع:</td><td><strong><?php echo htmlspecialchars($bag['donor_name'] ?? 'غير متوفر'); ?></strong></td></tr>
                            <tr><td class="text-muted">تاريخ التبرع:</td><td><?php echo formatDate($bag['donation_date']); ?></td></tr>
                            <tr><td class="text-muted">تاريخ الانتهاء:</td><td><?php echo formatDate($bag['expiry_date']); ?></td></tr>
                            <tr><td class="text-muted">الحالة الحالية:</td><td><?php echo statusBadge($bag['status']); ?></td></tr>
                            <tr><td class="text-muted">وحدة التخزين:</td><td><?php echo htmlspecialchars($bag['unit_name'] ?? 'المخزن الرئيسي'); ?></td></tr>
                        </table>

                        <div class="d-flex gap-2">
                            <a href="print_sticker.php?bag_id=<?php echo $bag['bag_id']; ?>" target="_blank" class="btn btn-outline-primary flex-grow-1">
                                <i class="fas fa-print me-1"></i> طباعة الملصق
                            </a>
                            <a href="scan.php" class="btn btn-secondary">
                                <i class="fas fa-redo me-1"></i> مسح آخر
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- سجل التتبع الكامل (Timeline) -->
            <div class="col-lg-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-dark text-white py-3">
                        <h5 class="fw-bold mb-0"><i class="fas fa-route me-2"></i> سجل التتبع وخط السير الزمني للكيس</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php foreach ($tracking as $i => $log): ?>
                        <div class="d-flex mb-3 pb-3 border-bottom">
                            <div class="flex-shrink-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 42px; height: 42px; font-weight: bold;">
                                    <?php echo (count($tracking) - $i); ?>
                                </div>
                            </div>
                            <div class="flex-grow-1 me-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($log['action']); ?></h6>
                                    <small class="text-muted"><?php echo formatDate($log['log_time']); ?></small>
                                </div>
                                <p class="mb-1 text-muted small"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($log['location']); ?></p>
                                <small class="text-secondary"><i class="fas fa-user me-1"></i> بواسطة: <?php echo htmlspecialchars($log['performer'] ?? 'النظام الآلي'); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($tracking)): ?>
                        <p class="text-muted text-center py-4">لا يوجد سجل تتبع مسجل لهذا الكيس.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>