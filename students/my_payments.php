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
$stmt = $conn->prepare("SELECT s.id FROM students s WHERE s.user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
if ($stmt->execute()) {
    $stmt->bind_result($student_id);
    $stmt->fetch();
}
$stmt->close();

if ($student_id <= 0) {
    echo 'Student profile not found.';
    exit;
}

$items = [];
$total_balance = 0.0;

$sql = "
SELECT 
  ps.id,
  pc.name,
  ps.due_date,
  ps.amount_due,
  ps.status,
  COALESCE(p.total_paid,0) AS total_paid,
  GREATEST(ps.amount_due - COALESCE(p.total_paid,0), 0) AS remaining
FROM payment_schedule ps
JOIN payment_categories pc ON pc.id = ps.payment_category_id
LEFT JOIN (
  SELECT student_id, payment_category_id, SUM(amount_paid) AS total_paid
  FROM payments
  WHERE student_id = ?
  GROUP BY student_id, payment_category_id
) p ON p.student_id = ps.student_id AND p.payment_category_id = ps.payment_category_id
WHERE ps.student_id = ?
ORDER BY ps.due_date ASC, pc.name ASC";

if ($stmt2 = $conn->prepare($sql)) {
    $stmt2->bind_param('ii', $student_id, $student_id);
    if ($stmt2->execute()) {
        $res = $stmt2->get_result();
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
            $total_balance += (float)$row['remaining'];
        }
    }
    $stmt2->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments | MARC</title>
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
                <h2 class="mb-0">My Payments</h2>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card bg-danger bg-opacity-25">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-uppercase small text-muted">Total Balance</div>
                                <div class="h3 mb-0">₱<?php echo number_format($total_balance, 2); ?></div>
                            </div>
                            <i class="bi bi-cash-coin fs-1 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Assigned Payments</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Payment Item</th>
                                    <th>Due Date</th>
                                    <th>Amount Due</th>
                                    <th>Paid</th>
                                    <th>Remaining</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($items) === 0): ?>
                                    <tr><td colspan="6" class="text-center text-muted">No assigned payments.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($items as $it): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($it['name']); ?></td>
                                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($it['due_date']))); ?></td>
                                            <td>₱<?php echo number_format((float)$it['amount_due'], 2); ?></td>
                                            <td>₱<?php echo number_format((float)$it['total_paid'], 2); ?></td>
                                            <td>₱<?php echo number_format((float)$it['remaining'], 2); ?></td>
                                            <td>
                                                <?php
                                                    $remaining = (float)$it['remaining'];
                                                    $dueDate = new DateTime($it['due_date']);
                                                    $today = new DateTime('today');
                                                    $computed = 'pending';
                                                    if ($remaining <= 0.009) {
                                                        $computed = 'paid';
                                                    } elseif ($dueDate < $today) {
                                                        $computed = 'overdue';
                                                    }
                                                ?>
                                                <span class="badge bg-<?php echo $computed === 'paid' ? 'success' : ($computed === 'overdue' ? 'danger' : 'secondary'); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($computed)); ?>
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../system_admin/assets/js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>
