<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

// Only registrar or system admin can access this page
if (!in_array($_SESSION["role"] ?? '', ['registrar', 'system_admin', 'admin', 'school_admin'])) {
    header("location: ../index.php");
    exit;
}

$counts = [
    'total_students' => 0,
    'total_payments' => 0,
    'total_balance' => 0.0,
];

// Total students
if ($res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'students'")) {
    $counts['total_students'] = (int)$res->fetch_assoc()['c'];
}
// Total payments
if ($res = $conn->query("SELECT COUNT(*) AS c FROM payments")) {
    $counts['total_payments'] = (int)$res->fetch_assoc()['c'];
}
// Total balance (use aggregated paid per student+category)
$sqlBalance = "SELECT SUM(GREATEST(ps.amount_due - COALESCE(p.total_paid,0), 0)) AS balance
               FROM payment_schedule ps
               LEFT JOIN (
                 SELECT student_id, payment_category_id, SUM(amount_paid) AS total_paid
                 FROM payments
                 GROUP BY student_id, payment_category_id
               ) p ON p.student_id = ps.student_id AND p.payment_category_id = ps.payment_category_id
               WHERE ps.status != 'paid'";
if ($res = $conn->query($sqlBalance)) {
    $counts['total_balance'] = (float)($res->fetch_assoc()['balance'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Registrar | MARC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../system_admin/assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/MARC/image/marclogo.png">
</head>
<body class="topnav">
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div id="content">
        <?php include '../includes/navbar.php'; ?>
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Dashboard</h2>
                <div class="text-muted">
                    <i class="bi bi-calendar"></i> <?php echo date('F j, Y'); ?>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1">Total Students</h6>
                                <div class="h2 mb-0"><?php echo $counts['total_students']; ?></div>
                            </div>
                            <i class="bi bi-mortarboard fs-1"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <a href="payments.php" class="text-decoration-none">
                        <div class="card bg-warning text-dark h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total Payments</h6>
                                    <div class="h2 mb-0"><?php echo $counts['total_payments']; ?></div>
                                </div>
                                <i class="bi bi-credit-card fs-1"></i>
                            </div>
                            <div class="card-footer bg-warning bg-opacity-25 d-flex align-items-center justify-content-between">
                                <span class="small text-dark">Manage Payments</span>
                                <div class="small text-dark"><i class="bi bi-chevron-right"></i></div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="payments.php" class="text-decoration-none">
                        <div class="card bg-danger text-white h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total Balance</h6>
                                    <div class="h2 mb-0">₱<?php echo number_format($counts['total_balance'], 2); ?></div>
                                </div>
                                <i class="bi bi-cash-coin fs-1"></i>
                            </div>
                            <div class="card-footer bg-danger bg-opacity-25 d-flex align-items-center justify-content-between">
                                <span class="small text-white">View Details</span>
                                <div class="small text-white"><i class="bi bi-chevron-right"></i></div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid d-md-flex gap-2">
                        <a href="payments.php" class="btn btn-outline-warning text-start"><i class="bi bi-credit-card me-2"></i>Manage Payments</a>
                        <a href="payments.php" class="btn btn-outline-success text-start"><i class="bi bi-plus-circle me-2"></i>Assign Payment</a>
                        <a href="payments.php" class="btn btn-outline-primary text-start"><i class="bi bi-receipt me-2"></i>Record Payment</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../system_admin/assets/js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>
