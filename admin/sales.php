<?php
require_once '../config/config.php';
require_once '../includes/Auth.php';
require_once '../includes/Database.php';
require_once '../includes/Functions.php';

checkAdminRole();

$db = new Database();
$error = '';
$success = '';

// Handle sales
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_sale') {
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        $payment_method = sanitize($_POST['payment_method'] ?? 'cash');
        $member_id = !empty($_POST['member_id']) ? intval($_POST['member_id']) : null;
        
        // Get item details
        $item = $db->getRow("SELECT * FROM cafeteria_items WHERE item_id = $item_id");
        
        if ($item && $item['quantity_available'] >= $quantity) {
            $total_price = $item['price'] * $quantity;
            
            // Insert sale
            $query = "
                INSERT INTO sales (item_id, member_id, quantity_sold, unit_price, total_price, payment_method, sold_by_employee_id, sale_date, sale_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $db->query($query);
            $stmt->bind('i', $item_id);
            $stmt->bind('i', $member_id);
            $stmt->bind('i', $quantity);
            $stmt->bind('d', $item['price']);
            $stmt->bind('d', $total_price);
            $stmt->bind('s', $payment_method);
            
            $user_id = $_SESSION['user_id'];
            $stmt->bind('i', $user_id);
            $stmt->bind('s', date('Y-m-d'));
            $stmt->bind('s', date('H:i:s'));
            
            if ($stmt->execute()) {
                // Update inventory
                $new_quantity = $item['quantity_available'] - $quantity;
                $new_sold = ($item['quantity_sold'] ?? 0) + $quantity;
                
                $update_query = "UPDATE cafeteria_items SET quantity_available = ?, quantity_sold = ? WHERE item_id = ?";
                $update_stmt = $db->query($update_query);
                $update_stmt->bind('i', $new_quantity);
                $update_stmt->bind('i', $new_sold);
                $update_stmt->bind('i', $item_id);
                $update_stmt->execute();
                
                $success = 'تم تسجيل البيع بنجاح';
            } else {
                $error = 'حدث خطأ أثناء تسجيل البيع';
            }
        } else {
            $error = 'المخزون غير كافي';
        }
    }
}

// Get sales with date filter
$date_filter = sanitize($_GET['date'] ?? date('Y-m-d'));

$query = "
    SELECT 
        s.*, 
        ci.item_name_ar, 
        m.full_name as member_name,
        e.full_name as employee_name
    FROM sales s
    JOIN cafeteria_items ci ON s.item_id = ci.item_id
    LEFT JOIN members m ON s.member_id = m.member_id
    LEFT JOIN employees e ON s.sold_by_employee_id = e.employee_id
    WHERE s.sale_date = ?
    ORDER BY s.sale_time DESC
";

$stmt = $db->query($query);
$stmt->bind('s', $date_filter);
$stmt->execute();
$sales = $stmt->getResult()->fetch_all(MYSQLI_ASSOC);

// Get daily total
$daily_total = $db->getRow("
    SELECT SUM(total_price) as total FROM sales WHERE sale_date = ?
");
$daily_total_amount = $daily_total && $daily_total['total'] ? $daily_total['total'] : 0;

// Get items
$items = $db->getRows("SELECT * FROM cafeteria_items WHERE is_available = 1 ORDER BY item_name_ar");

// Get members
$members = $db->getRows("SELECT member_id, full_name FROM members WHERE subscription_status = 'active' ORDER BY full_name");
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المبيعات - <?php echo SYSTEM_NAME; ?></title>
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
                <a href="members.php"><i class="fa fa-users"></i> الأعضاء</a>
                <a href="employees.php"><i class="fa fa-user-tie"></i> الموظفين</a>
                <a href="trainers.php"><i class="fa fa-dumbbell"></i> المدربين</a>
                <a href="attendance.php"><i class="fa fa-calendar"></i> الحضور</a>
                <a href="cafeteria.php"><i class="fa fa-coffee"></i> الكافتيريا</a>
                <a href="sales.php" class="active"><i class="fa fa-shopping-cart"></i> المبيعات</a>
                <a href="reports.php"><i class="fa fa-bar-chart"></i> التقارير</a>
                <a href="settings.php"><i class="fa fa-cog"></i> الإعدادات</a>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>💰 تسجيل المبيعات</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSaleModal">
                        <i class="fa fa-plus"></i> عملية بيع جديدة
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
                
                <!-- Daily Stats -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5><?php echo count($sales); ?></h5>
                                <p class="text-muted">عدد العمليات</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5><?php echo formatCurrency($daily_total_amount); ?></h5>
                                <p class="text-muted">إجمالي اليوم</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Date Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-success w-100">عرض المبيعات</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Sales Table -->
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المنتج</th>
                                    <th>الكمية</th>
                                    <th>السعر الوحدة</th>
                                    <th>الإجمالي</th>
                                    <th>العضو</th>
                                    <th>الوقت</th>
                                    <th>طريقة الدفع</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sales)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">لا توجد مبيعات</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $count = 1; ?>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><?php echo $count++; ?></td>
                                            <td><?php echo $sale['item_name_ar']; ?></td>
                                            <td><?php echo $sale['quantity_sold']; ?></td>
                                            <td><?php echo formatCurrency($sale['unit_price']); ?></td>
                                            <td><?php echo formatCurrency($sale['total_price']); ?></td>
                                            <td><?php echo $sale['member_name'] ?? '-'; ?></td>
                                            <td><?php echo $sale['sale_time']; ?></td>
                                            <td><?php echo $sale['payment_method']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Sale Modal -->
    <div class="modal fade" id="addSaleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تسجيل عملية بيع</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_sale">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المنتج *</label>
                                <select name="item_id" class="form-control" required>
                                    <option value="">-- اختر منتج --</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['item_id']; ?>">
                                            <?php echo $item['item_name_ar'] . ' (' . $item['quantity_available'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الكمية *</label>
                                <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">العضو (اختياري)</label>
                                <select name="member_id" class="form-control">
                                    <option value="">-- لا يوجد --</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['member_id']; ?>">
                                            <?php echo $member['full_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">طريقة الدفع</label>
                                <select name="payment_method" class="form-control" required>
                                    <option value="cash">نقد</option>
                                    <option value="card">بطاقة</option>
                                    <option value="wallet">محفظة</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">تسجيل البيع</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
