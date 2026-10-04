<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn()) redirect('../../index.php');

$pageTitle = 'طلبات الدم';
$requests = $pdo->query("SELECT r.*, u.full_name as doctor_name FROM BloodRequests r LEFT JOIN Users u ON r.doctor_id = u.user_id ORDER BY r.request_date DESC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom"><h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5></div>
        <a href="../../dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="list.php" class="active"><i class="fas fa-hand-holding-medical"></i> طلبات الدم</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-hand-holding-medical text-primary"></i> طلبات الدم</h3>
            <?php if (hasRole('Doctor')): ?>
           <a href="add.php" class="btn btn-danger"><i class="fas fa-plus me-1"></i> طلب دم جديد</a>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-hover table-sm">
                    <thead class="table-dark">
                        <tr><th>#</th><th>الطبيب</th><th>المريض</th><th>الفصيلة</th><th>الوحدات</th><th>الأولوية</th><th>الحالة</th><th>التاريخ</th><th>إجراء</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>#<?php echo $req['request_id']; ?></td>
                            <td><?php echo $req['doctor_name'] ?? 'غير متوفر'; ?></td>
                            <td><?php echo htmlspecialchars($req['patient_name'] ?? 'غير متوفر'); ?></td>
                            <td><?php echo statusBadge($req['patient_blood_type']); ?></td>
                            <td><?php echo $req['units_needed']; ?></td>
                            <td><?php echo statusBadge($req['urgency']); ?></td>
                            <td><?php echo statusBadge($req['status']); ?></td>
                            <td><?php echo formatDate($req['request_date']); ?></td>
                            <td>
                                <?php if ($req['status'] === 'Pending' && hasRole('Admin')): ?>
                                <a href="approve.php?request_id=<?php echo $req['request_id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <a href="dashboard.php" class="active"><i class="fas fa-home"></i> الرئيسية</a>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
