<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn() || (!hasRole('LabTechnician') && !hasRole('Admin'))) {
    redirect('../../dashboard.php');
}

$pageTitle = 'الفحص المبدئي للمتبرع';
$success = '';
$error = '';
$donor = null;
$logHasSmoker = in_array('smoker', tableColumns($pdo, 'DonorScreeningLog'));

// ─── البحث عن المتبرع ───
if (isset($_GET['search']) && !empty($_GET['search_term'])) {
    $search = '%' . trim($_GET['search_term']) . '%';
    $stmt = $pdo->prepare("SELECT * FROM Donors WHERE bag_number LIKE ? OR full_name LIKE ? OR phone LIKE ? LIMIT 10");
    $stmt->execute([$search, $search, $search]);
    $searchResults = $stmt->fetchAll();
}

// ─── عرض بيانات متبرع محدد ───
if (isset($_GET['donor_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM Donors WHERE donor_id = ?");
    $stmt->execute([$_GET['donor_id']]);
    $donor = $stmt->fetch();
}

// ─── حفظ نتائج الفحص المبدئي ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['screening'])) {
    try {
        $donor_id = intval($_POST['donor_id']);

        // تحديد الحالة الكلية
        $overall = 'Approved';
        if ($_POST['hiv'] === 'Positive' || $_POST['hepatitis'] === 'Positive' || $_POST['syphilis'] === 'Positive' || $_POST['malaria'] === 'Positive') {
            $overall = 'Rejected';
        }

        // تحديث بيانات المتبرع
        $stmt = $pdo->prepare("
            UPDATE Donors SET 
                hiv_screening = ?,
                hepatitis_screening = ?,
                syphilis_screening = ?,
                malaria_screening = ?,
                screening_status = ?,
                screened_at = NOW(),
                screened_by = ?,
                is_eligible = ?,
                rejection_reason = CASE WHEN ? = 'Rejected' THEN 'فحوصات مبدئية إيجابية - غير مسموح بالتبرع' ELSE NULL END
            WHERE donor_id = ?
        ");

        $isEligible = ($overall === 'Approved') ? 1 : 0;
        $stmt->execute([
            $_POST['hiv'],
            $_POST['hepatitis'],
            $_POST['syphilis'],
            $_POST['malaria'],
            $overall,
            $_SESSION['user_id'],
            $isEligible,
            $overall,
            $donor_id
        ]);

        // تسجيل في السجل
        $smokerVal = 'No';
        if ($logHasSmoker) {
            $sm = $pdo->prepare("SELECT smoker FROM Donors WHERE donor_id = ?");
            $sm->execute([$donor_id]);
            $smokerVal = $sm->fetchColumn() ?: 'No';
        }
        if ($logHasSmoker) {
            $pdo->prepare("
                INSERT INTO DonorScreeningLog 
                (donor_id, smoker, hiv_result, hepatitis_result, syphilis_result, malaria_result, overall_status, screened_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $donor_id,
                $smokerVal,
                $_POST['hiv'],
                $_POST['hepatitis'],
                $_POST['syphilis'],
                $_POST['malaria'],
                $overall,
                $_SESSION['user_id'],
                $_POST['notes'] ?? null
            ]);
        } else {
            $pdo->prepare("
                INSERT INTO DonorScreeningLog 
                (donor_id, hiv_result, hepatitis_result, syphilis_result, malaria_result, overall_status, screened_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $donor_id,
                $_POST['hiv'],
                $_POST['hepatitis'],
                $_POST['syphilis'],
                $_POST['malaria'],
                $overall,
                $_SESSION['user_id'],
                $_POST['notes'] ?? null
            ]);
        }

        if ($overall === 'Approved') {
            $success = "✅ المتبرع #" . $donor_id . " اجتاز الفحص المبدئي بنجاح! يمكنه التبرع الآن.";
        } else {
            $error = "❌ المتبرع #" . $donor_id . " لم يجتاز الفحص المبدئي. ممنوع من التبرع.";
        }

        // إعادة تحميل بيانات المتبرع
        $stmt = $pdo->prepare("SELECT * FROM Donors WHERE donor_id = ?");
        $stmt->execute([$donor_id]);
        $donor = $stmt->fetch();

    } catch (Exception $e) {
        $error = "خطأ: " . $e->getMessage();
    }
}

require_once '../../includes/header.php';
?>

<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom"><h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5></div>
        <a href="../../dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="../donors/list.php"><i class="fas fa-user-plus"></i> المتبرعون</a>
        <a href="screening.php" class="active"><i class="fas fa-vial"></i> الفحص المبدئي</a>
        <a href="../donors/add.php"><i class="fas fa-plus-circle"></i> تبرع جديد</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <h3 class="mb-4"><i class="fas fa-microscope text-warning"></i> الفحص المبدئي للمتبرع (Pre-donation Screening)</h3>
        <p class="text-muted mb-4">🔬 يجب إجراء الفحوصات المبدئية على المتبرع <strong>قبل</strong> سحب الدم. إذا كانت النتائج سلبية يتم السماح بالتبرع.</p>

        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ─── البحث عن المتبرع ─── -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white"><i class="fas fa-search"></i> البحث عن متبرع</div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-8">
                        <input type="text" name="search_term" class="form-control" 
                               placeholder="ابحث برقم الكيس، الاسم، أو رقم الهاتف..." 
                               value="<?php echo isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="search" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> بحث
                        </button>
                    </div>
                </form>

                <?php if (isset($searchResults) && !empty($searchResults)): ?>
                <div class="table-responsive mt-3">
                    <table class="table table-hover table-sm">
                        <thead class="table-light"><tr><th>رقم الكيس</th><th>الاسم</th><th>الفصيلة</th><th>حالة الفحص</th><th>إجراء</th></tr></thead>
                        <tbody>
                            <?php foreach ($searchResults as $d): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($d['bag_number'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($d['full_name']); ?></td>
                                <td><?php echo statusBadge($d['blood_type']); ?></td>
                                <td>
                                    <?php if ($d['screening_status'] === 'Approved'): ?>
                                        <span class="badge bg-success">✅ مقبول</span>
                                    <?php elseif ($d['screening_status'] === 'Rejected'): ?>
                                        <span class="badge bg-danger">❌ مرفوض</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">⏳ لم يُفحص</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="screening.php?donor_id=<?php echo $d['donor_id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-vial"></i> إجراء الفحص
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif (isset($searchResults) && empty($searchResults)): ?>
                <div class="alert alert-info mt-3">لا توجد نتائج.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ─── نموذج إدخال الفحص ─── -->
        <?php if ($donor): ?>
        <div class="card shadow-sm border-warning">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-user-check"></i> فحص المتبرع: <?php echo htmlspecialchars($donor['full_name']); ?> 
                <small>(رقم الكيس: <?php echo htmlspecialchars($donor['bag_number'] ?? '-'); ?>)</small>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>الاسم:</strong> <?php echo htmlspecialchars($donor['full_name']); ?></div>
                    <div class="col-md-3"><strong>العمر:</strong> <?php echo $donor['age'] ?? '-'; ?> سنة</div>
                    <div class="col-md-3"><strong>الوزن:</strong> <?php echo $donor['weight'] ?? '-'; ?> كجم</div>
                    <div class="col-md-3"><strong>الفصيلة:</strong> <?php echo statusBadge($donor['blood_type']); ?></div>
                    <div class="col-md-3"><strong>التدخين:</strong> <?php echo (($donor['smoker'] ?? 'No') === 'Yes') ? '<span class="badge bg-secondary">🚬 مدخن</span>' : '<span class="badge bg-light text-dark">غير مدخن</span>'; ?></div>
                </div>

                <?php if ($donor['screening_status'] === 'Approved'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-double"></i> هذا المتبرع <strong>اجتاز الفحص المبدئي</strong> بتاريخ <?php echo $donor['screened_at']; ?>. يمكن الذهاب لصفحة <a href="../donors/add.php?donor_id=<?php echo $donor['donor_id']; ?>" class="alert-link">التبرع</a>.
                </div>
                <?php elseif ($donor['screening_status'] === 'Rejected'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-ban"></i> هذا المتبرع <strong>مرفوض</strong> من الفحص المبدئي. السبب: <?php echo htmlspecialchars($donor['rejection_reason'] ?? 'فحوصات إيجابية'); ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="donor_id" value="<?php echo $donor['donor_id']; ?>">
                    <input type="hidden" name="screening" value="1">

                    <h6 class="text-primary mb-3">🔬 نتائج الفحوصات المبدئية</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">HIV <span class="text-danger">*</span></label>
                            <select name="hiv" class="form-select" required>
                                <option value="Negative" <?php echo ($donor['hiv_screening'] ?? '') === 'Negative' ? 'selected' : ''; ?>>✅ سلبي (Negative)</option>
                                <option value="Positive" <?php echo ($donor['hiv_screening'] ?? '') === 'Positive' ? 'selected' : ''; ?>>❌ إيجابي (Positive)</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">التهاب الكبد <span class="text-danger">*</span></label>
                            <select name="hepatitis" class="form-select" required>
                                <option value="Negative" <?php echo ($donor['hepatitis_screening'] ?? '') === 'Negative' ? 'selected' : ''; ?>>✅ سلبي (Negative)</option>
                                <option value="Positive" <?php echo ($donor['hepatitis_screening'] ?? '') === 'Positive' ? 'selected' : ''; ?>>❌ إيجابي (Positive)</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">الزهري <span class="text-danger">*</span></label>
                            <select name="syphilis" class="form-select" required>
                                <option value="Negative" <?php echo ($donor['syphilis_screening'] ?? '') === 'Negative' ? 'selected' : ''; ?>>✅ سلبي (Negative)</option>
                                <option value="Positive" <?php echo ($donor['syphilis_screening'] ?? '') === 'Positive' ? 'selected' : ''; ?>>❌ إيجابي (Positive)</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">الملاريا <span class="text-danger">*</span></label>
                            <select name="malaria" class="form-select" required>
                                <option value="Negative" <?php echo ($donor['malaria_screening'] ?? '') === 'Negative' ? 'selected' : ''; ?>>✅ سلبي (Negative)</option>
                                <option value="Positive" <?php echo ($donor['malaria_screening'] ?? '') === 'Positive' ? 'selected' : ''; ?>>❌ إيجابي (Positive)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="أي ملاحظات إضافية..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>تنبيه:</strong> إذا كانت <strong>جميع</strong> النتائج سلبية (Negative) ➜ المتبرع يصبح <span class="badge bg-success">مقبول</span> ويستطيع التبرع.<br>
                        إذا كانت <strong>أي</strong> نتيجة إيجابية (Positive) ➜ المتبرع يصبح <span class="badge bg-danger">مرفوض</span> ولا يستطيع التبرع.
                    </div>

                    <button type="submit" class="btn btn-warning btn-lg">
                        <i class="fas fa-flask"></i> حفظ نتائج الفحص المبدئي
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>