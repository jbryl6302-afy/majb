<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn() || !hasRole('Doctor')) redirect('../../dashboard.php');

$pageTitle = 'طلب دم جديد';
$success = $error = '';

// [جديد] قائمة المستشفيات + مستشفى الدكتور الحالي (إن وجد)
$hospitals = $pdo->query("SELECT hospital_id, hospital_name FROM Hospitals ORDER BY hospital_name")->fetchAll();
$stmt = $pdo->prepare("SELECT hospital_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctorHospital = $stmt->fetchColumn() ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // [جديد] المستشفى: من الفورم، وإلا من مستشفى الدكتور
        $hospital_id = !empty($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : $doctorHospital;
        if (!$hospital_id) {
            throw new Exception('يرجى اختيار المستشفى.');
        }

        $pdo->beginTransaction();
        // [معدّل] إضافة hospital_id في الـ INSERT
        $stmt = $pdo->prepare("INSERT INTO BloodRequests (doctor_id, hospital_id, patient_name, patient_blood_type, units_needed, urgency) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $hospital_id, $_POST['patient_name'], $_POST['patient_blood_type'], $_POST['units_needed'], $_POST['urgency']]);
        $request_id = $pdo->lastInsertId();

        if (!empty($_POST['selected_bags'])) {
            foreach ($_POST['selected_bags'] as $bag_id) {
                $pdo->prepare("INSERT INTO RequestAllocations (request_id, bag_id) VALUES (?, ?)")->execute([$request_id, $bag_id]);
                $pdo->prepare("UPDATE BloodBags SET status='Reserved' WHERE bag_id=?")->execute([$bag_id]);
            }
        }
        $pdo->commit();
        $success = "تم إرسال الطلب #$request_id بنجاح!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$patientBloodType = $_GET['blood_type'] ?? 'A+';
// [جديد] المستشفى المحدد (يبقى محفوظًا عند تغيير الفصيلة)
$selectedHospital = $_GET['hospital_id'] ?? $_POST['hospital_id'] ?? $doctorHospital;
$compatibleTypes = getCompatibleTypes($patientBloodType);
$placeholders = implode(',', array_fill(0, count($compatibleTypes), '?'));

$stmt = $pdo->prepare("SELECT b.*, d.full_name as donor_name, s.unit_name, s.location, DATEDIFF(b.expiry_date, NOW()) as days_until_expiry FROM BloodBags b LEFT JOIN Donors d ON b.donor_id = d.donor_id LEFT JOIN StorageUnits s ON b.storage_unit_id = s.unit_id WHERE b.blood_type IN ($placeholders) AND b.status = 'Available' AND b.expiry_date > NOW() ORDER BY b.expiry_date ASC, b.donation_date ASC");
$stmt->execute($compatibleTypes);
$recommendations = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom"><h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5></div>
        <a href="../../dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="list.php"><i class="fas fa-hand-holding-medical"></i> طلبات الدم</a>
        <a href="add.php" class="active"><i class="fas fa-plus-circle"></i> طلب جديد</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <h3 class="mb-4"><i class="fas fa-hand-holding-medical text-danger"></i> طلب كيس دم</h3>

        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="POST">
            <div class="card shadow-sm p-4 mb-4">
                <h5 class="text-primary mb-3"><i class="fas fa-user-injured"></i> بيانات المريض</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">اسم المريض *</label>
                        <input type="text" name="patient_name" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">فصيلة الدم المطلوبة *</label>
                        <select name="patient_blood_type" class="form-select" onchange="window.location.href='add.php?blood_type='+encodeURIComponent(this.value)+'&hospital_id='+encodeURIComponent(document.getElementById('hospital_id').value)" required>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                            <option value="<?php echo $bt; ?>" <?php echo ($patientBloodType==$bt)?'selected':''; ?>><?php echo $bt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">عدد الوحدات *</label>
                        <input type="number" name="units_needed" class="form-control" value="1" min="1" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">درجة الاستعجال</label>
                        <select name="urgency" class="form-select">
                            <option value="Normal">عادي</option>
                            <option value="Urgent">مستعجل</option>
                            <option value="Critical">حرج</option>
                        </select>
                    </div>
                    <!-- [جديد] اختيار المستشفى -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label">المستشفى *</label>
                        <select name="hospital_id" id="hospital_id" class="form-select" required>
                            <option value="">-- اختر المستشفى --</option>
                            <?php foreach ($hospitals as $h): ?>
                            <option value="<?php echo $h['hospital_id']; ?>" <?php echo ($selectedHospital == $h['hospital_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($h['hospital_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-robot"></i> التوصيات الذكية (FIFO + التوافق)
                    <span class="float-start badge bg-light text-dark">الفصائل المتوافقة: <?php echo implode(', ', $compatibleTypes); ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($recommendations)): ?>
                        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> لا توجد أكياس دم متوافقة متاحة.</div>
                    <?php else: ?>
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr><th><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th><th>رقم الكيس</th><th>الفصيلة</th><th>المتبرع</th><th>الانتهاء</th><th>الأيام المتبقية</th><th>التخزين</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recommendations as $rec): ?>
                            <tr class="<?php echo $rec['days_until_expiry'] <= 7 ? 'table-warning' : ''; ?>">
                                <td><input type="checkbox" name="selected_bags[]" value="<?php echo $rec['bag_id']; ?>" class="bag-checkbox"></td>
                                <td>#<?php echo $rec['bag_id']; ?></td>
                                <td><?php echo statusBadge($rec['blood_type']); ?></td>
                                <td><?php echo $rec['donor_name'] ?? 'غير متوفر'; ?></td>
                                <td><?php echo date('d M Y', strtotime($rec['expiry_date'])); ?></td>
                                <td><span class="badge bg-<?php echo $rec['days_until_expiry'] <= 7 ? 'warning text-dark' : 'success'; ?>"><?php echo $rec['days_until_expiry']; ?> يوم</span></td>
                                <td><?php echo $rec['unit_name']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> إرسال الطلب مع الأكياس المحددة</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<script>function toggleAll(source){ document.querySelectorAll('.bag-checkbox').forEach(cb=>cb.checked=source.checked); }</script>

<?php require_once '../../includes/footer.php'; ?>