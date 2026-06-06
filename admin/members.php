<?php
require_once '../config/config.php';
require_once '../includes/Auth.php';
require_once '../includes/Database.php';
require_once '../includes/Functions.php';

checkAdminRole();

$db = new Database();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_member') {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $full_name_ar = sanitize($_POST['full_name_ar'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $membership_plan_id = intval($_POST['membership_plan_id'] ?? 0);
        $subscription_fee = floatval($_POST['subscription_fee'] ?? 0);
        $subscription_start = $_POST['subscription_start'] ?? date('Y-m-d');
        
        // Calculate end date based on plan
        $query = "SELECT duration_months FROM membership_plans WHERE plan_id = ?";
        $stmt = $db->query($query);
        $stmt->bind('i', $membership_plan_id);
        $stmt->execute();
        $result = $stmt->getResult()->fetch_assoc();
        
        if ($result) {
            $start = new DateTime($subscription_start);
            $start->add(new DateInterval('P' . $result['duration_months'] . 'M'));
            $subscription_end = $start->format('Y-m-d');
        } else {
            $subscription_end = date('Y-m-d', strtotime('+1 month'));
        }
        
        // Insert member
        $query = "INSERT INTO members (full_name, full_name_ar, phone, email, membership_plan_id, subscription_start_date, subscription_end_date, membership_fee, subscription_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
        
        $stmt = $db->query($query);
        $stmt->bind('s', $full_name);
        $stmt->bind('s', $full_name_ar);
        $stmt->bind('s', $phone);
        $stmt->bind('s', $email);
        $stmt->bind('i', $membership_plan_id);
        $stmt->bind('s', $subscription_start);
        $stmt->bind('s', $subscription_end);
        $stmt->bind('d', $subscription_fee);
        
        if ($stmt->execute()) {
            $success = 'تم إضافة العضو بنجاح';
        } else {
            $error = 'حدث خطأ أثناء إضافة العضو';
        }
    }
}

// Get members with filters
$page = intval($_GET['page'] ?? 1);
$search = sanitize($_GET['search'] ?? '');
$status_filter = sanitize($_GET['status'] ?? '');

$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = "WHERE 1=1";
$params = array();
$types = "";

if (!empty($search)) {
    $where .= " AND (m.full_name LIKE ? OR m.phone LIKE ? OR m.email LIKE ?)";
    $search_like = "%$search%";
    $params = array($search_like, $search_like, $search_like);
    $types = "sss";
}

if (!empty($status_filter)) {
    $where .= " AND m.subscription_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get total count
$count_query = "SELECT COUNT(*) as count FROM members m $where";
$total = $db->getRow($count_query);
$total_members = $total ? $total['count'] : 0;
$total_pages = ceil($total_members / ITEMS_PER_PAGE);

// Get members
$query = "
    SELECT m.*, mp.plan_name
    FROM members m
    LEFT JOIN membership_plans mp ON m.membership_plan_id = mp.plan_id
    $where
    ORDER BY m.created_at DESC
    LIMIT ? OFFSET ?
";

// Note: For simplicity, we're using getRows which doesn't support parameterized limit/offset
// In production, use prepared statements properly
$members = $db->getRows("
    SELECT m.*, mp.plan_name
    FROM members m
    LEFT JOIN membership_plans mp ON m.membership_plan_id = mp.plan_id
    $where
    ORDER BY m.created_at DESC
");

// Get membership plans
$plans = $db->getRows("SELECT * FROM membership_plans WHERE is_active = 1");
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأعضاء - <?php echo SYSTEM_NAME; ?></title>
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
                <span class="me-3">مرحباً، <?php echo $_SESSION['full_name']; ?></span>
                <a href="../logout.php" class="btn btn-sm btn-danger">خروج</a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <a href="dashboard.php"><i class="fa fa-dashboard"></i> لوحة التحكم</a>
                <a href="members.php" class="active"><i class="fa fa-users"></i> الأعضاء</a>
                <a href="employees.php"><i class="fa fa-user-tie"></i> الموظفين</a>
                <a href="trainers.php"><i class="fa fa-dumbbell"></i> المدربين</a>
                <a href="attendance.php"><i class="fa fa-calendar"></i> الحضور</a>
                <a href="cafeteria.php"><i class="fa fa-coffee"></i> الكافتيريا</a>
                <a href="sales.php"><i class="fa fa-shopping-cart"></i> المبيعات</a>
                <a href="reports.php"><i class="fa fa-bar-chart"></i> التقارير</a>
                <a href="settings.php"><i class="fa fa-cog"></i> الإعدادات</a>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>إدارة الأعضاء</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                        <i class="fa fa-plus"></i> إضافة عضو جديد
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
                
                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="بحث عن اسم أو هاتف أو بريد" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-4">
                                <select name="status" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>نشط</option>
                                    <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>منتهي</option>
                                    <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>موقوف</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-success w-100">بحث</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Members Table -->
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الاسم</th>
                                    <th>الهاتف</th>
                                    <th>البريد الإلكتروني</th>
                                    <th>الخطة</th>
                                    <th>المدفوع</th>
                                    <th>انتهاء الاشتراك</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $count = ($page - 1) * ITEMS_PER_PAGE + 1; ?>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?php echo $count++; ?></td>
                                        <td><?php echo $member['full_name']; ?></td>
                                        <td><?php echo $member['phone']; ?></td>
                                        <td><?php echo $member['email'] ?? '-'; ?></td>
                                        <td><?php echo $member['plan_name'] ?? '-'; ?></td>
                                        <td><?php echo formatCurrency($member['membership_fee']); ?></td>
                                        <td><?php echo formatDateAr($member['subscription_end_date']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $member['subscription_status'] == 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo getMemberStatusAr($member['subscription_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info"><i class="fa fa-eye"></i></button>
                                            <button class="btn btn-sm btn-warning"><i class="fa fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1">الأولى</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>">الأخيرة</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Member Modal -->
    <div class="modal fade" id="addMemberModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة عضو جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_member">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الاسم (العربية)</label>
                                <input type="text" name="full_name_ar" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الاسم (الإنجليزية)</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رقم الهاتف</label>
                                <input type="tel" name="phone" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">البريد الإلكتروني</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">خطة الاشتراك</label>
                                <select name="membership_plan_id" class="form-control" required>
                                    <option value="">-- اختر خطة --</option>
                                    <?php foreach ($plans as $plan): ?>
                                        <option value="<?php echo $plan['plan_id']; ?>">
                                            <?php echo $plan['plan_name_ar'] . ' - ' . formatCurrency($plan['price']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المبلغ المدفوع</label>
                                <input type="number" name="subscription_fee" class="form-control" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ البداية</label>
                                <input type="date" name="subscription_start" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ العضو</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
