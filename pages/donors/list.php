<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn()) redirect('../../index.php');

$pageTitle = 'قائمة المتبرعين';
$donors = $pdo->query("SELECT * FROM Donors ORDER BY created_at DESC")->fetchAll();

// إحصائيات
$totalDonors = count($donors);
$eligibleCount = count(array_filter($donors, fn($d) => !empty($d['is_eligible'])));
$rejectedCount = $totalDonors - $eligibleCount;

require_once '../../includes/header.php';
?>

<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom"><h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5></div>
        <a href="../../dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="list.php" class="active"><i class="fas fa-user-plus"></i> المتبرعون</a>
        <a href="../bags/list.php"><i class="fas fa-syringe"></i> أكياس الدم</a>
        <a href="../bags/scan.php"><i class="fas fa-qrcode"></i> مسح QR</a>
        <a href="../requests/list.php"><i class="fas fa-hand-holding-medical"></i> طلبات الدم</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-users text-primary"></i> المتبرعون</h3>
            <?php if (hasRole('LabTechnician') || hasRole('Admin')): ?>
            <a href="add.php" class="btn btn-danger"><i class="fas fa-plus"></i> تبرع جديد</a>
            <?php endif; ?>
        </div>

        <!-- إحصائيات -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="text-primary mb-0"><?php echo $totalDonors; ?></h2>
                        <small class="text-muted">إجمالي المتبرعين</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="text-success mb-0"><?php echo $eligibleCount; ?></h2>
                        <small class="text-muted">المؤهلون للتبرع</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="text-danger mb-0"><?php echo $rejectedCount; ?></h2>
                        <small class="text-muted">غير المؤهلين</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>رقم الكيس</th>
                            <th>العمر</th>
                            <th>الوزن</th>
                            <th>الفصيلة</th>
                            <th>الهاتف</th>
                            <th>آخر تبرع</th>
                            <th>الحالة الصحية</th>
                            <th>التدخين</th>
                            <th>الحالة</th>
                            <th>تاريخ التسجيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donors as $d): ?>
                        <tr>
                            <td><?php echo $d['donor_id']; ?></td>
                            <td><?php echo htmlspecialchars($d['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($d['bag_number'] ?? '-'); ?></td>
                            <td><?php echo !empty($d['age']) ? $d['age'] . ' سنة' : '-'; ?></td>
                            <td><?php echo !empty($d['weight']) ? $d['weight'] . ' كجم' : '-'; ?></td>
                            <td><?php echo statusBadge($d['blood_type']); ?></td>
                            <td><?php echo $d['phone'] ?? '-'; ?></td>
                            <td><?php echo $d['last_donation'] ?? 'غير متوفر'; ?></td>
                            <td><?php echo $d['health_status'] ?? 'جيد'; ?></td>
                            <td><?php echo ($d['smoker'] ?? 'No') === 'Yes' ? '<span class="badge bg-secondary">🚬 مدخن</span>' : '<span class="badge bg-light text-dark">لا</span>'; ?></td>
                            <td>
                                <?php if (!empty($d['is_eligible'])): ?>
                                    <span class="badge bg-success">✅ مؤهل</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">❌ غير مؤهل</span>
                                    <?php if (!empty($d['rejection_reason'])): ?>
                                        <i class="fas fa-info-circle text-danger ms-1" 
                                           title="<?php echo htmlspecialchars($d['rejection_reason']); ?>"
                                           style="cursor: pointer;"></i>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($d['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>