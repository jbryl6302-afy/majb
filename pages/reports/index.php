<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn()) redirect('../../index.php');

$pageTitle = 'التقارير';
$totalDonors = $pdo->query("SELECT COUNT(*) FROM Donors")->fetchColumn();
$totalBags = $pdo->query("SELECT COUNT(*) FROM BloodBags")->fetchColumn();
$totalRequests = $pdo->query("SELECT COUNT(*) FROM BloodRequests")->fetchColumn();
$fulfilledRequests = $pdo->query("SELECT COUNT(*) FROM BloodRequests WHERE status='Fulfilled'")->fetchColumn();

$monthlyStats = $pdo->query("SELECT DATE_FORMAT(donation_date, '%Y-%m') as month, COUNT(*) as bags, SUM(volume_ml) as total_ml FROM BloodBags GROUP BY month ORDER BY month DESC LIMIT 6")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom"><h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5></div>
        <a href="../../dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="index.php" class="active"><i class="fas fa-file-alt"></i> التقارير</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <h3 class="mb-4"><i class="fas fa-file-alt text-primary"></i> تقارير النظام</h3>

        <div class="row mb-4">
            <div class="col-md-3"><div class="card stat-card bg-primary text-white p-3 text-center"><h4><?php echo $totalDonors; ?></h4><small>إجمالي المتبرعين</small></div></div>
            <div class="col-md-3"><div class="card stat-card bg-success text-white p-3 text-center"><h4><?php echo $totalBags; ?></h4><small>إجمالي الأكياس</small></div></div>
            <div class="col-md-3"><div class="card stat-card bg-info text-white p-3 text-center"><h4><?php echo $totalRequests; ?></h4><small>إجمالي الطلبات</small></div></div>
            <div class="col-md-3"><div class="card stat-card bg-warning text-dark p-3 text-center"><h4><?php echo $fulfilledRequests; ?></h4><small>تم تنفيذها</small></div></div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white"><i class="fas fa-calendar-alt"></i> ملخص التبرعات الشهرية</div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead><tr><th>الشهر</th><th>عدد الأكياس</th><th>إجمالي الحجم (مل)</th></tr></thead>
                    <tbody>
                        <?php foreach ($monthlyStats as $m): ?>
                        <tr>
                            <td><?php echo $m['month']; ?></td>
                            <td><?php echo $m['bags']; ?></td>
                            <td><?php echo $m['total_ml']; ?> مل</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
