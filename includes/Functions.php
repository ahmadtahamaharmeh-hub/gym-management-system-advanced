<?php
/**
 * Common Functions
 */

require_once dirname(__FILE__) . '/../config/config.php';
require_once dirname(__FILE__) . '/Database.php';

/**
 * Check if user is authorized
 */
function checkAuth() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Check admin role
 */
function checkAdminRole() {
    checkAuth();
    
    if ($_SESSION['role'] !== 'admin') {
        header('HTTP/1.0 403 Forbidden');
        die('غير مصرح لك بالدخول إلى هذه الصفحة');
    }
}

/**
 * Check employee role
 */
function checkEmployeeRole() {
    checkAuth();
    
    if ($_SESSION['role'] !== 'employee' && $_SESSION['role'] !== 'admin') {
        header('HTTP/1.0 403 Forbidden');
        die('غير مصرح لك بالدخول إلى هذه الصفحة');
    }
}

/**
 * Check trainer role
 */
function checkTrainerRole() {
    checkAuth();
    
    if ($_SESSION['role'] !== 'trainer' && $_SESSION['role'] !== 'admin') {
        header('HTTP/1.0 403 Forbidden');
        die('غير مصرح لك بالدخول إلى هذه الصفحة');
    }
}

/**
 * Check member role
 */
function checkMemberRole() {
    checkAuth();
    
    if ($_SESSION['role'] !== 'member' && $_SESSION['role'] !== 'admin') {
        header('HTTP/1.0 403 Forbidden');
        die('غير مصرح لك بالدخول إلى هذه الصفحة');
    }
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format date in Arabic
 */
function formatDateAr($date) {
    $months = array(
        '01' => 'يناير',
        '02' => 'فبراير',
        '03' => 'مارس',
        '04' => 'أبريل',
        '05' => 'مايو',
        '06' => 'يونيو',
        '07' => 'يوليو',
        '08' => 'أغسطس',
        '09' => 'سبتمبر',
        '10' => 'أكتوبر',
        '11' => 'نوفمبر',
        '12' => 'ديسمبر'
    );
    
    $parts = explode('-', $date);
    if (count($parts) == 3) {
        return $parts[2] . ' ' . $months[$parts[1]] . ' ' . $parts[0];
    }
    
    return $date;
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return number_format($amount, 2, '.', ',') . ' د.أ';
}

/**
 * Get member status in Arabic
 */
function getMemberStatusAr($status) {
    $statuses = array(
        'active' => 'نشط',
        'expired' => 'منتهي الاشتراك',
        'suspended' => 'موقوف'
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

/**
 * Get days until subscription expires
 */
function getDaysUntilExpiry($endDate) {
    $today = new DateTime();
    $expiry = new DateTime($endDate);
    $interval = $today->diff($expiry);
    
    return $interval->days;
}

/**
 * Create upload directory if not exists
 */
function createUploadDir() {
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    if (!is_dir(UPLOAD_TRAINING_MEDIA)) {
        mkdir(UPLOAD_TRAINING_MEDIA, 0755, true);
    }
    
    if (!is_dir(UPLOAD_CAFETERIA)) {
        mkdir(UPLOAD_CAFETERIA, 0755, true);
    }
    
    if (!is_dir(UPLOAD_PROFILES)) {
        mkdir(UPLOAD_PROFILES, 0755, true);
    }
}

/**
 * Upload file
 */
function uploadFile($file, $destination, $maxSize = MAX_UPLOAD_SIZE) {
    // Check file size
    if ($file['size'] > $maxSize) {
        return array('success' => false, 'message' => 'حجم الملف كبير جداً');
    }
    
    // Check file type
    $allowed = array('jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov');
    $filename = basename($file['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return array('success' => false, 'message' => 'نوع الملف غير مدعوم');
    }
    
    // Generate unique filename
    $newFilename = time() . '_' . uniqid() . '.' . $ext;
    $uploadPath = $destination . $newFilename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return array('success' => true, 'filename' => $newFilename, 'path' => $uploadPath);
    }
    
    return array('success' => false, 'message' => 'فشل تحميل الملف');
}

/**
 * Delete file
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        unlink($filePath);
        return true;
    }
    
    return false;
}

/**
 * Get current member count
 */
function getMemberCount($db) {
    $query = "SELECT COUNT(*) as count FROM members WHERE subscription_status = 'active'";
    $result = $db->getRow($query);
    return $result ? $result['count'] : 0;
}

/**
 * Get today's attendance count
 */
function getTodayAttendanceCount($db) {
    $today = date('Y-m-d');
    $query = "SELECT COUNT(DISTINCT user_id) as count FROM attendance WHERE attendance_date = ? AND user_type = 'member'";
    $stmt = $db->query($query);
    $stmt->bind('s', $today);
    $stmt->execute();
    $result = $stmt->getResult()->fetch_assoc();
    return $result ? $result['count'] : 0;
}

/**
 * Get total revenue
 */
function getTotalRevenue($db, $from_date = null, $to_date = null) {
    $query = "SELECT SUM(total_price) as total FROM sales";
    
    if ($from_date && $to_date) {
        $query .= " WHERE sale_date BETWEEN ? AND ?";
        $stmt = $db->query($query);
        $stmt->bind('s', $from_date);
        $stmt->bind('s', $to_date);
    } else {
        $stmt = $db->query($query);
    }
    
    $stmt->execute();
    $result = $stmt->getResult()->fetch_assoc();
    return $result ? $result['total'] : 0;
}

/**
 * Check if subscription is expiring soon
 */
function isSubscriptionExpiringSoon($endDate, $days = 7) {
    $today = new DateTime();
    $expiry = new DateTime($endDate);
    $interval = $today->diff($expiry);
    
    return $interval->days <= $days && $interval->days >= 0;
}

/**
 * Generate membership report
 */
function generateMembershipReport($db) {
    $query = "
        SELECT 
            mp.plan_name,
            COUNT(m.member_id) as count,
            SUM(m.membership_fee) as total_revenue
        FROM members m
        LEFT JOIN membership_plans mp ON m.membership_plan_id = mp.plan_id
        GROUP BY m.membership_plan_id
    ";
    
    return $db->getRows($query);
}

/**
 * Log action
 */
function logAction($db, $action, $details = '') {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $query = "
        INSERT INTO action_logs (user_id, action, details, created_at)
        VALUES (?, ?, ?, NOW())
    ";
    
    $stmt = $db->query($query);
    $stmt->bind('i', $user_id);
    $stmt->bind('s', $action);
    $stmt->bind('s', $details);
    
    return $stmt->execute();
}

/**
 * Send notification
 */
function sendNotification($title, $message, $type = 'info') {
    $_SESSION['notification'] = array(
        'title' => $title,
        'message' => $message,
        'type' => $type // success, error, warning, info
    );
}

/**
 * Get notification
 */
function getNotification() {
    if (isset($_SESSION['notification'])) {
        $notification = $_SESSION['notification'];
        unset($_SESSION['notification']);
        return $notification;
    }
    
    return null;
}
