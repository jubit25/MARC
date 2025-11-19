<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

if ($_SESSION["role"] !== 'system_admin') {
    header("location: ../index.php");
    exit;
}
 
$error = '';
$success = '';
$isAdd = isset($_GET['action']) && $_GET['action'] === 'add';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $role = 'registrar';
    $allowed_roles = ['registrar'];

    if ($username === '' || $password === '' || $email === '' || $first_name === '' || $last_name === '' || !in_array($role, $allowed_roles, true)) {
        $error = 'Please provide username, password, email, first name, last name, and a valid role.';
    } else {
        // Check if username already exists to avoid duplicate key error
        if ($chk = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1')) {
            $chk->bind_param('s', $username);
            if ($chk->execute()) {
                $res = $chk->get_result();
                if ($res && $res->num_rows > 0) {
                    $error = 'Username already exists. Please choose another.';
                }
            }
            $chk->close();
        }
        if ($error === '') {
            $stmt = $conn->prepare('INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt->bind_param('ssssss', $username, $hashed, $email, $first_name, $last_name, $role);
                if ($stmt->execute()) {
                    header('Location: manage_admins.php');
                    exit;
                } else {
                    $error = 'Failed to create admin. Username or email may already exist.';
                }
                $stmt->close();
            } else {
                $error = 'Failed to prepare create statement.';
            }
        }
    }
}

$admins = [];
$sql = "SELECT id, username, role FROM users WHERE role IN ('system_admin', 'registrar', 'admin') ORDER BY role, username";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Administrators | MARC</title>
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
                    <h2 class="mb-0"><?php echo $isAdd ? 'Add New Registrar' : 'Manage Administrators'; ?></h2>
                    <?php if ($isAdd): ?>
                        <a href="manage_admins.php" class="btn btn-outline-secondary">Cancel</a>
                    <?php else: ?>
                        <a href="manage_admins.php?action=add" class="btn btn-primary"><i class="bi bi-person-plus me-2"></i>Add Registrar</a>
                    <?php endif; ?>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($isAdd): ?>
                    <div class="card">
                        <div class="card-body">
                            <form action="manage_admins.php?action=add" method="post" class="row g-3">
                                <input type="hidden" name="action" value="add">
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" required>
                                </div>
                                <input type="hidden" name="role" value="registrar">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Create Registrar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Username</th>
                                            <th scope="col">Role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($admins) === 0): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No administrators found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($admins as $index => $admin): ?>
                                                <tr>
                                                    <th scope="row"><?php echo $index + 1; ?></th>
                                                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                                    <td><?php echo htmlspecialchars(str_replace('_', ' ', $admin['role'])); ?></td>
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
</body>
</html>
<?php $conn->close(); ?>
