<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn() || (!hasRole('LabTechnician') && !hasRole('Admin'))) redirect('../../dashboard.php');

// ─── توليد رقم الكيس تلقائياً: BAG-2026-0001 ───
if (!function_exists('generateBagNumber')) {
    function generateBagNumber(PDO $pdo): string {
        $prefix = 'BAG-' . date('Y') . '-';
        $stmt = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING_INDEX(bag_number, '-', -1) AS UNSIGNED))
            FROM Donors WHERE bag_number LIKE ?
        ");
        $stmt->execute([$prefix . '%']);
        $next = ((int)$stmt->fetchColumn()) + 1;
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}

$pageTitle = 'تبرع جديد';
$success = $error = '';

// ─── أعمدة جدول المتبرعين المتاحة (للتوافق مع قواعد البيانات القديمة) ───
$donorCols = tableColumns($pdo, 'Donors');
$hasSmokingCols = in_array('smoker', $donorCols);
$hasPrescreenCol = in_array('prescreen_sample', $donorCols);
$logHasSmoker = in_array('smoker', tableColumns($pdo, 'DonorScreeningLog'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ─── جمع البيانات ───
    $full_name      = trim($_POST['full_name'] ?? '');
    $bag_number     = generateBagNumber($pdo); // تلقائي
    $age            = intval($_POST['age'] ?? 0);
    $weight         = floatval($_POST['weight'] ?? 0);
    $blood_type     = $_POST['blood_type'] ?? '';
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $last_donation  = !empty($_POST['last_donation']) ? $_POST['last_donation'] : null;
    $health_status  = trim($_POST['health_status'] ?? 'جيد');
    $storage_unit_id = intval($_POST['storage_unit_id'] ?? 0);
    $volume_ml      = intval($_POST['volume_ml'] ?? 450);

    // 🚬 سؤال التدخين
    $smoker          = (($_POST['smoker'] ?? 'No') === 'Yes') ? 'Yes' : 'No';
    $last_smoke_raw  = trim($_POST['last_smoke_time'] ?? '');
    $last_smoke_time = null;
    if ($smoker === 'Yes' && !empty($last_smoke_raw)) {
        $last_smoke_time = str_replace('T', ' ', $last_smoke_raw);
        if (strlen($last_smoke_time) === 16) $last_smoke_time .= ':00';
    }

    // 🔬 نتائج فحص العينة المأخوذة قبل التبرع
    $hiv       = $_POST['hiv'] ?? 'Negative';
    $hepatitis = $_POST['hepatitis'] ?? 'Negative';
    $syphilis  = $_POST['syphilis'] ?? 'Negative';
    $malaria   = $_POST['malaria'] ?? 'Negative';

    $errors = [];

    // ─── التحقق 1: العمر >= 18 ───
    if ($age < 18) {
        $errors[] = "العمر يجب أن يكون 18 سنة أو أكثر (المدخل: $age)";
    }

    // ─── التحقق 2: الوزن >= 50 ───
    if ($weight < 50) {
        $errors[] = "الوزن يجب أن يكون 50 كجم أو أكثر (المدخل: $weight)";
    }

    // ─── التحقق 3: 3 أشهر من آخر تبرع ───
    if ($last_donation) {
        $lastDate = new DateTime($last_donation);
        $today    = new DateTime();
        $diffDays = $today->diff($lastDate)->days;
        if ($diffDays < 90) {
            $remaining = 90 - $diffDays;
            $errors[] = "لم يمر 3 أشهر على آخر تبرع، باقي $remaining يوم";
        }
    }

    // ─── التحقق 4: الاسم ───
    if (empty($full_name)) {
        $errors[] = "الاسم الكامل مطلوب";
    }

    // ─── التحقق 5: قاعدة التدخين 🚬 ───
    // المدخن يُمنع من التدخين لمدة ساعة كاملة قبل التبرع
    if ($smoker === 'Yes') {
        if (empty($last_smoke_time)) {
            $errors[] = "المتبرع مدخن - يجب تحديد وقت آخر سيجارة للتأكد من مرور ساعة كاملة";
        } else {
            $smokeDate    = new DateTime($last_smoke_time);
            $minutesSince = (new DateTime())->getTimestamp() - $smokeDate->getTimestamp();
            $minutesSince = floor($minutesSince / 60);
            if ($minutesSince < 60) {
                $remaining = 60 - $minutesSince;
                $errors[] = "🚬 قاعدة التدخين: يجب الامتناع عن التدخين لمدة ساعة كاملة قبل التبرع. باقي $remaining دقيقة";
            }
        }
    }

    // ─── تقييم نتائج فحص العينة قبل التبرع ───
    // أي نتيجة إيجابية ⬅️ رفض التبرع فوراً وعدم إنشاء كيس
    $overall = 'Approved';
    if ($hiv === 'Positive' || $hepatitis === 'Positive' || $syphilis === 'Positive' || $malaria === 'Positive') {
        $overall = 'Rejected';
    }

    // ─── إذا فيه أخطاء ➜ نسجّل المتبرع كـ غير مؤهل ───
    if (!empty($errors)) {
        try {
            if ($hasSmokingCols) {
                $stmt = $pdo->prepare("
                    INSERT INTO Donors 
                    (full_name, bag_number, age, weight, blood_type, phone, email, 
                     last_donation, health_status, is_eligible, rejection_reason,
                     smoker, last_smoke_time, prescreen_sample,
                     hiv_screening, hepatitis_screening, syphilis_screening, malaria_screening, screening_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE, ?, ?, ?, 'Taken', ?, ?, ?, ?, 'Rejected')
                ");
                $stmt->execute([
                    $full_name, $bag_number, $age, $weight, $blood_type,
                    $phone, $email, $last_donation, $health_status,
                    implode(' | ', $errors),
                    $smoker, $last_smoke_time,
                    $hiv, $hepatitis, $syphilis, $malaria
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO Donors 
                    (full_name, bag_number, age, weight, blood_type, phone, email, 
                     last_donation, health_status, is_eligible, rejection_reason)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE, ?)
                ");
                $stmt->execute([
                    $full_name, $bag_number, $age, $weight, $blood_type,
                    $phone, $email, $last_donation, $health_status,
                    implode(' | ', $errors)
                ]);
            }
            $error = "❌ تم رفض التسجيل:<br>• " . implode('<br>• ', $errors);
        } catch (Exception $e) {
            $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
        }
    }
    // ─── الفحوصات الأساسية سليمة ➜ نتائج العينة تحدد السماح بالتبرع ───
    else {
        try {
            $pdo->beginTransaction();

            // 1️⃣ تسجيل المتبرع + نتائج فحص العينة المأخوذة قبل التبرع
            if ($hasSmokingCols) {
                $stmt = $pdo->prepare("
                    INSERT INTO Donors 
                    (full_name, bag_number, age, weight, blood_type, phone, email, 
                     last_donation, health_status, is_eligible, rejection_reason,
                     smoker, last_smoke_time, prescreen_sample,
                     hiv_screening, hepatitis_screening, syphilis_screening, malaria_screening,
                     screening_status, screened_at, screened_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Taken', ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $isEligible = ($overall === 'Approved') ? 1 : 0;
                $rejectionReason = ($overall === 'Rejected') ? 'فحص العينة قبل التبرع إيجابي - غير مسموح بالتبرع' : null;
                $stmt->execute([
                    $full_name, $bag_number, $age, $weight, $blood_type,
                    $phone, $email, $last_donation, $health_status,
                    $isEligible, $rejectionReason,
                    $smoker, $last_smoke_time,
                    $hiv, $hepatitis, $syphilis, $malaria,
                    $overall, $_SESSION['user_id']
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO Donors 
                    (full_name, bag_number, age, weight, blood_type, phone, email, 
                     last_donation, health_status, is_eligible, rejection_reason)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, NULL)
                ");
                $stmt->execute([
                    $full_name, $bag_number, $age, $weight, $blood_type,
                    $phone, $email, $last_donation, $health_status
                ]);
            }
            $donor_id = $pdo->lastInsertId();

            // 2️⃣ تسجيل الفحص في السجل
            if ($logHasSmoker) {
                $pdo->prepare("
                    INSERT INTO DonorScreeningLog 
                    (donor_id, smoker, hiv_result, hepatitis_result, syphilis_result, malaria_result, overall_status, screened_by, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $donor_id, $smoker, $hiv, $hepatitis, $syphilis, $malaria,
                    $overall, $_SESSION['user_id'],
                    'فحص عينة مأخوذة قبل التبرع' . ($smoker === 'Yes' ? ' - مدخن (امتنع عن التدخين ساعة كاملة)' : '')
                ]);
            } else {
                $pdo->prepare("
                    INSERT INTO DonorScreeningLog 
                    (donor_id, hiv_result, hepatitis_result, syphilis_result, malaria_result, overall_status, screened_by, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $donor_id, $hiv, $hepatitis, $syphilis, $malaria,
                    $overall, $_SESSION['user_id'],
                    'فحص عينة مأخوذة قبل التبرع' . ($smoker === 'Yes' ? ' - مدخن (امتنع عن التدخين ساعة كاملة)' : '')
                ]);
            }

            // 3️⃣ إذا كانت نتائج العينة سليمة ⬅️ السماح بالتبرع وإنشاء الكيس
            if ($overall === 'Approved') {

                $donation_date = date('Y-m-d H:i:s');
                $expiry_date   = date('Y-m-d H:i:s', strtotime('+42 days'));
                $qr_code       = generateQRCode();

                $stmt = $pdo->prepare("
                    INSERT INTO BloodBags 
                    (donor_id, qr_code, blood_type, volume_ml, donation_date, expiry_date, storage_unit_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $donor_id, $qr_code, $blood_type, $volume_ml,
                    $donation_date, $expiry_date, $storage_unit_id
                ]);
                $bag_id = $pdo->lastInsertId();

                // 4️⃣ نتائج الفحص على الكيس (مبدئية)
                $pdo->prepare("
                    INSERT INTO TestResults (bag_id, test_date, tested_by) 
                    VALUES (?, NOW(), ?)
                ")->execute([$bag_id, $_SESSION['user_id']]);

                // 5️⃣ تتبع
                logTracking($pdo, $bag_id, 'تم تسجيل التبرع', 'الاستقبال', $_SESSION['user_id']);

                $pdo->commit();
                $success = "✅ نجح فحص العينة قبل التبرع - تم <strong>السماح بالتبرع</strong> وإنشاء الكيس!<br>"
                         . "رقم الكيس: $bag_number | QR: $qr_code"
                         . ($smoker === 'Yes' ? "<br>🚬 المتبرع مدخن - تم التأكد من الامتناع عن التدخين لمدة ساعة كاملة." : "");
            }
            // 4️⃣ نتائج العينة غير سليمة ⬅️ الرفض وعدم إنشاء كيس
            else {
                $pdo->commit();
                $error = "❌ تم سحب عينة وفحصها قبل التبرع وكانت النتائج <strong>غير سليمة</strong> (يوجد فحص إيجابي).<br>"
                       . "⛔ <strong>لم يُسمح بالتبرع</strong> ولم يتم إنشاء كيس دم. تم تسجيل المتبرع كمرفوض.";
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "خطأ: " . $e->getMessage();
        }
    }
}

$storageUnits = $pdo->query("SELECT * FROM StorageUnits WHERE status='Active'")->fetchAll();
require_once '../../includes/header.php';
?>

<div class="d-flex">
    <div class="sidebar">
        <div class="p-3 text-white text-center border-bottom"><h5><i class="fas fa-tint text-danger"></i> بنك الدم</h5></div>
        <a href="../../dashboard.php"><i class="fas fa-home"></i> الرئيسية</a>
        <a href="list.php"><i class="fas fa-user-plus"></i> المتبرعون</a>
        <a href="add.php" class="active"><i class="fas fa-plus-circle"></i> تبرع جديد</a>
        <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
    </div>

    <div class="main-content w-100">
        <h3 class="mb-4"><i class="fas fa-user-plus text-danger"></i> تسجيل تبرع جديد</h3>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <br><a href="../bags/list.php" class="btn btn-sm btn-primary mt-2">عرض الأكياس</a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" id="donorForm">
                    <h5 class="text-primary mb-3">👤 بيانات المتبرع</h5>
                    <div class="row">
                        <!-- الاسم -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الاسم الكامل *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>

                        <!-- رقم الكيس (تلقائي) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">رقم الكيس</label>
                            <input type="text" class="form-control"
                                   value="<?php echo htmlspecialchars(generateBagNumber($pdo)); ?>" disabled>
                            <small class="text-muted">يتولد تلقائياً عند الحفظ</small>
                        </div>

                        <!-- العمر -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">العمر (سنة) *</label>
                            <input type="number" name="age" class="form-control"
                                   min="1" max="100" placeholder="مثال: 25" required>
                            <small class="text-muted">يجب 18+</small>
                        </div>

                        <!-- الوزن -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">الوزن (كجم) *</label>
                            <input type="number" name="weight" class="form-control"
                                   min="1" max="200" step="0.1" placeholder="مثال: 70" required>
                            <small class="text-muted">يجب 50+ كجم</small>
                        </div>

                        <!-- فصيلة الدم -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">فصيلة الدم *</label>
                            <select name="blood_type" class="form-select" required>
                                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                                <option value="<?php echo $bt; ?>"><?php echo $bt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- الهاتف -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label">الهاتف</label>
                            <input type="text" name="phone" class="form-control" placeholder="01234567890">
                        </div>

                        <!-- البريد -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" placeholder="email@example.com">
                        </div>

                        <!-- تاريخ آخر تبرع -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاريخ آخر تبرع (إن وجد)</label>
                            <input type="date" name="last_donation" class="form-control">
                            <small class="text-muted">يجب أن يمر 3 أشهر على الأقل</small>
                        </div>

                        <!-- الحالة الصحية -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الحالة الصحية</label>
                            <select name="health_status" class="form-select">
                                <option value="جيد">جيد</option>
                                <option value="متوسطة">متوسطة</option>
                                <option value="تحت الملاحظة">تحت الملاحظة</option>
                            </select>
                        </div>

                        <!-- 🚬 سؤال التدخين -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">🚬 هل المتبرع يدخن؟ *</label>
                            <select name="smoker" id="smokerSelect" class="form-select" required>
                                <option value="No" selected>لا</option>
                                <option value="Yes">نعم</option>
                            </select>
                            <small class="text-muted">⚠️ إذا كان يدخن: يجب الامتناع عن التدخين لمدة <strong>ساعة كاملة</strong> قبل التبرع</small>
                        </div>

                        <!-- 🕐 وقت آخر تدخين (يظهر فقط لو المتبرع مدخن) -->
                        <div class="col-md-6 mb-3" id="lastSmokeWrap" style="display:none;">
                            <label class="form-label">🕐 وقت آخر سيجارة *</label>
                            <input type="datetime-local" name="last_smoke_time" id="lastSmokeInput" class="form-control">
                            <small class="text-muted">لن يُسمح بالتبرع إلا بعد مرور ساعة كاملة على آخر تدخين</small>
                        </div>

                        <!-- وحدة التخزين -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">وحدة التخزين *</label>
                            <select name="storage_unit_id" class="form-select" required>
                                <?php foreach ($storageUnits as $unit): ?>
                                <option value="<?php echo $unit['unit_id']; ?>">
                                    <?php echo $unit['unit_name']; ?> (<?php echo $unit['location']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- الكمية -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الكمية (مل)</label>
                            <input type="number" name="volume_ml" class="form-control"
                                   value="450" min="200" max="500" required>
                        </div>
                    </div>

                    <!-- 🔬 فحص العينة قبل التبرع -->
                    <h5 class="text-primary mb-3 mt-2"><i class="fas fa-vial"></i> فحص عينة المتبرع قبل التبرع (Pre-donation Sample Testing)</h5>
                    <p class="text-muted">يتم سحب عينة دم من المتبرع <strong>قبل</strong> سحب الكيس، وإدخال نتائج الفحوصات هنا. إذا كانت جميع النتائج سلبية يتم السماح بالتبرع تلقائياً، وإذا ظهرت أي نتيجة إيجابية يتم رفض التبرع.</p>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">HIV <span class="text-danger">*</span></label>
                            <select name="hiv" class="form-select" required>
                                <option value="Negative" selected>✅ سلبي (Negative)</option>
                                <option value="Positive">❌ إيجابي (Positive)</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">التهاب الكبد <span class="text-danger">*</span></label>
                            <select name="hepatitis" class="form-select" required>
                                <option value="Negative" selected>✅ سلبي (Negative)</option>
                                <option value="Positive">❌ إيجابي (Positive)</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">الزهري <span class="text-danger">*</span></label>
                            <select name="syphilis" class="form-select" required>
                                <option value="Negative" selected>✅ سلبي (Negative)</option>
                                <option value="Positive">❌ إيجابي (Positive)</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">الملاريا <span class="text-danger">*</span></label>
                            <select name="malaria" class="form-select" required>
                                <option value="Negative" selected>✅ سلبي (Negative)</option>
                                <option value="Positive">❌ إيجابي (Positive)</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>تنبيه:</strong> جميع الفحوصات سلبية ⬅️ <span class="badge bg-success">السماح بالتبرع</span> وإنشاء الكيس.
                        أي فحص إيجابي ⬅️ <span class="badge bg-danger">رفض التبرع</span> ولن يتم إنشاء كيس.
                    </div>

                    <button type="submit" class="btn btn-danger btn-lg">
                        <i class="fas fa-save"></i> حفظ الفحص وتسجيل التبرع
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// إظهار/إخفاء حقل وقت آخر تدخين حسب الإجابة
const smokerSelect = document.getElementById('smokerSelect');
const lastSmokeWrap = document.getElementById('lastSmokeWrap');
const lastSmokeInput = document.getElementById('lastSmokeInput');

function toggleSmokeField() {
    const isSmoker = smokerSelect.value === 'Yes';
    lastSmokeWrap.style.display = isSmoker ? 'block' : 'none';
    lastSmokeInput.required = isSmoker;
    if (!isSmoker) lastSmokeInput.value = '';
}
smokerSelect.addEventListener('change', toggleSmokeField);
toggleSmokeField();

// تحقق فوري في المتصفح (JavaScript)
document.getElementById('donorForm').addEventListener('submit', function(e) {
    let errors = [];
    const age = parseInt(document.querySelector('[name="age"]').value);
    const weight = parseFloat(document.querySelector('[name="weight"]').value);
    const lastDonation = document.querySelector('[name="last_donation"]').value;
    const smoker = smokerSelect.value;
    const lastSmoke = lastSmokeInput.value;

    if (age < 18) errors.push('العمر يجب أن يكون 18 أو أكثر');
    if (weight < 50) errors.push('الوزن يجب أن يكون 50 كجم أو أكثر');

    if (lastDonation) {
        const last = new Date(lastDonation);
        const today = new Date();
        const diff = Math.floor((today - last) / (1000 * 60 * 60 * 24));
        if (diff < 90) errors.push('لم يمر 3 أشهر على آخر تبرع');
    }

    // 🚬 قاعدة التدخين: ساعة كاملة قبل التبرع
    if (smoker === 'Yes') {
        if (!lastSmoke) {
            errors.push('المتبرع مدخن - حدد وقت آخر سيجارة');
        } else {
            const diffMin = Math.floor((new Date() - new Date(lastSmoke)) / 60000);
            if (diffMin < 60) {
                errors.push('🚬 يجب الامتناع عن التدخين لمدة ساعة كاملة قبل التبرع - باقي ' + (60 - diffMin) + ' دقيقة');
            }
        }
    }

    // تأكيد عند وجود فحص إيجابي
    const tests = ['hiv', 'hepatitis', 'syphilis', 'malaria'];
    const anyPositive = tests.some(t => document.querySelector(`[name="${t}"]`).value === 'Positive');
    if (anyPositive && !confirm('⚠️ يوجد فحص إيجابي في نتائج العينة!\nسيتم رفض التبرع ولن يتم إنشاء كيس دم.\nهل تريد المتابعة؟')) {
        e.preventDefault();
        return;
    }

    if (errors.length > 0) {
        e.preventDefault();
        alert('⚠️ ' + errors.join('\n'));
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>