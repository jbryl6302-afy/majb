# Blood Bank 2 - Quick Setup

## طريقة الاستخدام:

1. افك الضغط في مجلد المشروع (bloodbank2/)
2. عدل الملفات الموجودة دي:

### pages/donors/add.php
اضف في اول الملف:
require_once __DIR__ . '/../../includes/donation_validation.php';

وفي معالجة POST (قبل INSERT):
$eligibility = validateDonorEligibility($_POST['last_donation'] ?? '');
if (!$eligibility['can_donate']) {
    $errors[] = $eligibility['message'];
}

### pages/donors/edit.php
نفس التعديلات اعلاه.

### pages/bags/view.php
اضف في اول الملف:
require_once __DIR__ . '/../../includes/donation_validation.php';

وفي مكان عرض الكيس:
displayQRCode($bag['bag_id'], $bag['blood_type'], $bag['donation_date']);

### includes/header.php + index.php
غير 'بنك الدم' لـ 'بنك الدم 2'

3. جرب محليا: http://localhost/bloodbank2/setup.php
4. commit & push
5. Deploy على Render

## بيانات الدخول:
admin@bloodbank2.com / password
