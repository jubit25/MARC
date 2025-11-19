<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

if (($_SESSION['role'] ?? '') !== 'students') {
    header('location: ../index.php');
    exit;
}

$user_id = (int)($_SESSION['id'] ?? 0);
$student_id = 0;

// Get student's internal id
$stmt = $conn->prepare("SELECT s.id, u.first_name, u.last_name FROM students s JOIN users u ON u.id = s.user_id WHERE s.user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$first_name = $last_name = '';
if ($stmt->execute()) {
    $stmt->bind_result($student_id, $first_name, $last_name);
    $stmt->fetch();
}
$stmt->close();

if ($student_id <= 0) {
    echo 'Student profile not found.';
    exit;
}

$total_balance = 0.0;
$sql = "SELECT SUM(GREATEST(ps.amount_due - COALESCE(p.total_paid,0), 0)) AS balance
        FROM payment_schedule ps
        LEFT JOIN (
          SELECT student_id, payment_category_id, SUM(amount_paid) AS total_paid
          FROM payments
          WHERE student_id = ?
          GROUP BY student_id, payment_category_id
        ) p ON p.student_id = ps.student_id AND p.payment_category_id = ps.payment_category_id
        WHERE ps.student_id = ?";
if ($st2 = $conn->prepare($sql)) {
    $st2->bind_param('ii', $student_id, $student_id);
    if ($st2->execute()) {
        $res = $st2->get_result();
        $row = $res->fetch_assoc();
        $total_balance = (float)($row['balance'] ?? 0);
    }
    $st2->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student | MARC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../system_admin/assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/MARC/image/marclogo.png">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div id="content">
        <?php include '../includes/navbar.php'; ?>
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Welcome, <?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></h2>
                <div class="text-muted"><i class="bi bi-calendar"></i> <?php echo date('F j, Y'); ?></div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card bg-danger bg-opacity-25 h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small text-muted">Total Balance</div>
                                <div class="h3 mb-0">₱<?php echo number_format($total_balance, 2); ?></div>
                            </div>
                            <i class="bi bi-cash-coin fs-1 text-danger"></i>
                        </div>
                        <div class="card-footer bg-danger bg-opacity-10 d-flex align-items-center justify-content-between">
                            <a class="small text-danger stretched-link" href="my_payments.php">View Details</a>
                            <div class="small text-danger"><i class="bi bi-chevron-right"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small text-muted">Payments</div>
                                <div class="h6 mb-0">See your assigned fees and payment history</div>
                            </div>
                            <i class="bi bi-receipt fs-1 text-secondary"></i>
                        </div>
                        <div class="card-footer bg-light d-flex align-items-center justify-content-between">
                            <a class="small stretched-link" href="my_payments.php">Go to My Payments</a>
                            <div class="small"><i class="bi bi-chevron-right"></i></div>
                        </div>
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
