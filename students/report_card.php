<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

// Only admins can access printable report card
$role = ($_SESSION['role'] ?? '');
if (!in_array($role, ['system_admin','registrar'], true)) {
    header('location: ../index.php');
    exit;
}

// Load report settings
$report_header = '';
$report_logo = '';
$report_logo_h = '120';
$report_title_sz = '20';
if ($res = $conn->query("SELECT k, v FROM app_settings WHERE k IN ('report_card_header','report_card_logo_url','report_card_logo_height','report_card_title_size')")) {
    while ($r = $res->fetch_assoc()) {
        if (($r['k'] ?? '') === 'report_card_header') { $report_header = $r['v'] ?? ''; }
        if (($r['k'] ?? '') === 'report_card_logo_url') { $report_logo = $r['v'] ?? ''; }
        if (($r['k'] ?? '') === 'report_card_logo_height') { $report_logo_h = $r['v'] ?? '120'; }
        if (($r['k'] ?? '') === 'report_card_title_size') { $report_title_sz = $r['v'] ?? '20'; }
    }
    $res->close();
}

// Determine which student to print via ?student_id (required)
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$student_name = '';
$grade_level = '';
if ($student_id > 0) {
    if ($stmt = $conn->prepare("SELECT u.first_name, u.last_name, s.grade_level FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ? LIMIT 1")) {
        $stmt->bind_param('i', $student_id);
        if ($stmt->execute()) {
            $stmt->bind_result($first, $last, $grade_level);
            if ($stmt->fetch()) {
                $student_name = trim(($first ?? '') . ' ' . ($last ?? ''));
            }
        }
        $stmt->close();
    }
}

// Fetch grades grouped by school year and subject
$grades = [];
if ($student_id > 0) {
    // Detect if 'quarter' column exists
    $has_quarter = false;
    if ($chk = $conn->query("SHOW COLUMNS FROM grades LIKE 'quarter'")) { $has_quarter = ($chk->num_rows > 0); $chk->close(); }
    $select_quarter = $has_quarter ? 'g.quarter' : 'g.semester AS quarter';

    $sql = "SELECT sub.subject_name, $select_quarter, g.grade, g.school_year\n            FROM grades g\n            JOIN subjects sub ON sub.id = g.subject_id\n            WHERE g.student_id = ?\n            ORDER BY g.school_year DESC, sub.subject_name, FIELD(".($has_quarter?"g.quarter,'1st','2nd','3rd','4th'":"g.semester,'1st','2nd'").")";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $student_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $sy = $r['school_year'] ?? '';
                $subj = $r['subject_name'] ?? '';
                $q = $r['quarter'] ?? '';
                $g = isset($r['grade']) ? floatval($r['grade']) : null;
                if ($sy === '' || $subj === '' || $q === '' || $g === null) continue;
                if (!isset($grades[$sy])) $grades[$sy] = [];
                if (!isset($grades[$sy][$subj])) $grades[$sy][$subj] = ['quarters'=>[], 'final'=>null];
                $grades[$sy][$subj]['quarters'][$q] = $g;
            }
        }
        $stmt->close();
    }
}

