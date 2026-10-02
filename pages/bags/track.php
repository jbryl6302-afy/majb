<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn()) redirect('../../index.php');

$pageTitle = 'تتبع كيس الدم';
$bag_id = $_GET['bag_id'] ?? 0;

$stmt = $pdo->prepare("SELECT b.*, d.full_name as donor_name, s.unit_name, s.location FROM BloodBags b LEFT JOIN Donors d ON b.donor_id = d.donor_id LEFT JOIN StorageUnits s ON b.storage_unit_id = s.unit_id WHERE b.bag_id = ?");
$stmt->execute([$bag_id]);
$bag = $stmt->fetch();

$tracking = [];
if ($bag) {
    $tstmt = $pdo->prepare("SELECT t.*, u.full_name as performer FROM TrackingLog t LEFT JOIN Users u ON t.performed_by = u.user_id WHERE t.bag_id = ? ORDER BY t.log_time DESC");
    $tstmt->execute([$bag_id]); $tracking = $tstmt->fetchAll();
}

require_once '../../includes/header.php';
?>

<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom"><h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5></div>
        <a href="../../dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="list.php"><i class="fas fa-syringe"></i> أكياس الدم</a>
        <a href="scan.php"><i class="fas fa-qrcode"></i> مسح QR</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <h3 class="mb-4"><i class="fas fa-route text-info"></i> تتبع رحلة كيس الدم</h3>

        <?php if (!$bag): ?><div class="alert alert-danger">الكيس غير موجود.</div>
        <?php else: ?>
        <div class="row">
            <div class="col-md-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">تفاصيل الكيس</div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr><td><strong>رقم الكيس:</strong></td><td>#<?php echo $bag['bag_id']; ?></td></tr>
                            <tr><td><strong>QR Code:</strong></td><td><code><?php echo $bag['qr_code']; ?></code></td></tr>
                            <tr><td><strong>فصيلة الدم:</strong></td><td><?php echo statusBadge($bag['blood_type']); ?></td></tr>
                            <tr><td><strong>الكمية:</strong></td><td><?php echo $bag['volume_ml']; ?> مل</td></tr>
                            <tr><td><strong>المتبرع:</strong></td><td><?php echo $bag['donor_name'] ?? 'غير متوفر'; ?></td></tr>
                            <tr><td><strong>الحالة:</strong></td><td><?php echo statusBadge($bag['status']); ?></td></tr>
                            <tr><td><strong>التخزين:</strong></td><td><?php echo $bag['unit_name'] ?? 'غير متوفر'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white"><i class="fas fa-history"></i> الرحلة الكاملة</div>
                    <div class="card-body">
                       <?php foreach ($tracking as $i => $log): ?>
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><?php echo count($tracking) - $i; ?></div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1"><?php echo $log['action']; ?></h6>
                                <p class="mb-0 text-muted"><?php echo $log['location']; ?></p>
                                <small class="text-muted"><?php echo formatDate($log['log_time']); ?> | بواسطة: <?php echo $log['performer'] ?? 'النظام'; ?></small>
                            </div>
                        </div>
                        <?php if ($i < count($tracking) - 1): ?><hr class="my-2"><?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($tracking)): ?><p class="text-muted">لا يوجد سجل.</p><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
