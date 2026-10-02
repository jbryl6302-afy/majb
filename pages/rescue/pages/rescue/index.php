<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/rescue_functions.php';
if (!isLoggedIn() || !hasRole('Admin')) redirect('../../dashboard.php');

$pageTitle = 'الإنقاذ الذكي لفائض الدم';
$counts = rescueCounts($pdo);

$suggested = $pdo->query("SELECT t.*, b.qr_code, b.blood_type, b.volume_ml, b.expiry_date,
                          DATEDIFF(b.expiry_date, NOW()) AS days_left,
                          f.name AS facility_name, f.city, f.distance_km, f.type AS facility_type
                          FROM Transfers t JOIN BloodBags b ON t.bag_id = b.bag_id
                          LEFT JOIN Facilities f ON t.to_facility_id = f.facility_id
                          WHERE t.status='Suggested' ORDER BY t.priority_score DESC")->fetchAll();

$active = $pdo->query("SELECT t.*, b.qr_code, b.blood_type, b.expiry_date,
                       f.name AS facility_name, f.city
                       FROM Transfers t JOIN BloodBags b ON t.bag_id = b.bag_id
                       LEFT JOIN Facilities f ON t.to_facility_id = f.facility_id
                       WHERE t.status IN ('Approved','InTransit') ORDER BY t.suggested_at DESC")->fetchAll();

$history = $pdo->query("SELECT t.*, b.qr_code, b.blood_type, f.name AS facility_name
                        FROM Transfers t JOIN BloodBags b ON t.bag_id = b.bag_id
                        LEFT JOIN Facilities f ON t.to_facility_id = f.facility_id
                        WHERE t.status IN ('Completed','Rejected','Cancelled')
                        ORDER BY t.suggested_at DESC LIMIT 15")->fetchAll();

$flash = getFlash();
$csrf = csrfToken();

// 🆕 الأكياس المهددة بالهدر (≤7 أيام) واللي ما عندها اقتراح/نقل نشط
$expiringNoSuggestion = $pdo->query("SELECT b.bag_id, b.qr_code, b.blood_type, b.expiry_date,
    DATEDIFF(b.expiry_date, NOW()) AS days_left
    FROM BloodBags b
    WHERE b.status='Available'
      AND b.expiry_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
      AND NOT EXISTS (SELECT 1 FROM Transfers t
                      WHERE t.bag_id = b.bag_id
                        AND t.status IN ('Suggested','Approved','InTransit'))
    ORDER BY b.expiry_date ASC")->fetchAll();

// 🆕 قائمة الجهات (مستشفيات/بنوك دم) النشطة للاختيار اليدوي
$facilityList = $pdo->query("SELECT facility_id, name, type, city, distance_km
    FROM Facilities WHERE is_active = TRUE ORDER BY distance_km ASC")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom"><h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5></div>
        <a href="../../dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="index.php" class="active"><i class="fas fa-truck-medical"></i> الإنقاذ الذكي</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-truck-medical text-danger"></i> نظام الإنقاذ الذكي لفائض الدم</h3>
            <form method="POST" action="decide.php">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="detect">
                <button class="btn btn-danger"><i class="fas fa-sync-alt"></i> فحص الفائض الآن</button>
            </form>
        </div>

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-3"><div class="card text-center border-primary shadow-sm"><div class="card-body"><h2 class="text-primary mb-0"><?php echo $counts['suggested']; ?></h2><small>اقتراحات بانتظار الموافقة</small></div></div></div>
            <div class="col-md-3"><div class="card text-center border-info shadow-sm"><div class="card-body"><h2 class="text-info mb-0"><?php echo $counts['in_transit']; ?></h2><small>نقلات نشطة</small></div></div></div>
            <div class="col-md-3"><div class="card text-center border-success shadow-sm"><div class="card-body"><h2 class="text-success mb-0"><?php echo $counts['completed']; ?></h2><small>نقلات مكتملة</small></div></div></div>
            <div class="col-md-3"><div class="card text-center border-danger shadow-sm"><div class="card-body"><h2 class="text-danger mb-0"><?php echo $counts['wasted_risk']; ?></h2><small>كيس مهدد بالهدر (7 أيام)</small></div></div></div>
        </div>

        <div class="card shadow-sm mb-4 border-warning">
            <div class="card-header bg-warning text-dark"><i class="fas fa-hospital"></i> 🆕 الأكياس المهددة بالهدر بدون اقتراح — اطلب نقلها لجهة أخرى</div>
            <div class="card-body table-responsive">
                <?php if (empty($expiringNoSuggestion)): ?>
                <p class="text-muted text-center py-2 mb-0">✅ كل الأكياس المهددة بالهدر عليها اقتراحات نقل أو لا يوجد أكياس مهددة حالياً</p>
                <?php else: ?>
                <table class="table table-hover align-middle">
                    <thead class="table-warning"><tr><th>الكيس</th><th>الفصيلة</th><th>متبقٍ</th><th>تاريخ الانتهاء</th><th>الجهة المطلوب نقلها إليها</th><th>إجراء</th></tr></thead>
                    <tbody>
                        <?php foreach ($expiringNoSuggestion as $bag): ?>
                        <tr class="<?php echo $bag['days_left'] <= 3 ? 'table-danger' : ''; ?>">
                            <td><code><?php echo $bag['qr_code']; ?></code></td>
                            <td><?php echo statusBadge($bag['blood_type']); ?></td>
                            <td><span class="badge bg-<?php echo $bag['days_left'] <= 3 ? 'danger' : 'warning text-dark'; ?> fs-6"><?php echo $bag['days_left']; ?> يوم</span></td>
                            <td><?php echo formatDate($bag['expiry_date']); ?></td>
                            <td>
                                <form method="POST" action="decide.php" class="d-flex gap-2">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="action" value="request">
                                    <input type="hidden" name="bag_id" value="<?php echo $bag['bag_id']; ?>">
                                    <select name="facility_id" class="form-select form-select-sm" style="min-width:260px" required>
                                        <option value="">— اختر المستشفى / الجهة —</option>
                                        <?php foreach ($facilityList as $f): ?>
                                        <option value="<?php echo $f['facility_id']; ?>">
                                            <?php echo htmlspecialchars($f['name']); ?> (<?php echo $f['type'] === 'Hospital' ? 'مستشفى' : 'بنك دم'; ?> — <?php echo htmlspecialchars($f['city']); ?> — <?php echo $f['distance_km']; ?> كم)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-danger text-nowrap" onclick="return confirm('إرسال طلب نقل الكيس <?php echo $bag['qr_code']; ?> إلى الجهة المختارة؟')"><i class="fas fa-paper-plane"></i> اطلب النقل</button>
                                </form>
                            </td>
                            <td class="text-muted small">ينشئ اقتراح نقل بنفس دورة الموافقة والشحن</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-danger text-white"><i class="fas fa-lightbulb"></i> اقتراحات النقل الإنقاذي (مرتبة حسب درجة الأولوية)</div>
            <div class="card-body table-responsive">
                <?php if (empty($suggested)): ?>
                <p class="text-muted text-center py-3">لا توجد اقتراحات حالياً — اضغط "فحص الفائض الآن" لتحليل المخزون</p>
                <?php else: ?>
                <table class="table table-hover align-middle">
                    <thead class="table-dark"><tr><th>الأولوية</th><th>الكيس</th><th>الفصيلة</th><th>متبقٍ</th><th>الجهة المقترحة</th><th>المدينة</th><th>المسافة</th><th>ملاحظات</th><th>إجراء</th></tr></thead>
                    <tbody>
                        <?php foreach ($suggested as $s): ?>
                        <tr>
                            <td><span class="badge bg-<?php echo $s['priority_score'] >= 70 ? 'danger' : ($s['priority_score'] >= 45 ? 'warning text-dark' : 'secondary'); ?> fs-6"><?php echo $s['priority_score']; ?>%</span></td>
                            <td><code><?php echo $s['qr_code']; ?></code></td>
                            <td><?php echo statusBadge($s['blood_type']); ?></td>
                            <td><?php echo $s['days_left']; ?> يوم</td>
                            <td><strong><?php echo htmlspecialchars($s['facility_name']); ?></strong><br><small class="text-muted"><?php echo $s['facility_type'] === 'Hospital' ? '🏥 مستشفى' : '🩸 بنك دم'; ?></small></td>
                            <td><?php echo htmlspecialchars($s['city']); ?></td>
                            <td><?php echo $s['distance_km']; ?> كم</td>
                            <td><small><?php echo htmlspecialchars($s['notes']); ?></small></td>
                            <td class="text-nowrap">
                                <form method="POST" action="decide.php" class="d-inline">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="transfer_id" value="<?php echo $s['transfer_id']; ?>">
                                    <button name="action" value="approve" class="btn btn-sm btn-success" title="موافقة"><i class="fas fa-check"></i></button>
                                    <button name="action" value="reject" class="btn btn-sm btn-outline-danger" title="رفض" onclick="return confirm('رفض هذا الاقتراح؟')"><i class="fas fa-times"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white"><i class="fas fa-shipping-fast"></i> نقلات نشطة (معتمدة / في الطريق)</div>
            <div class="card-body table-responsive">
                <?php if (empty($active)): ?><p class="text-muted text-center py-2">لا توجد نقلات نشطة</p><?php else: ?>
                <table class="table table-sm align-middle">
                    <thead><tr><th>#</th><th>الكيس</th><th>الفصيلة</th><th>الجهة</th><th>الحالة</th><th>رمز التسليم</th><th>إجراءات</th></tr></thead>
                    <tbody>
                        <?php foreach ($active as $a): ?>
                        <tr>
                            <td>#<?php echo $a['transfer_id']; ?></td>
                            <td><code><?php echo $a['qr_code']; ?></code></td>
                            <td><?php echo statusBadge($a['blood_type']); ?></td>
                            <td><?php echo htmlspecialchars($a['facility_name']); ?></td>
                            <td><?php echo statusBadge($a['status']); ?></td>
                            <td><code class="small"><?php echo $a['qr_token']; ?></code></td>
                            <td class="text-nowrap">
                                <?php if ($a['status'] === 'Approved'): ?>
                                <a href="handover.php?transfer_id=<?php echo $a['transfer_id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-print"></i> ورقة التسليم</a>
                                <form method="POST" action="decide.php" class="d-inline">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="transfer_id" value="<?php echo $a['transfer_id']; ?>">
                                    <button name="action" value="ship" class="btn btn-sm btn-info text-white">انطلق 🚑</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" action="decide.php" class="d-inline">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="transfer_id" value="<?php echo $a['transfer_id']; ?>">
                                    <button name="action" value="complete" class="btn btn-sm btn-success">تأكيد الاستلام ✔</button>
                                    <button name="action" value="cancel" class="btn btn-sm btn-outline-danger" onclick="return confirm('إلغاء النقل وإعادة الكيس للمخزون؟')">إلغاء</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white"><i class="fas fa-history"></i> سجل النقلات</div>
            <div class="card-body table-responsive">
                <?php if (empty($history)): ?><p class="text-muted text-center py-2">لا يوجد سجل بعد</p><?php else: ?>
                <table class="table table-sm">
                    <thead><tr><th>#</th><th>الكيس</th><th>الفصيلة</th><th>الجهة</th><th>الحالة</th><th>تاريخ الاقتراح</th></tr></thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td>#<?php echo $h['transfer_id']; ?></td>
                            <td><code><?php echo $h['qr_code']; ?></code></td>
                            <td><?php echo statusBadge($h['blood_type']); ?></td>
                            <td><?php echo htmlspecialchars($h['facility_name']); ?></td>
                            <td><?php echo statusBadge($h['status']); ?></td>
                            <td><?php echo formatDate($h['suggested_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
