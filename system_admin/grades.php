<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

// Only system admin can access this page
if (($_SESSION['role'] ?? '') !== 'system_admin') {
    header('location: ../index.php');
    exit;
}
// Section and action
$section = $_GET['section'] ?? 'grades';
$action = $_GET['action'] ?? '';

$success_msg = '';
$error_msg = '';

// Load settings (auto_remarks_enabled, default_school_year)
$auto_remarks_enabled = '1';
$default_school_year = '';
if ($res = $conn->query("SELECT k, v FROM app_settings WHERE k IN ('auto_remarks_enabled','default_school_year')")) {
    while ($r = $res->fetch_assoc()) {
        if (($r['k'] ?? '') === 'auto_remarks_enabled') { $auto_remarks_enabled = $r['v'] ?? '1'; }
        if (($r['k'] ?? '') === 'default_school_year') { $default_school_year = $r['v'] ?? ''; }
    }
    $res->close();
}

// Handle Subject CRUD
if ($section === 'subjects') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject_code = trim($_POST['subject_code'] ?? '');
        $subject_name = trim($_POST['subject_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $grade_level = trim($_POST['grade_level'] ?? '');
        $mode = $_POST['mode'] ?? 'create';
        if ($subject_code === '' || $subject_name === '' || $grade_level === '') {
            $error_msg = 'Please provide subject code, name, and grade level.';
        } else {
            if ($mode === 'create') {
                $stmt = $conn->prepare('INSERT INTO subjects (subject_code, subject_name, description, grade_level) VALUES (?, ?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('ssss', $subject_code, $subject_name, $description, $grade_level);
                    if ($stmt->execute()) { $success_msg = 'Subject added.'; } else { $error_msg = 'Failed to add subject.'; }
                    $stmt->close();
                } else { $error_msg = 'Failed to prepare statement.'; }
            } elseif ($mode === 'update') {
                $id = intval($_POST['id'] ?? 0);
                $stmt = $conn->prepare('UPDATE subjects SET subject_code=?, subject_name=?, description=?, grade_level=? WHERE id=?');
                if ($stmt) {
                    $stmt->bind_param('ssssi', $subject_code, $subject_name, $description, $grade_level, $id);
                    if ($stmt->execute()) { $success_msg = 'Subject updated.'; } else { $error_msg = 'Failed to update subject.'; }
                    $stmt->close();
                } else { $error_msg = 'Failed to prepare statement.'; }
            }
        }
    }
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM subjects WHERE id=?');
            if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); $success_msg = 'Subject deleted.'; }
        }
    }
}

