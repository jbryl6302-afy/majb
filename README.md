# 🩸 نظام بنك الدم الذكي
## Smart Blood Bank Management System with AI & QR Code

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white" />
  <img src="https://img.shields.io/badge/Python-3.10-3776AB?logo=python&logoColor=white" />
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white" />
  <img src="https://img.shields.io/badge/License-Academic-green" />
</p>

---

## 📋 نظرة عامة

نظام ويب متكامل لإدارة بنك الدم يستخدم **QR Code** لتتبع أكياس الدم من لحظة التبرع حتى الاستخدام، مع نظام **ذكاء اصطناعي** للتنبؤ بالطلب على فصائل الدم ودعم القرار الطبي.

### ✨ المميزات الرئيسية

- 🔗 **تتبع كامل** لرحلة كيس الدم عبر QR Code
- 🤖 **نموذج ذكاء اصطناعي** للتنبؤ بالطلب على فصائل الدم
- 🔔 **تنبيهات ذكية** لانتهاء الصلاحية والمخزون المنخفض
- 📊 **لوحة تحكم تفاعلية** مع رسوم بيانية
- 👥 **نظام أدوار متعدد** (Admin, Doctor, Lab Technician, Storage Officer)
- 📱 **تصميم متجاوب** يعمل على جميع الأجهزة

---

## 🛠️ التقنيات المستخدمة

| الطبقة | التقنية |
|--------|---------|
| **Backend** | PHP 8.x |
| **Database** | MySQL 8.0 |
| **Frontend** | HTML5, CSS3, Bootstrap 5, JavaScript |
| **AI/ML** | Python 3.10, Flask, Scikit-learn |
| **QR Code** | QRCode.js, HTML5-QRCode |
| **Charts** | Chart.js |

---

