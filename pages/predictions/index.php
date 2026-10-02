<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn()) redirect('../../index.php');

$pageTitle = 'التنبؤات الذكية';
$predictions = [];
$aiStatus = 'offline';

try {
    $currentMonth = date('n');
    $currentYear = date('Y');
    $apiUrl = "http://localhost:5000/predict/all/$currentMonth/$currentYear";
    $response = @file_get_contents($apiUrl);
    if ($response) {
        $predictions = json_decode($response, true);
        $aiStatus = 'online';
    }
} catch (Exception $e) {
    $predictions = [];
}

if (!empty($predictions) && $aiStatus === 'online') {
    foreach ($predictions as $p) {
        $pdo->prepare("INSERT INTO Predictions (blood_type, predicted_units, confidence, prediction_for_month) VALUES (?, ?, ?, ?)")
            ->execute([$p['blood_type'], $p['predicted_units'], $p['confidence'] ?? 0.85, date('Y-m-d')]);
    }
}

require_once '../../includes/header.php';
?>

<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom"><h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5></div>
        <a href="../../dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="index.php" class="active"><i class="fas fa-brain"></i> التنبؤات الذكية</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <h3 class="mb-4"><i class="fas fa-brain text-primary"></i> التنبؤ بالطلب على فصائل الدم</h3>

        <div class="alert alert-<?php echo $aiStatus === 'online' ? 'success' : 'warning'; ?>">
            <i class="fas fa-<?php echo $aiStatus === 'online' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            خدمة الذكاء الاصطناعي: <strong><?php echo $aiStatus === 'online' ? 'متصلة' : 'غير متصلة'; ?></strong>
            <?php if ($aiStatus === 'offline'): ?>
                <br><small>شغل الخدمة: <code>cd ai_model && python predict.py</code></small>
            <?php endif; ?>
        </div>

        <?php if (!empty($predictions)): ?>
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-chart-bar"></i> الطلب المتوقع لشهر <?php echo date('F Y'); ?>
                    </div>
                    <div class="card-body">
                        <canvas id="predictionChart" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><i class="fas fa-table"></i> جدول التنبؤات</div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <thead><tr><th>الفصيلة</th><th>الوحدات المتوقعة</th></tr></thead>
                            <tbody>
                                <?php foreach ($predictions as $p): ?>
                                <tr>
                                    <td><?php echo statusBadge($p['blood_type']); ?></td>
                                    <td><strong class="text-primary"><?php echo $p['predicted_units']; ?></strong> وحدة</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-info text-white"><i class="fas fa-lightbulb"></i> توصيات النظام</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($predictions as $p): 
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM BloodBags WHERE blood_type=? AND status='Available'");
                        $stmt->execute([$p['blood_type']]);
                        $current = $stmt->fetchColumn();
                        $diff = $p['predicted_units'] - $current;
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo statusBadge($p['blood_type']); ?>
                        <span>
                            المتوقع: <strong><?php echo $p['predicted_units']; ?></strong> | 
                            الحالي: <strong><?php echo $current; ?></strong>
                            <?php if ($diff > 0): ?>
                                <span class="badge bg-danger ms-2">نقص: <?php echo $diff; ?></span>
                            <?php else: ?>
                                <span class="badge bg-success ms-2">كافٍ</span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        const ctx = document.getElementById('predictionChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($predictions, 'blood_type')); ?>,
                datasets: [{
                    label: 'الوحدات المتوقعة',
                    data: <?php echo json_encode(array_column($predictions, 'predicted_units')); ?>,
                    backgroundColor: '#B03A2E',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, title: { display: true, text: 'الوحدات' } } }
            }
        });
        </script>
        <?php else: ?>
            <div class="alert alert-info">لا توجد بيانات تنبؤ. تأكد من تشغيل خدمة الذكاء الاصطناعي.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
