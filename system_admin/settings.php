<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

if (($_SESSION['role'] ?? '') !== 'system_admin') {
    header('location: ../index.php');
    exit;
}

// Ensure settings table exists
$conn->query("CREATE TABLE IF NOT EXISTS app_settings (\n  k VARCHAR(100) PRIMARY KEY,\n  v TEXT NOT NULL,\n  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function get_setting($conn, $key, $default='') {
    $val = $default;
    if ($stmt = $conn->prepare("SELECT v FROM app_settings WHERE k=? LIMIT 1")) {
        $stmt->bind_param('s', $key);
        if ($stmt->execute()) { $stmt->bind_result($val); $stmt->fetch(); }
        $stmt->close();
    }
    return ($val === null || $val === '') ? $default : $val;
}

function set_setting($conn, $key, $val) {
    if ($stmt = $conn->prepare("INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v=VALUES(v)")) {
        $stmt->bind_param('ss', $key, $val);
        $stmt->execute();
        $stmt->close();
    }
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $default_sy = trim($_POST['default_school_year'] ?? '');
    $auto_remarks = isset($_POST['auto_remarks_enabled']) ? '1' : '0';
    $header = trim($_POST['report_card_header'] ?? '');
    $logo = trim($_POST['report_card_logo_url'] ?? '');
    $logo_h = trim($_POST['report_card_logo_height'] ?? '120');
    $title_sz = trim($_POST['report_card_title_size'] ?? '20');
    set_setting($conn, 'default_school_year', $default_sy);
    set_setting($conn, 'auto_remarks_enabled', $auto_remarks);
    set_setting($conn, 'report_card_header', $header);
    set_setting($conn, 'report_card_logo_url', $logo);
    set_setting($conn, 'report_card_logo_height', $logo_h);
    set_setting($conn, 'report_card_title_size', $title_sz);
    $success_msg = 'Settings saved.';
}

$default_sy = get_setting($conn, 'default_school_year', '');
$auto_remarks = get_setting($conn, 'auto_remarks_enabled', '1');
$header = get_setting($conn, 'report_card_header', '');
$logo = get_setting($conn, 'report_card_logo_url', '');
$logo_h = get_setting($conn, 'report_card_logo_height', '120');
$title_sz = get_setting($conn, 'report_card_title_size', '20');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | MARC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>.form-text small{color:#6c757d;}</style>
    <link rel="icon" type="image/png" href="/MARC/image/marclogo.png">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div id="content">
        <?php include '../includes/navbar.php'; ?>
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">System Settings</h2>
            </div>

            <?php if ($success_msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>
            <?php if ($error_msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

            <form method="post" class="card">
                <div class="card-header bg-white">General</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Default School Year</label>
                            <input type="text" name="default_school_year" class="form-control" placeholder="2025-2026" value="<?php echo htmlspecialchars($default_sy); ?>">
                            <div class="form-text">Used as a default when adding grades.</div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="auto_remarks_enabled" id="autoRemarks" <?php echo ($auto_remarks==='1')?'checked':''; ?>>
                                <label class="form-check-label" for="autoRemarks">Enable auto-remarks (DepEd descriptors)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-header bg-white border-top">Report Card</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Header Text</label>
                            <input type="text" name="report_card_header" class="form-control" value="<?php echo htmlspecialchars($header); ?>" placeholder="School Name and Address">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Logo URL</label>
                            <input type="text" name="report_card_logo_url" class="form-control" value="<?php echo htmlspecialchars($logo); ?>" placeholder="https://.../logo.png">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Logo Height (px)</label>
                            <input type="number" name="report_card_logo_height" class="form-control" min="60" max="240" step="1" value="<?php echo htmlspecialchars($logo_h); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Title Font Size (px)</label>
                            <input type="number" name="report_card_title_size" class="form-control" min="14" max="36" step="1" value="<?php echo htmlspecialchars($title_sz); ?>">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>
