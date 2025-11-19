<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: ../index.php");
    exit;
}

// Check user role and redirect if trying to access unauthorized pages
$current_page = basename($_SERVER['PHP_SELF']);
$allowed_pages = [
    'system_admin' => ['dashboard.php', 'manage_admins.php', 'manage_students.php', 'grades.php', 'reports.php', 'profile.php', 'settings.php', 'payments.php', 'payments_api.php', 'payments_export.php', 'report_card.php'],
    'registrar' => ['dashboard.php', 'manage_students.php', 'grades.php', 'reports.php', 'profile.php', 'payments.php'],
    // Legacy role alias: treat 'admin' same as 'registrar'
    'admin' => ['dashboard.php', 'manage_students.php', 'grades.php', 'reports.php', 'profile.php', 'payments.php'],
    // Legacy role alias: treat 'school_admin' same as 'registrar'
    'school_admin' => ['dashboard.php', 'manage_students.php', 'grades.php', 'reports.php', 'profile.php', 'payments.php'],
    'students' => ['dashboard.php', 'my_grades.php', 'my_payments.php', 'profile.php']
];

$user_role = $_SESSION["role"];
$base_dir = dirname(dirname($_SERVER['PHP_SELF']));
// Normalize legacy path for 'admin' to reuse registrar pages
$user_role_path = (in_array($user_role, ['admin','school_admin'], true)) ? 'registrar' : $user_role;

// If trying to access a page not in their allowed list, redirect to their dashboard
if (isset($allowed_pages[$user_role]) && !in_array($current_page, $allowed_pages[$user_role])) {
    $target = $base_dir . '/' . $user_role_path . '/dashboard.php';
    $current_path = $_SERVER['PHP_SELF'] ?? '';
    if ($current_path !== $target) {
        header("location: $target");
        exit;
    }
}
?>
