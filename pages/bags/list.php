<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn()) redirect('../../index.php');

$pageTitle = 'مخزون أكياس الدم';

// ─── فلترة حسب الحالة ───
$statusFilter = $_GET['status'] ?? 'all';
$params = [];

if ($statusFilter === 'all') {
    $sql = "SELECT b.*, d.full_name as donor_name, d.bag_number, s.unit_name, s.location 
            FROM BloodBags b 
            LEFT JOIN Donors d ON b.donor_id = d.donor_id 
            LEFT JOIN StorageUnits s ON b.storage_unit_id = s.unit_id 
            ORDER BY b.bag_id DESC";
} else {
    $sql = "SELECT b.*, d.full_name as donor_name, d.bag_number, s.unit_name, s.location 
            FROM BloodBags b 
            LEFT JOIN Donors d ON b.donor_id = d.donor_id 
            LEFT JOIN StorageUnits s ON b.storage_unit_id = s.unit_id 
            WHERE b.status = ? 
            ORDER BY b.bag_id DESC";
    $params = [$statusFilter];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bags = $stmt->fetchAll();

// إحصائيات الأكياس
$stats = [
    'total'     => $pdo->query("SELECT COUNT(*) FROM BloodBags")->fetchColumn(),
    'available' => $pdo->query("SELECT COUNT(*) FROM BloodBags WHERE status='Available'")->fetchColumn(),
    'reserved'  => $pdo->query("SELECT COUNT(*) FROM BloodBags WHERE status='Reserved'")->fetchColumn(),
    'used'      => $pdo->query("SELECT COUNT(*) FROM BloodBags WHERE status='Used'")->fetchColumn(),
    'discarded' => $pdo->query("SELECT COUNT(*) FROM BloodBags WHERE status='Discarded'")->fetchColumn(),
    'expired'   => $pdo->query("SELECT COUNT(*) FROM BloodBags WHERE status='Expired'")->fetchColumn(),
];

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
            <a href="list.php" class="active"><i class="fas fa-syringe"></i> مخزون أكياس الدم</a>
            <a href="scan.php"><i class="fas fa-qrcode"></i> مسح وتتبع QR</a>
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
                <h3 class="fw-bold mb-1"><i class="fas fa-syringe text-primary me-2"></i> مخزون أكياس الدم وتتبع الـ QR</h3>
                <p class="text-muted mb-0">إدارة ومراقبة جميع أكياس الدم وحالتها ووحدات التخزين وصلاحيتها</p>
            </div>
            <div class="d-flex gap-2">
                <a href="scan.php" class="btn btn-dark"><i class="fas fa-qrcode me-1"></i> مسح QR</a>
                <?php if (hasRole(['LabTechnician', 'Admin'])): ?>
                <a href="../donors/add.php" class="btn btn-danger"><i class="fas fa-plus me-1"></i> تبرع جديد</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- كروت الإحصائيات -->
        <div class="row g-2 mb-4">
            <div class="col-md-2 col-6">
                <div class="card stat-card bg-primary text-white text-center p-2">
                    <small class="text-white-50">إجمالي الأكياس</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['total']; ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card stat-card bg-success text-white text-center p-2">
                    <small class="text-white-50">متاحة للصرف</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['available']; ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card stat-card bg-info text-dark text-center p-2">
                    <small>محجوزة لطلبات</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['reserved']; ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card stat-card bg-secondary text-white text-center p-2">
                    <small class="text-white-50">تم استخدامها</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['used']; ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card stat-card bg-danger text-white text-center p-2">
                    <small class="text-white-50">منتهية الصلاحية</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['expired']; ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card stat-card bg-dark text-white text-center p-2">
                    <small class="text-white-50">تالفة / مستبعدة</small>
                    <h4 class="fw-bold mb-0"><?php echo $stats['discarded']; ?></h4>
                </div>
            </div>
        </div>

        <!-- أزرار الفلترة -->
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="?status=all" class="btn btn-sm <?php echo $statusFilter==='all' ? 'btn-dark' : 'btn-outline-dark'; ?>">الكل (<?php echo $stats['total']; ?>)</a>
            <a href="?status=Available" class="btn btn-sm <?php echo $statusFilter==='Available' ? 'btn-success' : 'btn-outline-success'; ?>">متاحة (<?php echo $stats['available']; ?>)</a>
            <a href="?status=Reserved" class="btn btn-sm <?php echo $statusFilter==='Reserved' ? 'btn-info' : 'btn-outline-info'; ?>">محجوزة (<?php echo $stats['reserved']; ?>)</a>
            <a href="?status=Used" class="btn btn-sm <?php echo $statusFilter==='Used' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">مستخدمة (<?php echo $stats['used']; ?>)</a>
            <a href="?status=Expired" class="btn btn-sm <?php echo $statusFilter==='Expired' ? 'btn-danger' : 'btn-outline-danger'; ?>">منتهية (<?php echo $stats['expired']; ?>)</a>
            <a href="?status=Discarded" class="btn btn-sm <?php echo $statusFilter==='Discarded' ? 'btn-dark' : 'btn-outline-dark'; ?>">تالفة (<?php echo $stats['discarded']; ?>)</a>
        </div>

        <!-- جدول الأكياس -->
        <div class="card shadow-sm">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>QR</th>
                            <th>#</th>
                            <th>كود الكيس الفريد</th>
                            <th>الفصيلة</th>
                            <th>الحجم</th>
                            <th>المتبرع</th>
                            <th>الحالة</th>
                            <th>وحدة التخزين</th>
                            <th>تاريخ التبرع</th>
                            <th>تاريخ الانتهاء</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bags as $bag): 
                            $isExpiringSoon = (strtotime($bag['expiry_date']) - time()) < (7 * 86400) && $bag['status'] === 'Available';
                        ?>
                        <tr class="<?php echo $isExpiringSoon ? 'table-warning' : ''; ?>">
                            <td>
                                <img src="<?php echo getQrCodeUrl($bag['qr_code'], 60); ?>" 
                                     alt="QR" class="rounded border p-1 bg-white" style="width: 46px; height: 46px; cursor: pointer;"
                                     onclick="window.open('print_sticker.php?bag_id=<?php echo $bag['bag_id']; ?>', '_blank')"
                                     title="انقر لطباعة ستيكر الكيس">
                            </td>
                            <td>#<?php echo $bag['bag_id']; ?></td>
                            <td><code><?php echo htmlspecialchars($bag['qr_code']); ?></code></td>
                            <td><?php echo statusBadge($bag['blood_type']); ?></td>
                            <td><?php echo $bag['volume_ml']; ?> مل</td>
                            <td><?php echo htmlspecialchars($bag['donor_name'] ?? 'غير متوفر'); ?></td>
                            <td><?php echo statusBadge($bag['status']); ?></td>
                            <td><small><i class="fas fa-archive text-muted me-1"></i><?php echo htmlspecialchars($bag['unit_name'] ?? 'غير محدد'); ?></small></td>
                            <td class="small text-muted"><?php echo date('Y-m-d', strtotime($bag['donation_date'])); ?></td>
                            <td class="small <?php echo $isExpiringSoon ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                <?php echo date('Y-m-d', strtotime($bag['expiry_date'])); ?>
                                <?php if ($isExpiringSoon): ?>
                                    <span class="badge bg-warning text-dark d-block">وشك الانتهاء</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="track.php?bag_id=<?php echo $bag['bag_id']; ?>" class="btn btn-outline-info" title="تتبع خط السير">
                                        <i class="fas fa-route"></i>
                                    </a>
                                    <a href="print_sticker.php?bag_id=<?php echo $bag['bag_id']; ?>" target="_blank" class="btn btn-outline-dark" title="طباعة ملصق">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bags)): ?>
                        <tr><td colspan="11" class="text-center py-4 text-muted">لا توجد أكياس مطابقة للفلتر المحدد.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>