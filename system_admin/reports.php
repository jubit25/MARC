<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

// Only system admin can access this page
if (($_SESSION['role'] ?? '') !== 'system_admin') {
    header('location: ../index.php');
    exit;
}
// Filters
$sel_sy = trim($_GET['school_year'] ?? '');
$sel_grade_level = trim($_GET['grade_level'] ?? '');
$sel_subject_id = (int)($_GET['subject_id'] ?? 0);

// Options for filters
$school_years = [];
$res = $conn->query("SELECT DISTINCT school_year FROM grades ORDER BY school_year DESC");
if ($res) { while ($r = $res->fetch_assoc()) { if (($r['school_year'] ?? '') !== '') $school_years[] = $r['school_year']; } $res->close(); }

$grade_levels = [];
$res = $conn->query("SELECT DISTINCT grade_level FROM students ORDER BY grade_level");
if ($res) { while ($r = $res->fetch_assoc()) { if (($r['grade_level'] ?? '') !== '') $grade_levels[] = $r['grade_level']; } $res->close(); }

$subjects = [];
$res = $conn->query("SELECT id, subject_name FROM subjects ORDER BY subject_name");
if ($res) { while ($r = $res->fetch_assoc()) { $subjects[] = $r; } $res->close(); }

// Quarter fallback detection
$has_quarter_col = false;
$colCheck = $conn->query("SHOW COLUMNS FROM grades LIKE 'quarter'");
if ($colCheck) { $has_quarter_col = ($colCheck->num_rows > 0); $colCheck->close(); }

// Build report query with filters
$where = [];
if ($sel_sy !== '') { $where[] = "g.school_year='".$conn->real_escape_string($sel_sy)."'"; }
if ($sel_grade_level !== '') { $where[] = "s.grade_level='".$conn->real_escape_string($sel_grade_level)."'"; }
if ($sel_subject_id > 0) { $where[] = "sub.id=".(int)$sel_subject_id; }
$where_sql = count($where) ? ('WHERE '.implode(' AND ', $where)) : '';

$report_sql = $has_quarter_col
    ? "SELECT g.id, g.grade, g.quarter, g.school_year, g.remarks, u.first_name, u.last_name, s.grade_level, s.id AS student_id, sub.subject_name\n        FROM grades g\n        JOIN students s ON s.id=g.student_id\n        JOIN users u ON u.id=s.user_id\n        JOIN subjects sub ON sub.id=g.subject_id\n        $where_sql\n        ORDER BY g.school_year DESC, u.last_name, u.first_name, sub.subject_name, FIELD(g.quarter,'1st','2nd','3rd','4th')"
    : "SELECT g.id, g.grade, g.semester AS quarter, g.school_year, g.remarks, u.first_name, u.last_name, s.grade_level, s.id AS student_id, sub.subject_name\n        FROM grades g\n        JOIN students s ON s.id=g.student_id\n        JOIN users u ON u.id=s.user_id\n        JOIN subjects sub ON sub.id=g.subject_id\n        $where_sql\n        ORDER BY g.school_year DESC, u.last_name, u.first_name, sub.subject_name, FIELD(g.semester,'1st','2nd')";

$report_rows = [];
$res = $conn->query($report_sql);
if ($res) { while ($r = $res->fetch_assoc()) { $report_rows[] = $r; } $res->close(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Reports | MARC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/MARC/image/marclogo.png">
</head>
<body class="topnav">
<div class="wrapper">
    <div id="content">
        <?php include '../includes/navbar.php'; ?>
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Grade Reports</h2>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white">Filters</div>
                <div class="card-body">
                    <form method="get" class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label">School Year</label>
                            <select name="school_year" class="form-select">
                                <option value="">All</option>
                                <?php foreach ($school_years as $sy): ?>
                                    <option value="<?php echo htmlspecialchars($sy); ?>" <?php echo ($sel_sy===$sy?'selected':''); ?>><?php echo htmlspecialchars($sy); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Grade Level</label>
                            <select name="grade_level" class="form-select">
                                <option value="">All</option>
                                <?php foreach ($grade_levels as $gl): ?>
                                    <option <?php echo ($sel_grade_level===$gl?'selected':''); ?>><?php echo htmlspecialchars($gl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Subject</label>
                            <select name="subject_id" class="form-select">
                                <option value="0">All</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?php echo (int)$sub['id']; ?>" <?php echo ($sel_subject_id==(int)$sub['id']?'selected':''); ?>><?php echo htmlspecialchars($sub['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">Grade Report</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Grade Level</th>
                                    <th>Subject</th>
                                    <th>Quarter</th>
                                    <th>School Year</th>
                                    <th>Grade</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($report_rows)===0): ?>
                                    <tr><td colspan="8" class="text-center text-muted">No records found for the selected filters.</td></tr>
                                <?php else: foreach ($report_rows as $r): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(($r['last_name'] ?? '').', '.($r['first_name'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($r['grade_level'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($r['subject_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($r['quarter'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($r['school_year'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($r['grade'] ?? ''); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($r['remarks'] ?? ''); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary" href="../students/report_card.php?student_id=<?php echo (int)($r['student_id'] ?? 0); ?>" target="_blank" title="Print Report Card"><i class="bi bi-printer"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
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
