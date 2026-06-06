<?php
require_once '../config/config.php';
require_once '../includes/Auth.php';
require_once '../includes/Database.php';
require_once '../includes/Functions.php';

checkEmployeeRole();

$db = new Database();

// Get employee info
$employee = $db->getRow("SELECT * FROM employees WHERE user_id = " . $_SESSION['user_id']);

// Get attendance for today
$today = date('Y-m-d');

$query = "
    SELECT 
        a.*, 
        m.full_name as member_name,
        e.full_name as employee_name
    FROM attendance a
    LEFT JOIN members m ON a.member_id = m.member_id
    LEFT JOIN employees e ON a.employee_id = e.employee_id
    WHERE a.attendance_date = ?
    ORDER BY a.check_in_time DESC
";

$stmt = $db->query($query);
$stmt->bind('s', $today);
$stmt->execute();
$today_attendance = $stmt->getResult()->fetch_all(MYSQLI_ASSOC);

// Get stats
$stats = array(
    'today_checkins' => count($today_attendance),
    'active_members' => $db->getRow("SELECT COUNT(*) as count FROM members WHERE subscription_status = 'active'")['count'],
    'today_revenue' => getTotalRevenue($db, $today, $today)
);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الموظف - <?php echo SYSTEM_NAME; ?></title>
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
                <span class="me-3">مرحباً، <?php echo $_SESSION['full_name']; ?> (موظف)</span>
                <a href="../logout.php" class="btn btn-sm btn-danger">خروج</a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <!-- Stats -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['today_checkins']; ?></h4>
                        <p class="text-muted">دخول اليوم</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['active_members']; ?></h4>
                        <p class="text-muted">أعضاء نشطين</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h4><?php echo formatCurrency($stats['today_revenue']); ?></h4>
                        <p class="text-muted">مبيعات اليوم</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Today's Attendance -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">دخول اليوم - <?php echo formatDateAr($today); ?></h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الاسم</th>
                                    <th>النوع</th>
                                    <th>وقت الدخول</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($today_attendance)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">لا توجد دخول حتى الآن</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $count = 1; ?>
                                    <?php foreach ($today_attendance as $record): ?>
                                        <tr>
                                            <td><?php echo $count++; ?></td>
                                            <td>
                                                <?php 
                                                if ($record['user_type'] == 'member') {
                                                    echo $record['member_name'];
                                                } else {
                                                    echo $record['employee_name'];
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $record['user_type'] == 'member' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo $record['user_type'] == 'member' ? 'عضو' : 'موظف'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('H:i:s', strtotime($record['check_in_time'])); ?></td>
                                            <td>
                                                <span class="badge bg-success">
                                                    حاضر
                                                </span>
                                            </td>
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
    
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
