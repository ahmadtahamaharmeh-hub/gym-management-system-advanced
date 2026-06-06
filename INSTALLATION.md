# Gym Management System - Installation Guide

## الدليل الكامل للتثبيت والتشغيل

### المتطلبات الأساسية

قبل البدء، تأكد من توفر الآتي:

1. **خادم ويب:** Apache أو Nginx
2. **PHP:** الإصدار 7.4 أو أحدث
   ```bash
   php -v
   ```
3. **MySQL:** الإصدار 5.7 أو أحدث
   ```bash
   mysql --version
   ```
4. **Composer:** (اختياري - لتثبيت المكتبات)

---

## خطوات التثبيت

### 1️⃣ استنساخ المستودع

```bash
# إذا كان لديك Git
git clone https://github.com/ahmadtahamaharmeh-hub/gym-management-system-advanced.git
cd gym-management-system-advanced

# أو قم بتحميل الملفات يدوياً من GitHub
```

### 2️⃣ إنشاء قاعدة البيانات

#### على Windows (XAMPP):
```bash
cd xampp\mysql\bin
mysql -u root -p
```

#### على Linux/Mac:
```bash
mysql -u root -p
```

#### الأوامر:
```sql
CREATE DATABASE gym_management;
USE gym_management;
SOURCE /path/to/database/gym.sql;
```

أو استيراد الملف من واجهة phpMyAdmin.

### 3️⃣ تكوين الملفات

#### تعديل `config/config.php`:
```php
// تفاصيل قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // أضف كلمة المرور إذا كانت موجودة
define('DB_NAME', 'gym_management');

// عنوان IP لجهاز ZKTeco (اختياري)
define('ZKTECO_IP', '192.168.1.201');
define('ZKTECO_PORT', 23);
```

### 4️⃣ إنشاء مجاعات الملفات

تأكد من وجود المجلدات التالية:
```bash
mkdir -p uploads/training_media
mkdir -p uploads/cafeteria
mkdir -p uploads/profiles
mkdir -p logs

# تعيين الصلاحيات (Linux/Mac)
chmod -R 755 uploads
chmod -R 755 logs
```

### 5️⃣ رفع الملفات على الخادم

#### على Hostinger (FTP):
1. استخدم FileZilla أو Cpanel File Manager
2. انسخ جميع الملفات إلى المجلد `public_html`
3. تأكد من حفظ هيكل المجلدات

#### على XAMPP (محلي):
```bash
xcopy /E /I gym-management-system-advanced C:\xampp\htdocs\gym
```

### 6️⃣ الوصول للنظام

افتح المتصفح وادخل:

**للاختبار المحلي:**
```
http://localhost/gym-management-system-advanced/login.php
```

**على خادم مستضاف:**
```
https://yourdomain.com/gym-management-system-advanced/login.php
```

---

## 🔐 بيانات الدخول الافتراضية

عند تثبيت النظام لأول مرة، استخدم:

| الدور | المستخدم | كلمة المرور | الوصف |
|------|---------|----------|-------|
| مسؤول النظام | admin | admin@123 | إدارة كاملة للنظام |
| موظف استقبال | employee | emp123 | إدارة الحضور والاشتراكات |
| مدرب | trainer | train123 | إنشاء وإدارة الخطط |
| عضو | member | mem123 | عرض خطط التدريب |

⚠️ **مهم:** غير كلمات المرور الافتراضية فوراً بعد التثبيت!

---

## ⚙️ الإعدادات المتقدمة

### ربط جهاز ZKTeco

#### تثبيت المكتبة:
```bash
composer require jmrashed/zkteco
```

#### الإعدادات:
1. اذهب إلى `config/config.php`
2. عدّل بيانات الاتصال:
```php
define('ZKTECO_IP', '192.168.1.201');    // عنوان IP للجهاز
define('ZKTECO_PORT', 23);               // المنفذ (عادة 23)
define('ZKTECO_TIMEOUT', 3000);          // مهلة الاتصال
```

#### اختبار الاتصال:
استخدم صفحة الإعدادات في لوحة التحكم للتحقق من الاتصال.

### تفعيل SSL/HTTPS

للأمان الإضافي على خادم مستضاف:

```php
// في config/config.php
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
```

### تكوين البريد الإلكتروني

للإشعارات التلقائية (اختياري):

```php
// في config/config.php
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@gmail.com');
define('MAIL_PASSWORD', 'your-password');
```

---

## 🐛 استكشاف الأخطاء

### خطأ: "خطأ في الاتصال بقاعدة البيانات"

**الحل:**
1. تحقق من معلومات قاعدة البيانات في `config/config.php`
2. تأكد من تشغيل خدمة MySQL
3. تأكد من أن كلمة المرور صحيحة

### خطأ: "Permission Denied"

**الحل:**
```bash
# Linux/Mac
chmod -R 755 uploads
chmod -R 755 logs
```

### خطأ: "صفحة بيضاء فارغة"

**الحل:**
1. افحص ملف السجل: `logs/error.log`
2. تحقق من رسائل الخطأ في PHP:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

### جهاز ZKTeco غير متصل

**الحل:**
1. تحقق من عنوان IP: `ping 192.168.1.201`
2. تأكد من أن المنفذ 23 مفتوح
3. جرّب إعادة تشغيل الجهاز

---

## 📊 نسخ احتياطي واستعادة

### إنشاء نسخة احتياطية

```bash
# MySQL backup
mysqldump -u root -p gym_management > backup.sql

# Backup الملفات
zip -r backup.zip uploads config
```

### استعادة النسخة

```bash
# استعادة قاعدة البيانات
mysql -u root -p gym_management < backup.sql

# استعادة الملفات
unzip backup.zip
```

---

## 🚀 نصائح الأداء

1. **تحسين الصور:** استخدم صور مضغوطة
2. **الفهارسة:** تأكد من وجود فهارس قاعدة البيانات
3. **التخزين المؤقت:** فعّل التخزين المؤقت في المتصفح

---

## 📞 الدعم الفني

إذا واجهت مشاكل:
1. اقرأ الأسئلة الشائعة
2. ابحث في GitHub Issues
3. تواصل مع الدعم الفني

---

**آخر تحديث:** 2026-06-06
