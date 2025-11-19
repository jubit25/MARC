<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

// Only system admin can access this page
if ($_SESSION["role"] !== 'system_admin') {
    header("location: ../index.php");
    exit;
}

$success_msg = $error_msg = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Subject mapping per grade level
$GRADE_SUBJECTS = [
    'Grade 1' => [
        ['code' => 'G1-ENG', 'name' => 'English'],
        ['code' => 'G1-MATH', 'name' => 'Mathematics'],
        ['code' => 'G1-MTFIL', 'name' => 'Mother Tongue / Filipino'],
        ['code' => 'G1-AP', 'name' => 'Araling Panlipunan (Social Studies)'],
        ['code' => 'G1-ESP', 'name' => 'Edukasyon sa Pagpapakatao (EsP) / Values Education'],
    ],
    'Grade 2' => [
        ['code' => 'G2-ENG', 'name' => 'English'],
        ['code' => 'G2-MATH', 'name' => 'Mathematics'],
        ['code' => 'G2-FIL', 'name' => 'Filipino'],
        ['code' => 'G2-SCI', 'name' => 'Science'],
        ['code' => 'G2-AP', 'name' => 'Araling Panlipunan'],
    ],
    'Grade 3' => [
        ['code' => 'G3-ENG', 'name' => 'English'],
        ['code' => 'G3-MATH', 'name' => 'Mathematics'],
        ['code' => 'G3-FIL', 'name' => 'Filipino'],
        ['code' => 'G3-SCI', 'name' => 'Science'],
        ['code' => 'G3-ESP', 'name' => 'Edukasyon sa Pagpapakatao (EsP)'],
    ],
    'Grade 4' => [
        ['code' => 'G4-ENG', 'name' => 'English'],
        ['code' => 'G4-MATH', 'name' => 'Mathematics'],
        ['code' => 'G4-FIL', 'name' => 'Filipino'],
        ['code' => 'G4-SCI', 'name' => 'Science'],
        ['code' => 'G4-AP', 'name' => 'Araling Panlipunan'],
    ],
    'Grade 5' => [
        ['code' => 'G5-ENG', 'name' => 'English'],
        ['code' => 'G5-MATH', 'name' => 'Mathematics'],
        ['code' => 'G5-FIL', 'name' => 'Filipino'],
        ['code' => 'G5-SCI', 'name' => 'Science'],
        ['code' => 'G5-ESP', 'name' => 'Edukasyon sa Pagpapakatao (EsP)'],
    ],
    'Grade 6' => [
        ['code' => 'G6-ENG', 'name' => 'English'],
        ['code' => 'G6-MATH', 'name' => 'Mathematics'],
        ['code' => 'G6-FIL', 'name' => 'Filipino'],
        ['code' => 'G6-SCI', 'name' => 'Science'],
        ['code' => 'G6-AP', 'name' => 'Araling Panlipunan'],
    ],
];

// Ensure subjects exist in DB for a given grade level
function ensureSubjectsForGrade(mysqli $conn, string $grade, array $map): void {
    if (!isset($map[$grade])) return;
    $stmt = $conn->prepare('INSERT IGNORE INTO subjects (subject_code, subject_name, description, grade_level) VALUES (?, ?, ?, ?)');
    if (!$stmt) return;
    foreach ($map[$grade] as $subj) {
        $code = $subj['code'];
        $name = $subj['name'];
        $desc = NULL;
        $stmt->bind_param('ssss', $code, $name, $desc, $grade);
        $stmt->execute();
    }
    $stmt->close();
}