// Compute finals
foreach ($grades as $sy => $subjects) {
    foreach ($subjects as $subj => $data) {
        $sum = 0; $cnt = 0;
        foreach ($data['quarters'] as $qg) { $sum += $qg; $cnt++; }
        $grades[$sy][$subj]['final'] = $cnt > 0 ? round($sum / $cnt, 2) : null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card | MARC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/MARC/image/marclogo.png">
    <style>
        /* DepEd-like clean typography */
        body { font-family: Calibri, "Segoe UI", Arial, Helvetica, sans-serif; color:#111; }
        h1, h2, h3, h4, h5, h6 { font-family: "Times New Roman", Georgia, serif; }

        /* Print-friendly margins */
        @page { size: A4; margin: 18mm 15mm; }
        @media print {
            .no-print { display: none !important; }
            .card { border: none; box-shadow: none; }
            .container { max-width: none !important; padding: 0 !important; }
        }

        /* Header styles */
        .rc-header { text-align:center; margin-bottom: 10px; }
        .rc-logo {
            max-height: <?php echo (int)$report_logo_h; ?>px; /* controlled via settings */
            width: auto;
            object-fit: contain;
            image-rendering: auto; /* better quality */
            image-rendering: -webkit-optimize-contrast;
        }
        .rc-title { font-size: <?php echo (int)$report_title_sz; ?>px; font-weight: 700; letter-spacing: .3px; }
        .rc-subtitle { font-size: 12px; color:#444; margin-top: 2px; }

        /* Info rows */
        .rc-info { font-size: 13px; margin-bottom: 8px; }
        .rc-info strong { min-width: 90px; display: inline-block; }

        /* Table aesthetics */
        .table { font-size: 13px; }
        .table thead th { border-bottom: 2px solid #000 !important; }
        .table, .table th, .table td { border-color:#333 !important; }
        .table td, .table th { padding: .45rem .6rem; }

        .rc-footer { margin-top: 18px; color:#666; font-size: 11px; }
    </style>
</head>
<body class="bg-light">
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="mb-0">Printable Report Card</h4>
        <button class="btn btn-primary" onclick="window.print()">Print</button>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="rc-header">
                <?php if ($report_logo): ?>
                    <img class="rc-logo" src="<?php echo htmlspecialchars($report_logo); ?>" alt="Logo">
                <?php endif; ?>
                <?php if ($report_header): ?>
                    <div class="rc-title"><?php echo htmlspecialchars($report_header); ?></div>
                <?php endif; ?>
            </div>
            <?php
                $school_year_label = '';
                $sy_keys = array_keys($grades);
                if (count($sy_keys) === 1) { $school_year_label = $sy_keys[0]; }
            ?>
            <div class="row mb-2 rc-info">
                <div class="col">
                    <div><strong>Student:</strong> <?php echo htmlspecialchars($student_name); ?></div>
                    <div><strong>Grade Level:</strong> <?php echo htmlspecialchars($grade_level); ?></div>
                </div>
                <div class="col text-end">
                    <div><strong>Date:</strong> <?php echo date('Y-m-d'); ?></div>
                </div>
            </div>
            <?php if ($school_year_label): ?>
            <div class="rc-info mb-2"><strong>School Year:</strong> <?php echo htmlspecialchars($school_year_label); ?></div>
            <?php endif; ?>

            <?php if (empty($grades)): ?>
                <div class="alert alert-info">No grades to display.</div>
            <?php else: ?>
                <?php foreach ($grades as $sy => $subjects): ?>
                    <h6 class="mt-3">School Year: <?php echo htmlspecialchars($sy); ?></h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>1st</th>
                                    <th>2nd</th>
                                    <th>3rd</th>
                                    <th>4th</th>
                                    <th>Final Grade</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subj => $data): ?>
                                    <?php 
                                        $q = $data['quarters'];
                                        $final = $data['final'];
                                        $status = ($final !== null && $final >= 75) ? 'Pass' : (($final !== null) ? 'Did Not Meet Expectations' : '');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subj); ?></td>
                                        <td><?php echo isset($q['1st']) ? htmlspecialchars($q['1st']) : ''; ?></td>
                                        <td><?php echo isset($q['2nd']) ? htmlspecialchars($q['2nd']) : ''; ?></td>
                                        <td><?php echo isset($q['3rd']) ? htmlspecialchars($q['3rd']) : ''; ?></td>
                                        <td><?php echo isset($q['4th']) ? htmlspecialchars($q['4th']) : ''; ?></td>
                                        <td><?php echo $final !== null ? htmlspecialchars($final) : ''; ?></td>
                                        <td><?php echo htmlspecialchars($status); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="mt-4 small text-muted">This document is system-generated.</div>
        </div>
    </div>
</div>
</body>
<script>
// Auto print when the page is fully loaded, then try to close the tab
window.addEventListener('load', function(){
  setTimeout(function(){
    try { window.print(); } catch (e) {}
  }, 200);
});
window.addEventListener('afterprint', function(){
  // Some browsers allow closing a window opened via user interaction
  try { window.close(); } catch (e) {}
});
</script>
</html>
<?php $conn->close(); ?>