// Handle Grade Entries CRUD
if ($section === 'grades') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mode = $_POST['mode'] ?? 'create';
        $student_id = intval($_POST['student_id'] ?? 0);
        $subject_id = intval($_POST['subject_id'] ?? 0);
        $grade_val = trim($_POST['grade'] ?? '');
        $quarter = trim($_POST['quarter'] ?? '');
        $school_year = trim($_POST['school_year'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $user_id = intval($_SESSION['id'] ?? 0);

        // Auto-generate DepEd descriptors if enabled and remarks not provided
        if ($auto_remarks_enabled === '1' && $remarks === '' && $grade_val !== '') {
            $g = floatval($grade_val);
            if ($g >= 90) { $remarks = 'Outstanding'; }
            elseif ($g >= 85) { $remarks = 'Very Satisfactory'; }
            elseif ($g >= 80) { $remarks = 'Satisfactory'; }
            elseif ($g >= 75) { $remarks = 'Fairly Satisfactory'; }
            else { $remarks = 'Did Not Meet Expectations'; }
        }

        if ($student_id <= 0 || $subject_id <= 0 || $grade_val === '' || $quarter === '' || $school_year === '') {
            $error_msg = 'Please complete all required fields.';
        } else {
            // Detect if 'quarter' column exists to support pre-migration installs
            $has_quarter_col = false;
            $colCheck = $conn->query("SHOW COLUMNS FROM grades LIKE 'quarter'");
            if ($colCheck) { $has_quarter_col = ($colCheck->num_rows > 0); $colCheck->close(); }

            // If not migrated yet, only 1st/2nd are valid (semester). Block 3rd/4th gracefully.
            if (!$has_quarter_col && !in_array($quarter, ['1st','2nd'], true)) {
                $error_msg = 'The database is not yet migrated to quarters. Please run the provided SQL migration to enable 3rd/4th quarter entries.';
            } else if ($mode === 'create') {
                if ($has_quarter_col) {
                    $stmt = $conn->prepare('INSERT INTO grades (student_id, subject_id, grade, quarter, school_year, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                } else {
                    $stmt = $conn->prepare('INSERT INTO grades (student_id, subject_id, grade, semester, school_year, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                }
                if ($stmt) {
                    $stmt->bind_param('iidsssi', $student_id, $subject_id, $grade_val, $quarter, $school_year, $remarks, $user_id);
                    if ($stmt->execute()) { $success_msg = 'Grade added.'; } else { $error_msg = 'Failed to add grade.'; }
                    $stmt->close();
                } else { $error_msg = 'Failed to prepare statement.'; }
            } else if ($mode === 'update') {
                $id = intval($_POST['id'] ?? 0);
                if ($has_quarter_col) {
                    $stmt = $conn->prepare('UPDATE grades SET student_id=?, subject_id=?, grade=?, quarter=?, school_year=?, remarks=? WHERE id=?');
                } else {
                    $stmt = $conn->prepare('UPDATE grades SET student_id=?, subject_id=?, grade=?, semester=?, school_year=?, remarks=? WHERE id=?');
                }
                if ($stmt) {
                    $stmt->bind_param('iidsssi', $student_id, $subject_id, $grade_val, $quarter, $school_year, $remarks, $id);
                    if ($stmt->execute()) { $success_msg = 'Grade updated.'; } else { $error_msg = 'Failed to update grade.'; }
                    $stmt->close();
                } else { $error_msg = 'Failed to prepare statement.'; }
            }
        }
    }
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM grades WHERE id=?');
            if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); $success_msg = 'Grade deleted.'; }
        }
    }
}

// Data for UI
$subject_rows = [];
$student_rows = [];
$grade_rows = [];

$res = $conn->query("SELECT * FROM subjects ORDER BY grade_level, subject_name");
if ($res) { while ($r = $res->fetch_assoc()) { $subject_rows[] = $r; } $res->close(); }

$res = $conn->query("SELECT s.id, u.first_name, u.last_name, s.grade_level FROM students s JOIN users u ON u.id=s.user_id ORDER BY u.last_name, u.first_name");
if ($res) { while ($r = $res->fetch_assoc()) { $student_rows[] = $r; } $res->close(); }

// Detect if 'quarter' column exists (to be resilient before DB migration)
$has_quarter_col = false;
$colCheck = $conn->query("SHOW COLUMNS FROM grades LIKE 'quarter'");
if ($colCheck) { $has_quarter_col = ($colCheck->num_rows > 0); $colCheck->close(); }

$grade_sql = $has_quarter_col
    ? "SELECT g.id, g.grade, g.quarter, g.school_year, g.remarks, u.first_name, u.last_name, s.grade_level, s.id AS student_id, sub.subject_name FROM grades g JOIN students s ON s.id=g.student_id JOIN users u ON u.id=s.user_id JOIN subjects sub ON sub.id=g.subject_id ORDER BY g.created_at DESC"
    : "SELECT g.id, g.grade, g.semester AS quarter, g.school_year, g.remarks, u.first_name, u.last_name, s.grade_level, s.id AS student_id, sub.subject_name FROM grades g JOIN students s ON s.id=g.student_id JOIN users u ON u.id=s.user_id JOIN subjects sub ON sub.id=g.subject_id ORDER BY g.created_at DESC";

