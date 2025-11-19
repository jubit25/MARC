<?php
require_once '../php/db_connect.php';

$lrn = $password = $email = $first_name = $last_name = $grade_level = '';
$section = $birth_date = $address = $parent_name = $parent_contact = '';
$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lrn = trim($_POST['lrn'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $grade_level = trim($_POST['grade_level'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_contact = trim($_POST['parent_contact'] ?? '');

    if ($lrn === '' || $password === '' || $email === '' || $first_name === '' || $last_name === '' || $grade_level === '') {
        $error_msg = 'Please fill in all required fields (*).';
    } else {
        // Check if username/LRN already exists
        $check = $conn->prepare('SELECT id FROM users WHERE username = ?');
        $check->bind_param('s', $lrn);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error_msg = 'An account with this LRN already exists.';
        } else {
            $conn->begin_transaction();
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                // username is the LRN
                $stmtUser = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, 'students')");
                $stmtUser->bind_param('sssss', $lrn, $hashed, $email, $first_name, $last_name);
                if (!$stmtUser->execute()) {
                    throw new Exception('Failed to create user: ' . $stmtUser->error);
                }
                $user_id = $stmtUser->insert_id;
                $stmtUser->close();

                // LRN also stored as students.student_id
                $stmtStud = $conn->prepare('INSERT INTO students (user_id, student_id, grade_level, section, birth_date, address, parent_name, parent_contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmtStud->bind_param('isssssss', $user_id, $lrn, $grade_level, $section, $birth_date, $address, $parent_name, $parent_contact);
                if (!$stmtStud->execute()) {
                    throw new Exception('Failed to create student profile: ' . $stmtStud->error);
                }
                $stmtStud->close();

                $conn->commit();
                $success_msg = 'Account created successfully. You may now log in.';
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = $e->getMessage();
            }
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - MARC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/MARC/image/marclogo.png">
</head>
<body>
<div class="container py-5">
    <div class="mx-auto" style="max-width: 720px;">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="mb-3">Student Registration</h3>
                <p class="text-muted mb-4">Use your LRN as your username. Fields marked with * are required.</p>

                <?php if ($error_msg): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>
                <?php if ($success_msg): ?>
                    <div class="alert alert-success d-flex justify-content-between align-items-center">
                        <span><?php echo htmlspecialchars($success_msg); ?></span>
                        <a class="btn btn-success btn-sm" href="../index.php">Go to Login</a>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">LRN (Username)*</label>
                            <input type="text" name="lrn" class="form-control" value="<?php echo htmlspecialchars($lrn); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password*</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email*</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">First Name*</label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name*</label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Grade Level*</label>
                            <input type="text" name="grade_level" class="form-control" value="<?php echo htmlspecialchars($grade_level); ?>" placeholder="e.g., Grade 1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Section</label>
                            <input type="text" name="section" class="form-control" value="<?php echo htmlspecialchars($section); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Birth Date</label>
                            <input type="date" name="birth_date" class="form-control" value="<?php echo htmlspecialchars($birth_date); ?>">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($address); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Parent/Guardian Name</label>
                            <input type="text" name="parent_name" class="form-control" value="<?php echo htmlspecialchars($parent_name); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parent Contact</label>
                            <input type="text" name="parent_contact" class="form-control" value="<?php echo htmlspecialchars($parent_contact); ?>">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Account</button>
                        <a href="../index.php" class="btn btn-outline-secondary">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
