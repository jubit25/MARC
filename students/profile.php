<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

if (($_SESSION['role'] ?? '') !== 'students') {
    header('location: ../index.php');
    exit;
}

$user_id = (int)($_SESSION['id'] ?? 0);
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

// Change password handling
$success_msg = '';
$error_msg = '';

// Load current profile info
$email = '';
$first_name = '';
$last_name = '';
$stu_student_id = '';
$stu_grade_level = '';
$stu_section = '';
$stu_birth_date = '';
$stu_address = '';
$stu_parent_name = '';
$stu_parent_contact = '';

if ($user_id > 0) {
    $qry = 'SELECT u.email, u.first_name, u.last_name, s.student_id, s.grade_level, s.section, s.birth_date, s.address, s.parent_name, s.parent_contact
            FROM users u
            LEFT JOIN students s ON s.user_id = u.id
            WHERE u.id = ?
            LIMIT 1';
    if ($stmt = $conn->prepare($qry)) {
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            $stmt->bind_result($email, $first_name, $last_name, $stu_student_id, $stu_grade_level, $stu_section, $stu_birth_date, $stu_address, $stu_parent_name, $stu_parent_contact);
            $stmt->fetch();
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validations
    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $error_msg = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error_msg = 'New password and confirm password do not match.';
    } elseif (strlen($new_password) < 8) {
        $error_msg = 'New password must be at least 8 characters long.';
    }

    if ($error_msg === '') {
        // Fetch current hashed password
        $sql = 'SELECT password FROM users WHERE id = ? LIMIT 1';
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $user_id);
            if ($stmt->execute()) {
                $stmt->bind_result($hashed);
                if ($stmt->fetch()) {
                    if (!password_verify($current_password, $hashed)) {
                        $error_msg = 'Current password is incorrect.';
                    }
                } else {
                    $error_msg = 'User not found.';
                }
            } else {
                $error_msg = 'Failed to verify current password. Please try again.';
            }
            $stmt->close();
        } else {
            $error_msg = 'Database error. Please try again later.';
        }
    }

    if ($error_msg === '') {
        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = 'UPDATE users SET password = ? WHERE id = ?';
        if ($stmt2 = $conn->prepare($upd)) {
            $stmt2->bind_param('si', $new_hash, $user_id);
            if ($stmt2->execute()) {
                $success_msg = 'Password updated successfully.';
            } else {
                $error_msg = 'Failed to update password. Please try again.';
            }
            $stmt2->close();
        } else {
            $error_msg = 'Database error. Please try again later.';
        }
    }
}

// Update profile handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_email = trim($_POST['email'] ?? '');
    $new_first = trim($_POST['first_name'] ?? '');
    $new_last = trim($_POST['last_name'] ?? '');
    $new_birth = trim($_POST['birth_date'] ?? '');
    $new_address = trim($_POST['address'] ?? '');
    $new_parent_name = trim($_POST['parent_name'] ?? '');
    $new_parent_contact = trim($_POST['parent_contact'] ?? '');

    if ($new_email === '' || $new_first === '' || $new_last === '') {
        $error_msg = 'Email, First name, and Last name are required.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please provide a valid email address.';
    }

    if ($error_msg === '') {
        $ok = true;
        $updUser = 'UPDATE users SET email = ?, first_name = ?, last_name = ? WHERE id = ?';
        if ($stmt1 = $conn->prepare($updUser)) {
            $stmt1->bind_param('sssi', $new_email, $new_first, $new_last, $user_id);
            if (!$stmt1->execute()) {
                $ok = false;
            }
            $stmt1->close();
        } else {
            $ok = false;
        }

        $updStud = 'UPDATE students SET birth_date = ?, address = ?, parent_name = ?, parent_contact = ? WHERE user_id = ?';
        if ($stmt2 = $conn->prepare($updStud)) {
            $birthParam = ($new_birth !== '') ? $new_birth : null;
            $stmt2->bind_param('ssssi', $birthParam, $new_address, $new_parent_name, $new_parent_contact, $user_id);
            if (!$stmt2->execute()) {
                $ok = false;
            }
            $stmt2->close();
        } else {
            $ok = false;
        }

        if ($ok) {
            $success_msg = 'Profile updated successfully.';
            // refresh loaded values
            $email = $new_email;
            $first_name = $new_first;
            $last_name = $new_last;
            $stu_birth_date = $new_birth;
            $stu_address = $new_address;
            $stu_parent_name = $new_parent_name;
            $stu_parent_contact = $new_parent_contact;
        } else {
            $error_msg = 'Failed to update profile. Please try again.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | MARC</title>
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
                <h2 class="mb-0">My Profile</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Account Information</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($success_msg && (!isset($_POST['action']) || $_POST['action'] === 'update_profile')): ?>
                                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success_msg); ?></div>
                            <?php endif; ?>
                            <?php if ($error_msg && (!isset($_POST['action']) || $_POST['action'] === 'update_profile')): ?>
                                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error_msg); ?></div>
                            <?php endif; ?>

                            <form method="post" action="">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($username); ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role))); ?>" disabled>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3 mt-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>

                                <hr>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Student ID</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($stu_student_id); ?>" disabled>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Grade Level</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($stu_grade_level); ?>" disabled>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Section</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($stu_section); ?>" disabled>
                                    </div>
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <label class="form-label">Birth Date</label>
                                        <input type="date" name="birth_date" class="form-control" value="<?php echo htmlspecialchars($stu_birth_date); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Parent/Guardian Name</label>
                                        <input type="text" name="parent_name" class="form-control" value="<?php echo htmlspecialchars($stu_parent_name); ?>">
                                    </div>
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <label class="form-label">Parent Contact</label>
                                        <input type="text" name="parent_contact" class="form-control" value="<?php echo htmlspecialchars($stu_parent_contact); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Address</label>
                                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($stu_address); ?>">
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-white" id="security">
                            <h6 class="mb-0">Security</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($success_msg): ?>
                                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success_msg); ?></div>
                            <?php endif; ?>
                            <?php if ($error_msg): ?>
                                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error_msg); ?></div>
                            <?php endif; ?>

                            <form method="post" action="">
                                <input type="hidden" name="action" value="change_password">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                                    <div class="form-text">Minimum 8 characters.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-shield-lock me-2"></i>Change Password
                                </button>
                            </form>
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
