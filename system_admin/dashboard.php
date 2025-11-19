<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

// Only system admin can access this page
if ($_SESSION["role"] !== 'system_admin') {
    header("location: ../index.php");
    exit;
}

// Get counts for dashboard
$counts = [
    'total_admins' => 0,
    'total_students' => 0,
    'total_payments' => 0,
    'total_balance' => 0
];

// Get total admins
$sql = "SELECT COUNT(*) as count FROM users WHERE role IN ('system_admin', 'registrar', 'admin')";
$result = $conn->query($sql);
if ($result) {
    $counts['total_admins'] = $result->fetch_assoc()['count'];
}

// Get total students
$sql = "SELECT COUNT(*) as count FROM users WHERE role = 'students'";
$result = $conn->query($sql);
if ($result) {
    $counts['total_students'] = $result->fetch_assoc()['count'];
}

// Get total payments (simplified for demo)
$sql = "SELECT COUNT(*) as count FROM payments";
$result = $conn->query($sql);
if ($result) {
    $counts['total_payments'] = $result->fetch_assoc()['count'];
}

// Get total balance (simplified for demo)
$sql = "SELECT SUM(GREATEST(ps.amount_due - COALESCE(p.total_paid, 0), 0)) AS balance
        FROM payment_schedule ps
        LEFT JOIN (
            SELECT student_id, payment_category_id, SUM(amount_paid) AS total_paid
            FROM payments
            GROUP BY student_id, payment_category_id
        ) p ON p.student_id = ps.student_id AND p.payment_category_id = ps.payment_category_id
        WHERE ps.status != 'paid'";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $counts['total_balance'] = $row['balance'] ?? 0;
}

// Build recent activities (payments and new users)
$activities = [];
$sqlActivities = "
    SELECT 
        p.created_at AS event_time,
        'payment' AS type,
        'Payment Received' AS title,
        CONCAT('₱', FORMAT(p.amount_paid, 2), ' ', pc.name, ' from ', u.last_name, ', ', u.first_name) AS subtitle,
        'bi bi-credit-card' AS icon,
        'warning' AS color
    FROM payments p
    JOIN payment_categories pc ON pc.id = p.payment_category_id
    JOIN students s ON s.id = p.student_id
    JOIN users u ON u.id = s.user_id

    UNION ALL

    SELECT 
        u.created_at AS event_time,
        'admin' AS type,
        'New Admin Added' AS title,
        CONCAT(u.first_name, ' ', u.last_name, ' was added as ', REPLACE(u.role, '_', ' ')) AS subtitle,
        'bi bi-person-plus' AS icon,
        'primary' AS color
    FROM users u
    WHERE u.role IN ('system_admin', 'registrar', 'admin', 'school_admin')

    UNION ALL

    SELECT 
        u.created_at AS event_time,
        'student' AS type,
        'New Student Registered' AS title,
        CONCAT(u.first_name, ' ', u.last_name) AS subtitle,
        'bi bi-mortarboard' AS icon,
        'success' AS color
    FROM users u
    WHERE u.role = 'students'

    ORDER BY event_time DESC
    LIMIT 10
";
$resAct = $conn->query($sqlActivities);
if ($resAct) {
    while ($row = $resAct->fetch_assoc()) {
        $activities[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - System Admin | MARC Agape Christian Learning School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/MARC/image/marclogo.png">
</head>
<body class="topnav">
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navbar -->
            <?php include '../includes/navbar.php'; ?>

            <!-- Main Content -->
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Dashboard</h2>
                    <div class="text-muted">
                        <i class="bi bi-calendar"></i> <?php echo date('F j, Y'); ?>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0">Total Admins</h6>
                                        <h2 class="mb-0"><?php echo $counts['total_admins']; ?></h2>
                                    </div>
                                    <div class="icon-large">
                                        <i class="bi bi-people"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-primary bg-opacity-25 d-flex align-items-center justify-content-between">
                                <a class="small text-white stretched-link" href="manage_admins.php">View Details</a>
                                <div class="small text-white"><i class="bi bi-chevron-right"></i></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0">Total Students</h6>
                                        <h2 class="mb-0"><?php echo $counts['total_students']; ?></h2>
                                    </div>
                                    <div class="icon-large">
                                        <i class="bi bi-mortarboard"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-success bg-opacity-25 d-flex align-items-center justify-content-between">
                                <a class="small text-white stretched-link" href="manage_students.php">View Details</a>
                                <div class="small text-white"><i class="bi bi-chevron-right"></i></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <a href="/MARC/system_admin/payments.php" class="text-decoration-none">
                            <div class="card bg-warning text-dark h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-uppercase mb-0">Total Payments</h6>
                                            <h2 class="mb-0"><?php echo $counts['total_payments']; ?></h2>
                                        </div>
                                        <div class="icon-large">
                                            <i class="bi bi-credit-card"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-warning bg-opacity-25 d-flex align-items-center justify-content-between">
                                    <span class="small text-dark">View Details</span>
                                    <div class="small text-dark"><i class="bi bi-chevron-right"></i></div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3">
                        <a href="/MARC/system_admin/payments.php" class="text-decoration-none">
                            <div class="card bg-danger text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-uppercase mb-0">Total Balance</h6>
                                            <h2 class="mb-0">₱<?php echo number_format($counts['total_balance'], 2); ?></h2>
                                        </div>
                                        <div class="icon-large">
                                            <i class="bi bi-cash-coin"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-danger bg-opacity-25 d-flex align-items-center justify-content-between">
                                    <span class="small text-white">View Details</span>
                                    <div class="small text-white"><i class="bi bi-chevron-right"></i></div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Recent Activities</h6>
                                <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php if (count($activities) === 0): ?>
                                        <div class="list-group-item border-0 px-0">
                                            <div class="text-muted small">No recent activities.</div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($activities as $act): ?>
                                            <?php 
                                                $bg = htmlspecialchars($act['color']);
                                                $txt = ($bg === 'warning') ? 'dark' : 'white';
                                            ?>
                                            <div class="list-group-item border-0 px-0">
                                                <div class="d-flex">
                                                    <div class="icon-sm bg-<?php echo $bg; ?> text-<?php echo $txt; ?> rounded-3 me-3">
                                                        <i class="<?php echo htmlspecialchars($act['icon']); ?>"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($act['title']); ?></h6>
                                                            <small class="text-muted"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($act['event_time']))); ?></small>
                                                        </div>
                                                        <p class="mb-0 small text-muted"><?php echo htmlspecialchars($act['subtitle']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="manage_admins.php?action=add" class="btn btn-outline-primary text-start">
                                        <i class="bi bi-person-plus me-2"></i> Add New Admin
                                    </a>
                                    <a href="manage_students.php?action=add" class="btn btn-outline-success text-start">
                                        <i class="bi bi-person-plus me-2"></i> Add New Student
                                    </a>
                                    <a href="/MARC/system_admin/payments.php" class="btn btn-outline-warning text-start">
                                        <i class="bi bi-credit-card me-2"></i> Record Payment
                                    </a>
                                    <a href="reports.php" class="btn btn-outline-info text-start">
                                        <i class="bi bi-graph-up me-2"></i> Generate Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>