$res = $conn->query($grade_sql);
if ($res) { while ($r = $res->fetch_assoc()) { $grade_rows[] = $r; } $res->close(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades | MARC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/MARC/image/marclogo.png">
</head>
<body class="topnav">
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div id="content">
        <?php include '../includes/navbar.php'; ?>
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Manage Grades</h2>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <ul class="nav nav-pills mb-3">
                <li class="nav-item"><a class="nav-link <?php echo ($section==='grades')?'active':''; ?>" href="grades.php?section=grades">Grade Entries</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($section==='subjects')?'active':''; ?>" href="grades.php?section=subjects">Subjects</a></li>
                <li class="nav-item"><a class="nav-link <?php echo ($section==='students')?'active':''; ?>" href="grades.php?section=students">Students</a></li>
            </ul>

            <?php if ($section === 'subjects'): ?>
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span>Subjects</span>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#subjectModal">Add Subject</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Grade Level</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($subject_rows)===0): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No subjects found.</td></tr>
                                <?php else: foreach ($subject_rows as $s): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($s['subject_code']); ?></td>
                                        <td><?php echo htmlspecialchars($s['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($s['grade_level']); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($s['description'] ?? ''); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#subjectModal" data-mode="update" data-id="<?php echo (int)$s['id']; ?>" data-code="<?php echo htmlspecialchars($s['subject_code']); ?>" data-name="<?php echo htmlspecialchars($s['subject_name']); ?>" data-grade="<?php echo htmlspecialchars($s['grade_level']); ?>" data-desc="<?php echo htmlspecialchars($s['description'] ?? ''); ?>"><i class="bi bi-pencil"></i></button>
                                            <a href="grades.php?section=subjects&action=delete&id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete subject? This will remove related grades.');"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="subjectModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="post" class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Subject</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <input type="hidden" name="mode" id="subject_mode" value="create">
                                <input type="hidden" name="id" id="subject_id" value="0">
                                <div class="mb-2">
                                    <label class="form-label">Code*</label>
                                    <input type="text" class="form-control" name="subject_code" id="subject_code" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Name*</label>
                                    <input type="text" class="form-control" name="subject_name" id="subject_name" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Grade Level*</label>
                                    <select class="form-select" name="grade_level" id="subject_grade" required>
                                        <option value="">Select</option>
                                        <option>Grade 1</option>
                                        <option>Grade 2</option>
                                        <option>Grade 3</option>
                                        <option>Grade 4</option>
                                        <option>Grade 5</option>
                                        <option>Grade 6</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" id="subject_desc" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Save</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($section === 'students'): ?>
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span>Students</span>
                        <a class="btn btn-sm btn-success" href="manage_students.php?action=add"><i class="bi bi-person-plus me-1"></i>Add Student</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Grade Level</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($student_rows)===0): ?>
                                    <tr><td colspan="3" class="text-center text-muted">No students found.</td></tr>
                                <?php else: foreach ($student_rows as $st): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(($st['last_name'] ?? '').', '.($st['first_name'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($st['grade_level'] ?? ''); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary" href="../students/report_card.php?student_id=<?php echo (int)$st['id']; ?>" target="_blank" title="Print Report Card"><i class="bi bi-printer"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span>Grade Entries</span>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#gradeModal">Add Grade</button>
                    </div>
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
                                <?php if (count($grade_rows)===0): ?>
                                    <tr><td colspan="8" class="text-center text-muted">No grade records.</td></tr>
                                <?php else: foreach ($grade_rows as $g): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(($g['last_name'] ?? '').', '.($g['first_name'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($g['grade_level'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($g['subject_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($g['quarter'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($g['school_year'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($g['grade'] ?? ''); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($g['remarks'] ?? ''); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#gradeModal" data-mode="update" data-id="<?php echo (int)$g['id']; ?>" data-student="<?php echo (int)($g['student_id'] ?? 0); ?>" data-grade="<?php echo htmlspecialchars($g['grade']); ?>" data-quarter="<?php echo htmlspecialchars($g['quarter']); ?>" data-sy="<?php echo htmlspecialchars($g['school_year']); ?>" data-remarks="<?php echo htmlspecialchars($g['remarks'] ?? ''); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="grades.php?section=grades&action=delete&id=<?php echo (int)$g['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this grade entry?');"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="gradeModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="post" class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Grade Entry (Elementary)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <input type="hidden" name="mode" id="grade_mode" value="create">
                                <input type="hidden" name="id" id="grade_id" value="0">
                                <input type="hidden" name="student_id" id="grade_student_hidden" value="">
                                <div class="mb-2">
                                    <label class="form-label">Student*</label>
                                    <select class="form-select" name="student_id" id="grade_student" required>
                                        <option value="">Select</option>
                                        <?php foreach ($student_rows as $st): ?>
                                            <option value="<?php echo (int)$st['id']; ?>"><?php echo htmlspecialchars(($st['last_name'] ?? '').', '.($st['first_name'] ?? '').' ('.($st['grade_level'] ?? '').')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Subject*</label>
                                    <select class="form-select" name="subject_id" id="grade_subject" required>
                                        <option value="">Select</option>
                                        <?php foreach ($subject_rows as $s): ?>
                                            <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars(($s['subject_name'] ?? '').' - '.($s['grade_level'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Quarter*</label>
                                        <select class="form-select" name="quarter" id="grade_quarter" required>
                                            <option value="">Select</option>
                                            <option>1st</option>
                                            <option>2nd</option>
                                            <option>3rd</option>
                                            <option>4th</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">School Year*</label>
                                        <input class="form-control" type="text" name="school_year" id="grade_sy" placeholder="2025-2026" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Grade* (0-100)</label>
                                        <input class="form-control" type="number" step="0.01" min="0" max="100" name="grade" id="grade_val" required>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="form-label">Remarks</label>
                                    <input class="form-control" type="text" name="remarks" id="grade_remarks">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Save</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var DEFAULT_SY = '<?php echo addslashes($default_school_year); ?>';
(function(){
  var subjectModal = document.getElementById('subjectModal');
  if (subjectModal) {
    subjectModal.addEventListener('show.bs.modal', function (e) {
      var btn = e.relatedTarget || {};
      var mode = btn.getAttribute ? (btn.getAttribute('data-mode') || 'create') : 'create';
      document.getElementById('subject_mode').value = mode;
      document.getElementById('subject_id').value = btn.getAttribute ? (btn.getAttribute('data-id') || '0') : '0';
      document.getElementById('subject_code').value = btn.getAttribute ? (btn.getAttribute('data-code') || '') : '';
      document.getElementById('subject_name').value = btn.getAttribute ? (btn.getAttribute('data-name') || '') : '';
      document.getElementById('subject_grade').value = btn.getAttribute ? (btn.getAttribute('data-grade') || '') : '';
      document.getElementById('subject_desc').value = btn.getAttribute ? (btn.getAttribute('data-desc') || '') : '';
    });
  }
  var gradeModal = document.getElementById('gradeModal');
  if (gradeModal) {
    gradeModal.addEventListener('show.bs.modal', function(e){
      var btn = e.relatedTarget || {};
      var mode = btn.getAttribute ? (btn.getAttribute('data-mode') || 'create') : 'create';
      document.getElementById('grade_mode').value = mode;
      document.getElementById('grade_id').value = btn.getAttribute ? (btn.getAttribute('data-id') || '0') : '0';
      document.getElementById('grade_val').value = btn.getAttribute ? (btn.getAttribute('data-grade') || '') : '';
      document.getElementById('grade_quarter').value = btn.getAttribute ? (btn.getAttribute('data-semester') || btn.getAttribute('data-quarter') || '') : '';
      var syInput = document.getElementById('grade_sy');
      syInput.value = btn.getAttribute ? (btn.getAttribute('data-sy') || '') : '';
      document.getElementById('grade_remarks').value = btn.getAttribute ? (btn.getAttribute('data-remarks') || '') : '';
      var studentSel = document.getElementById('grade_student');
      var studentHidden = document.getElementById('grade_student_hidden');
      if (mode === 'update') {
        var sid = btn.getAttribute ? (btn.getAttribute('data-student') || '') : '';
        if (studentSel) {
          studentSel.value = sid;
          studentSel.setAttribute('disabled', 'disabled');
        }
        if (studentHidden) studentHidden.value = sid; // ensure submission includes correct student_id
      } else {
        if (studentSel) {
          studentSel.removeAttribute('disabled');
          studentSel.selectedIndex = 0;
        }
        if (studentHidden) studentHidden.value = '';
        if (syInput && (!syInput.value || syInput.value.trim()==='') && DEFAULT_SY) {
          syInput.value = DEFAULT_SY;
        }
      }
      var subjSel = document.getElementById('grade_subject');
      if (mode === 'create') { if (subjSel) subjSel.selectedIndex = 0; }
    });
  }
})();
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>