// Handle add student POST
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    // LRN provided once and used as both username and student_id
    $student_id_code = trim($_POST['lrn'] ?? '');
    $grade_level = trim($_POST['grade_level'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_contact = trim($_POST['parent_contact'] ?? '');

    if ($student_id_code === '' || $password === '' || $email === '' || $first_name === '' || $last_name === '' || $grade_level === '') {
        $error_msg = 'Please fill in all required fields.';
    } else {
        // Ensure subjects exist for the selected grade level
        ensureSubjectsForGrade($conn, $grade_level, $GRADE_SUBJECTS);
        // Start transaction
        $conn->begin_transaction();
        try {
            // Create user
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, 'students')");
            // Username equals LRN
            $stmtUser->bind_param('sssss', $student_id_code, $hashed, $email, $first_name, $last_name);
            if (!$stmtUser->execute()) {
                throw new Exception('Failed to create user: ' . $stmtUser->error);
            }
            $user_id = $stmtUser->insert_id;
            $stmtUser->close();

            // Create student profile
            $stmtStud = $conn->prepare("INSERT INTO students (user_id, student_id, grade_level, section, birth_date, address, parent_name, parent_contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtStud->bind_param('isssssss', $user_id, $student_id_code, $grade_level, $section, $birth_date, $address, $parent_name, $parent_contact);
            if (!$stmtStud->execute()) {
                throw new Exception('Failed to create student profile: ' . $stmtStud->error);
            }
            $stmtStud->close();

            $conn->commit();
            header('Location: manage_students.php?added=1');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = $e->getMessage();
        }
    }
}

// List students (default view)
$students = [];
$sql = "SELECT u.id, u.username, u.role, u.first_name, u.last_name FROM users u WHERE u.role = 'students' ORDER BY u.last_name, u.first_name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students | MARC</title>
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
                <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
                    <div class="alert alert-success">Student added successfully.</div>
                <?php endif; ?>

                <?php if ($action === 'add'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">Add Student</h2>
                        <a href="manage_students.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
                    </div>

                    <?php if ($error_msg): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-body">
                            <form method="post" action="manage_students.php?action=add">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Password*</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Email*</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">First Name*</label>
                                        <input type="text" name="first_name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name*</label>
                                        <input type="text" name="last_name" class="form-control" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">LRN (Username)*</label>
                                        <input type="text" name="lrn" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Grade Level*</label>
                                        <select name="grade_level" id="grade_level" class="form-select" required>
                                            <option value="">Select grade level</option>
                                            <option>Grade 1</option>
                                            <option>Grade 2</option>
                                            <option>Grade 3</option>
                                            <option>Grade 4</option>
                                            <option>Grade 5</option>
                                            <option>Grade 6</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Section</label>
                                        <input type="text" name="section" class="form-control">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Birth Date</label>
                                        <input type="date" name="birth_date" class="form-control">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">Address</label>
                                        <input type="text" name="address" class="form-control">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Parent/Guardian Name</label>
                                        <input type="text" name="parent_name" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Parent Contact</label>
                                        <input type="text" name="parent_contact" class="form-control">
                                    </div>
                                </div>
                                <div class="mt-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-2"></i>Save</button>
                                    <a href="manage_students.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Subjects (auto-listed by grade)</label>
                        <div class="card">
                            <div class="card-body p-2">
                                <ul id="subjectsPreview" class="list-group list-group-flush small"></ul>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">Manage Students</h2>
                        <a href="manage_students.php?action=add" class="btn btn-success"><i class="bi bi-person-plus me-2"></i>Add Student</a>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Name</th>
                                            <th scope="col">Username</th>
                                            <th scope="col">Role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($students) === 0): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No students found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students as $index => $student): ?>
                                                <tr>
                                                    <th scope="row"><?php echo $index + 1; ?></th>
                                                    <td><?php echo htmlspecialchars(($student['last_name'] ?? '') . ', ' . ($student['first_name'] ?? '')); ?></td>
                                                    <td><?php echo htmlspecialchars($student['username']); ?></td>
                                                    <td><?php echo htmlspecialchars(str_replace('_', ' ', $student['role'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        (function(){
            const subjectsMap = {
                'Grade 1': ['English','Mathematics','Mother Tongue / Filipino','Araling Panlipunan (Social Studies)','Edukasyon sa Pagpapakatao (EsP) / Values Education'],
                'Grade 2': ['English','Mathematics','Filipino','Science','Araling Panlipunan'],
                'Grade 3': ['English','Mathematics','Filipino','Science','Edukasyon sa Pagpapakatao (EsP)'],
                'Grade 4': ['English','Mathematics','Filipino','Science','Araling Panlipunan'],
                'Grade 5': ['English','Mathematics','Filipino','Science','Edukasyon sa Pagpapakatao (EsP)'],
                'Grade 6': ['English','Mathematics','Filipino','Science','Araling Panlipunan']
            };
            const gradeSel = document.getElementById('grade_level');
            const list = document.getElementById('subjectsPreview');
            function render(){
                if (!list) return;
                list.innerHTML = '';
                const g = gradeSel && gradeSel.value;
                const items = subjectsMap[g] || [];
                items.forEach(s => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item px-2 py-1';
                    li.textContent = s;
                    list.appendChild(li);
                });
            }
            if (gradeSel) { gradeSel.addEventListener('change', render); render(); }
        })();
    </script>
</body>
</html>
<?php $conn->close(); ?>
