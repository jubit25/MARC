<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

if (($_SESSION['role'] ?? '') !== 'system_admin') {
    header('location: ../index.php');
    exit;
}

$user_id = (int)($_SESSION['id'] ?? 0);
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $error_msg = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error_msg = 'New password and confirm password do not match.';
    } elseif (strlen($new_password) < 8) {
        $error_msg = 'New password must be at least 8 characters long.';
    }

    if ($error_msg === '') {
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | MARC</title>
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
                <h2 class="mb-0">My Profile</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Account Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($username); ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role))); ?>" disabled>
                            </div>
                            <div class="alert alert-info d-flex align-items-center mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                <div>Profile editing is not yet implemented. This is a placeholder page.</div>
                            </div>
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
<script src="assets/js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>
