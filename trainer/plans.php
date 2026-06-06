<?php
require_once '../config/config.php';
require_once '../includes/Auth.php';
require_once '../includes/Database.php';
require_once '../includes/Functions.php';

checkTrainerRole();

$db = new Database();
$error = '';
$success = '';

// Get trainer info
$trainer = $db->getRow("SELECT * FROM trainers WHERE user_id = " . $_SESSION['user_id']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create_plan') {
        $title = sanitize($_POST['title'] ?? '');
        $title_ar = sanitize($_POST['title_ar'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $description_ar = sanitize($_POST['description_ar'] ?? '');
        $target_audience = sanitize($_POST['target_audience'] ?? '');
        $target_audience_ar = sanitize($_POST['target_audience_ar'] ?? '');
        $difficulty = sanitize($_POST['difficulty'] ?? 'beginner');
        $duration = intval($_POST['duration'] ?? 4);
        
        $trainer_id = $trainer['trainer_id'];
        
        $query = "
            INSERT INTO training_plans 
            (trainer_id, title, title_ar, description, description_ar, target_audience, target_audience_ar, difficulty_level, duration_weeks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $db->query($query);
        $stmt->bind('i', $trainer_id);
        $stmt->bind('s', $title);
        $stmt->bind('s', $title_ar);
        $stmt->bind('s', $description);
        $stmt->bind('s', $description_ar);
        $stmt->bind('s', $target_audience);
        $stmt->bind('s', $target_audience_ar);
        $stmt->bind('s', $difficulty);
        $stmt->bind('i', $duration);
        
        if ($stmt->execute()) {
            $success = 'تم إنشاء الخطة بنجاح';
        } else {
            $error = 'حدث خطأ أثناء إنشاء الخطة';
        }
    }
}

// Get trainer's plans
$plans = $db->getRows("
    SELECT * FROM training_plans 
    WHERE trainer_id = " . ($trainer['trainer_id'] ?? 0) . "
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطط التدريب - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">💪 <?php echo SYSTEM_NAME; ?></a>
            <div class="ms-auto">
                <span class="me-3">مرحباً، <?php echo $_SESSION['full_name']; ?> (مدرب)</span>
                <a href="../logout.php" class="btn btn-sm btn-danger">خروج</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>📋 جداول التدريب الخاصة بك</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPlanModal">
                <i class="fa fa-plus"></i> إنشاء جدول جديد
            </button>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Training Plans Grid -->
        <div class="row">
            <?php if (empty($plans)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        لا توجد جداول تدريب حالياً. قم بإنشاء جدول جديد!
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($plans as $plan): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $plan['title_ar']; ?></h5>
                                <p class="card-text"><?php echo substr($plan['description_ar'], 0, 100); ?>...</p>
                                <div class="mb-2">
                                    <span class="badge bg-info">
                                        <?php 
                                        $difficulty_ar = array('beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم');
                                        echo $difficulty_ar[$plan['difficulty_level']] ?? $plan['difficulty_level'];
                                        ?>
                                    </span>
                                    <span class="badge bg-secondary"><?php echo $plan['duration_weeks']; ?> أسابيع</span>
                                </div>
                                <div class="btn-group w-100" role="group">
                                    <a href="plan-details.php?id=<?php echo $plan['plan_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fa fa-eye"></i> عرض
                                    </a>
                                    <button class="btn btn-sm btn-warning"><i class="fa fa-edit"></i> تعديل</button>
                                    <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i> حذف</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create Plan Modal -->
    <div class="modal fade" id="createPlanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إنشاء جدول تدريب جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_plan">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">عنوان الخطة (عربي) *</label>
                                <input type="text" name="title_ar" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">عنوان الخطة (إنجليزي) *</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">الوصف (عربي) *</label>
                                <textarea name="description_ar" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">الوصف (إنجليزي) *</label>
                                <textarea name="description" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الفئة المستهدفة (عربي)</label>
                                <input type="text" name="target_audience_ar" class="form-control" placeholder="مثال: المبتدئين">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الفئة المستهدفة (إنجليزي)</label>
                                <input type="text" name="target_audience" class="form-control" placeholder="مثال: Beginners">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">مستوى الصعوبة</label>
                                <select name="difficulty" class="form-control">
                                    <option value="beginner">مبتدئ</option>
                                    <option value="intermediate">متوسط</option>
                                    <option value="advanced">متقدم</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المدة (بالأسابيع)</label>
                                <input type="number" name="duration" class="form-control" value="4" min="1">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إنشاء الخطة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