## ⚙️ متطلبات التشغيل

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP)
- [Python 3.10+](https://www.python.org/downloads/)
- متصفح حديث (Chrome, Firefox, Edge)

---

## 🚀 خطوات التثبيت

### 1️⃣ إعداد XAMPP

```bash
# 1. حمل XAMPP من: https://www.apachefriends.org/
# 2. ثبته في المسار الافتراضي
# 3. شغل Apache و MySQL من XAMPP Control Panel
```

### 2️⃣ نسخ المشروع

انسخ مجلد `bloodbank` إلى `C:\xampp\htdocs\`

### 3️⃣ إعداد قاعدة البيانات

افتح المتصفح واذهب إلى:
```
http://localhost/bloodbank/setup.php
```

اضغط على زر "إعداد قاعدة البيانات" وسيتم إنشاء كل شيء تلقائياً.

### 4️⃣ تشغيل خدمة الذكاء الاصطناعي

```bash
cd C:\xampp\htdocs\bloodbank\ai_model
pip install -r requirements.txt
python train_model.py
python predict.py
```

### 5️⃣ فتح النظام

```
http://localhost/bloodbank/
```

---

## 🔑 بيانات الدخول الافتراضية

| الدور | البريد الإلكتروني | كلمة المرور |
|-------|------------------|------------|
| 👨‍💼 مدير النظام | `jbryl6302@gmail.com` | `password` |
| 👨‍⚕️ طبيب | `jbryl1891@gmail.com` | `password` |
| 🔬 فني المختبر | `jbra121@gmail.com` | `password` |
| 📦 أخصائي التخزين | `mlaz123@gmail.com` | `password` |

---

## 📂 هيكل المشروع

```
bloodbank/
│
├── setup.php              # إعداد قاعدة البيانات
├── index.php              # صفحة تسجيل الدخول
├── dashboard.php          # لوحة التحكم
│
├── config/
│   └── database.php       # إعدادات الاتصال
│
├── includes/
│   ├── header.php         # الترويسة المشتركة
│   ├── footer.php         # التذييل المشترك
│   └── functions.php      # الدوال المساعدة
│
├── pages/
│   ├── donors/            # إدارة المتبرعين
│   ├── bags/              # تتبع أكياس الدم + QR
│   ├── requests/          # طلبات الدم
│   ├── tests/             # الفحوصات المخبرية
│   ├── predictions/       # التنبؤات الذكية
│   └── reports/           # التقارير
│
├── api/
│   ├── generate_qr.php    # إنشاء QR Code
│   └── alerts.php         # API التنبيهات
│
└── ai_model/
    ├── train_model.py     # تدريب النموذج
    ├── predict.py         # خدمة التنبؤ
    └── requirements.txt   # متطلبات Python
```

---

## 🎯 وظائف النظام

### 📝 تسجيل التبرع
- إدخال بيانات المتبرع
- إنشاء QR Code فريد لكل كيس
- تسجيل تلقائي في سجل التتبع

### 🔍 مسح QR Code
- مسح بالكاميرا أو إدخال يدوي
- عرض تفاصيل الكيس كاملة
- عرض تاريخ الرحلة (Timeline)

### 🧪 الفحوصات المخبرية
- HIV, Hepatitis, Syphilis, Malaria
- الموافقة/الرفض التلقائي
- تحديث حالة الكيس

### 🩺 طلب الدم
- اختيار فصيلة المريض
- **توصيات ذكية** (FIFO + التوافق)
- تخصيص أكياس تلقائي

### 🤖 التنبؤ بالذكاء الاصطناعي
- نموذج Random Forest
- تنبؤ شهري باحتياجات فصائل الدم
- مقارنة المخزون الحالي بالمتوقع

---

## 📊 قاعدة البيانات
| الجدول | الوصف |
|--------|-------|
| `Donors` | بيانات المتبرعين |
| `DonorScreeningLog` | سجل فحص المتبرعين |
| `BloodBags` | أكياس الدم مع QR Code |
| `TestResults` | نتائج الفحوصات المخبرية |
| `BloodRequests` | طلبات الدم |
| `RequestAllocations` | تخصيص الأكياس للطلبات |
| `TrackingLog` | سجل تتبع الرحلة |
| `Transfers` | اقتراحات وعمليات النقل (الأولوية، الحالة، رمز التسليم QR، الموافقة) |
| `Predictions` | سجل التنبؤات |
| `Users` | المستخدمين والأدوار |
| `StorageUnits` | وحدات التخزين |
| `Facilities` | بنوك الدم والمستشفيات (الاسم، النوع، المدينة، المسافة بالكم، الهاتف) |
| `FacilityNeeds` | احتياج كل منشأة من كل فصيلة + درجة الاستعجال |
| `Hospitals` | المستشفيات |
---

## 🧠 نموذج الذكاء الاصطناعي

- **الخوارزمية:** Random Forest Regressor
- **الميزات:** فصيلة الدم، الشهر، السنة، عدد الطلبات التاريخي
- **المخرجات:** عدد الوحدات المتوقعة لكل فصيلة

---

## 🔒 الأمان

- تشفير كلمات المرور بـ **bcrypt**
- حماية الجلسات (Sessions)
- التحقق من الأدوار (Role-based Access)
- Prepared Statements ضد SQL Injection

---

## 🐛 حل المشاكل الشائعة

### ❌ خطأ: Table 'bloodbank_db.users' doesn't exist
```
الحل: افتح http://localhost/bloodbank/setup.php
```

### ❌ خطأ: AI Service Offline
```bash
cd ai_model
python predict.py
```

---

## 🚑 نظام الإنقاذ الذكي لفائض الدم (إضافة جديدة)

محرك ذكي لإعادة توزيع أكياس الدم بين بنوك الدم والمستشفيات:
اكتشاف الفائض ➜ تحديد الأكياس المعرضة للهدر ➜ ترتيب الجهات المحتاجة (استعجال + صلاحية + احتياج + مسافة)
➜ تنبيه المسؤول ➜ نقل موثق بالكامل عبر QR مع تسجيل في سجل التتبع.


---

## 👨‍🎓 معلومات المشروع

- **المؤلف:** [جبريل الصادق ؟ملاذ عبدالقادر؟عرفةعبد الله]
- **المؤسسة:** [جامعة الامام المهدي كلية علوم الحاسوب وتقنية المعلومات]
- **التخصص:** تقنية المعلومات
- **السنة:** 2026
- **نوع المشروع:** مشروع تخرج

---

## 📄 الترخيص

هذا المشروع هو مشروع تخرج** 
---

<p align="center">
  <strong>صُنع بـ ❤️ و ☕</strong><br>
  <em>لدعم حياة الآخرين</em>
  <em>كل الشكر والتقدير ل احبتي واستاذنا المشرف النيل محمد زين حسن </em>
</p>
