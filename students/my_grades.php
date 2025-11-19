<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

if (($_SESSION['role'] ?? '') !== 'students') {
    header('location: ../index.php');
    exit;
}

$user_id = (int)($_SESSION['id'] ?? 0);
$student_id = 0;

// Get student's internal id (optional if you will fetch grades later)
$stmt = $conn->prepare("SELECT s.id FROM students s WHERE s.user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
if ($stmt->execute()) {
    $stmt->bind_result($student_id);
    $stmt->fetch();
}
$stmt->close();
// Fetch grades for this student
$grade_rows = [];
if ($student_id > 0) {
    // Check if 'quarter' column exists to be resilient pre-migration
    $has_quarter_col = false;
    if ($chk = $conn->query("SHOW COLUMNS FROM grades LIKE 'quarter'")) { $has_quarter_col = ($chk->num_rows > 0); $chk->close(); }
    $order_quarter = $has_quarter_col ? "FIELD(g.quarter,'1st','2nd','3rd','4th')" : "FIELD(g.semester,'1st','2nd')";
    $select_quarter = $has_quarter_col ? "g.quarter" : "g.semester AS quarter";
    $sql = "SELECT g.grade, $select_quarter, g.school_year, g.remarks, g.created_at, sub.subject_name\n            FROM grades g\n            JOIN subjects sub ON sub.id = g.subject_id\n            WHERE g.student_id = ?\n            ORDER BY g.school_year DESC, $order_quarter, sub.subject_name";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $student_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) { $grade_rows[] = $r; }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades | MARC</title>
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
                <h2 class="mb-0">My Grades</h2>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Grade Summary</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Quarter</th>
                                    <th>Grade</th>
                                    <th>Remarks</th>
                                    <th>Date Posted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($grade_rows)===0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No grades available yet.</td>
                                    </tr>
                                <?php else: foreach ($grade_rows as $g): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($g['subject_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars(($g['quarter'] ?? '').' ('.($g['school_year'] ?? '').')'); ?></td>
                                        <td><?php echo htmlspecialchars($g['grade'] ?? ''); ?></td>
                                        <td class="small text-muted">&nbsp;<?php echo htmlspecialchars($g['remarks'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars(isset($g['created_at']) ? date('Y-m-d', strtotime($g['created_at'])) : ''); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Final Ratings (Per Subject)</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Compute averages per subject per school year
                    $finals = [];
                    foreach ($grade_rows as $r) {
                        $sy = $r['school_year'] ?? '';
                        $subj = $r['subject_name'] ?? '';
                        $g = isset($r['grade']) ? floatval($r['grade']) : null;
                        if ($sy === '' || $subj === '' || $g === null) continue;
                        if (!isset($finals[$sy])) $finals[$sy] = [];
                        if (!isset($finals[$sy][$subj])) $finals[$sy][$subj] = ['sum'=>0,'cnt'=>0];
                        $finals[$sy][$subj]['sum'] += $g;
                        $finals[$sy][$subj]['cnt'] += 1;
                    }
                    ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>School Year</th>
                                    <th>Subject</th>
                                    <th>Final Grade</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($finals)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No final ratings yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($finals as $sy => $subjects): ?>
                                        <?php foreach ($subjects as $subj => $agg): ?>
                                            <?php $final = $agg['cnt'] > 0 ? round($agg['sum'] / $agg['cnt'], 2) : null; ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sy); ?></td>
                                                <td><?php echo htmlspecialchars($subj); ?></td>
                                                <td><?php echo htmlspecialchars($final !== null ? $final : ''); ?></td>
                                                <td><?php echo ($final !== null && $final >= 75) ? 'Pass' : (($final !== null) ? 'Did Not Meet Expectations' : ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
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
