<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn() || (!hasRole('LabTechnician') && !hasRole('Admin'))) {
    redirect('../../dashboard.php');
}

$pageTitle = 'إدخال نتائج الفحص';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $bag_id = intval($_POST['bag_id']);

        // تحديد الحالة الكلية
        $overall = 'Approved';
        if ($_POST['hiv'] === 'Positive' || $_POST['hepatitis'] === 'Positive' || $_POST['syphilis'] === 'Positive' || $_POST['malaria'] === 'Positive') {
            $overall = 'Rejected';
        }

        // تحديث نتائج الفحص
        $stmt = $pdo->prepare("UPDATE TestResults SET hiv_result=?, hepatitis_result=?, syphilis_result=?, malaria_result=?, overall_status=?, test_date=NOW(), tested_by=? WHERE bag_id=?");
        $stmt->execute([
            $_POST['hiv'],
            $_POST['hepatitis'],
            $_POST['syphilis'],
            $_POST['malaria'],
            $overall,
            $_SESSION['user_id'],
            $bag_id
        ]);

        // تحديث حالة الكيس
        if ($overall === 'Approved') {
            $bagStatus = 'Available';
        } else {
            $bagStatus = 'Discarded';
        }

        $stmt2 = $pdo->prepare("UPDATE BloodBags SET status=? WHERE bag_id=?");
        $stmt2->execute([$bagStatus, $bag_id]);

        // تسجيل التتبع
        logTracking($pdo, $bag_id, 'نتيجة الفحص: ' . $overall, 'المختبر', $_SESSION['user_id']);

        $success = "تم حفظ النتائج! الكيس #" . $bag_id . " الآن " . $bagStatus . ".";

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pendingBags = $pdo->query("SELECT b.bag_id, b.qr_code, b.blood_type, b.donation_date, d.full_name as donor_name FROM BloodBags b JOIN TestResults t ON b.bag_id = t.bag_id LEFT JOIN Donors d ON b.donor_id = d.donor_id WHERE t.overall_status = 'Pending' ORDER BY b.donation_date DESC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom"><h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5></div>
        <a href="../../dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="enter.php" class="active"><i class="fas fa-vial"></i> الفحوصات المخبرية</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <h3 class="mb-4"><i class="fas fa-vial text-warning"></i> إدخال نتائج الفحص المخبري</h3>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row">
            <?php foreach ($pendingBags as $bag): ?>
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-syringe"></i> كيس #<?php echo $bag['bag_id']; ?> | <?php echo $bag['qr_code']; ?>
                    </div>
                    <div class="card-body">
                        <p><strong>المتبرع:</strong> <?php echo $bag['donor_name'] ?? 'غير متوفر'; ?> | <strong>الفصيلة:</strong> <?php echo statusBadge($bag['blood_type']); ?></p>
                        <form method="POST">
                            <input type="hidden" name="bag_id" value="<?php echo $bag['bag_id']; ?>">
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="form-label small">HIV</label>
                                    <select name="hiv" class="form-select form-select-sm">
                                        <option value="Negative">سلبي</option>
                                        <option value="Positive">إيجابي</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label small">التهاب الكبد</label>
                                    <select name="hepatitis" class="form-select form-select-sm">
                                        <option value="Negative">سلبي</option>
                                        <option value="Positive">إيجابي</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label small">الزهري</label>
                                    <select name="syphilis" class="form-select form-select-sm">
                                        <option value="Negative">سلبي</option>
                                        <option value="Positive">إيجابي</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label small">الملاريا</label>
                                    <select name="malaria" class="form-select form-select-sm">
                                        <option value="Negative">سلبي</option>
                                        <option value="Positive">إيجابي</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100 mt-2">
                                <i class="fas fa-save"></i> حفظ النتائج
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($pendingBags)): ?>
            <div class="col-12">
                <div class="alert alert-info">لا توجد فحوصات معلقة.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>