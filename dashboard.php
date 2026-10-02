<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
if (!isLoggedIn()) redirect('index.php');

$pageTitle = 'لوحة التحكم';

// ─── إحصائيات الأكياس ───
$stats = [
    'total_bags'       => $pdo->query("SELECT COUNT(*) FROM BloodBags")->fetchColumn(),
    'available'        => $pdo->query("SELECT COUNT(*) FROM BloodBags WHERE status='Available'")->fetchColumn(),
    'discarded'        => $pdo->query("SELECT COUNT(*) FROM BloodBags WHERE status='Discarded'")->fetchColumn(),
    'expired'          => $pdo->query("SELECT COUNT(*) FROM BloodBags WHERE status='Expired'")->fetchColumn(),
    'pending_requests' => $pdo->query("SELECT COUNT(*) FROM BloodRequests WHERE status='Pending'")->fetchColumn(),
    'wasted_risk'        => $pdo->query("SELECT COUNT(*) FROM BloodBags WHERE status='Available' AND expiry_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
];

$bloodTypes = $pdo->query("SELECT blood_type, COUNT(*) as count FROM BloodBags WHERE status='Available' GROUP BY blood_type ORDER BY blood_type")->fetchAll();
$recentTracking = $pdo->query("SELECT t.*, b.qr_code, u.full_name as performer FROM TrackingLog t LEFT JOIN BloodBags b ON t.bag_id = b.bag_id LEFT JOIN Users u ON t.performed_by = u.user_id ORDER BY t.log_time DESC LIMIT 5")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom">
            <h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5>
            <small class="text-muted"><?php echo $_SESSION['role']; ?></small>
        </div>
        <a href="dashboard.php" class="active"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="pages/donors/list.php"><i class="fas fa-user-plus"></i> المتبرعون</a>
        <a href="pages/bags/list.php"><i class="fas fa-syringe"></i> أكياس الدم</a>
        <a href="pages/bags/scan.php"><i class="fas fa-qrcode"></i> مسح QR</a>
        <a href="pages/requests/list.php"><i class="fas fa-hand-holding-medical"></i> طلبات الدم</a>
        <a href="pages/tests/enter.php"><i class="fas fa-vial"></i> الفحوصات المخبرية</a>
        <a href="pages/predictions/index.php"><i class="fas fa-brain"></i> التنبؤات الذكية</a>
        <a href="pages/rescue/index.php"><i class="fas fa-truck-medical"></i> الإنقاذ الذكي لفائض الدم</a>
        <a href="pages/reports/index.php"><i class="fas fa-file-alt"></i> التقارير</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <h3 class="mb-4">مرحباً، <?php echo htmlspecialchars($_SESSION['full_name']); ?> 
            <span class="badge bg-primary"><?php echo $_SESSION['role']; ?></span>
        </h3>

        <!-- ─── كروت الإحصائيات ─── -->
        <div class="row mb-4">
            <!-- إجمالي الأكياس -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6>إجمالي الأكياس</h6>
                            <h2><?php echo $stats['total_bags']; ?></h2>
                        </div>
                        <i class="fas fa-syringe stat-icon"></i>
                    </div>
                </div>
            </div>
            <!-- المتاحة -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6>متاحة</h6>
                            <h2><?php echo $stats['available']; ?></h2>
                        </div>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                </div>
            </div>
            <!-- 🆕 التالفة (فحوصات إيجابية) -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6>تالفة</h6>
                            <h2><?php echo $stats['discarded']; ?></h2>
                        </div>
                        <i class="fas fa-biohazard stat-icon"></i>
                    </div>
                </div>
            </div>
            <!-- منتهية الصلاحية -->
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6>منتهية الصلاحية</h6>
                            <h2><?php echo $stats['expired']; ?></h2>
                        </div>
                        <i class="fas fa-exclamation-triangle stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- صف ثاني: طلبات معلقة -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-secondary text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6>مهددة بالهدر (7 أيام)</h6>
                            <h2><?php echo $stats['wasted_risk']; ?></h2>
                        </div>
                        <i class="fas fa-truck-medical stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-warning text-dark">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6>طلبات معلقة</h6>
                            <h2><?php echo $stats['pending_requests']; ?></h2>
                        </div>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-chart-pie"></i> توزيع فصائل الدم
                    </div>
                    <div class="card-body">
                        <canvas id="bloodChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-bell"></i> التنبيهات الذكية
                    </div>
                    <div class="card-body" id="alerts-container">
                        <div class="text-center text-muted">جاري تحميل التنبيهات...</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-history"></i> آخر النشاطات
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead><tr><th>الوقت</th><th>الإجراء</th><th>الكيس</th><th>الموقع</th><th>بواسطة</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentTracking as $log): ?>
                        <tr>
                            <td><?php echo formatDate($log['log_time']); ?></td>
                            <td><span class="badge bg-info"><?php echo $log['action']; ?></span></td>
                            <td><?php echo $log['qr_code'] ?? 'غير متوفر'; ?></td>
                            <td><?php echo $log['location']; ?></td>
                            <td><?php echo $log['performer'] ?? 'النظام'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('bloodChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($bloodTypes, 'blood_type')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($bloodTypes, 'count')); ?>,
            backgroundColor: ['#B03A2E', '#301805', '#C08A3E', '#6B8E5A', '#A9784D', '#ac9380', '#D8A47F', '#4A2C17']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

fetch('api/alerts.php')
    .then(r => r.json())
    .then(data => {
        const container = document.getElementById('alerts-container');
        if (data.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">لا توجد تنبيهات حالياً</p>';
            return;
        }
        container.innerHTML = data.map(a => `
            <div class="alert alert-${a.type} alert-sm py-2 mb-2">
                <small><strong><i class="fas fa-exclamation-circle"></i> ${a.title}</strong><br>${a.message}</small>
            </div>
        `).join('');
    })
    .catch(() => {
        document.getElementById('alerts-container').innerHTML = '<p class="text-muted text-center">تعذر تحميل التنبيهات</p>';
    });
</script>

<?php require_once 'includes/footer.php'; ?>