<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$role_path = ($role === 'admin') ? 'registrar' : $role;
?>

<!-- Sidebar -->
<nav id="sidebar" class="active">
    <div class="sidebar-header">
        <h3>MARC AGAPE</h3>
        <p class="text-muted small mb-0">School Management System</p>
    </div>

    <ul class="list-unstyled components">
        <li class="mb-2">
            <a href="/MARC/<?php echo $role_path; ?>/dashboard.php" class="d-flex align-items-center <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2 me-2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <?php if ($role == 'system_admin'): ?>
        <li class="mb-2">
            <a href="#adminSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle d-flex align-items-center">
                <i class="bi bi-people me-2"></i>
                <span>User Management</span>
            </a>
            <ul class="collapse list-unstyled" id="adminSubmenu">
                <li>
                    <a href="/MARC/system_admin/manage_admins.php" class="d-flex align-items-center <?php echo ($current_page == 'manage_admins.php') ? 'active' : ''; ?>">
                        <i class="bi bi-chevron-right me-2"></i>
                        <span>Manage Admins</span>
                    </a>
                </li>
                <li>
                    <a href="/MARC/system_admin/manage_students.php" class="d-flex align-items-center <?php echo ($current_page == 'manage_students.php') ? 'active' : ''; ?>">
                        <i class="bi bi-chevron-right me-2"></i>
                        <span>Manage Students</span>
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>
        
        <?php if ($role == 'registrar' || $role == 'system_admin'): ?>
        <li class="mb-2">
            <a href="#paymentSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle d-flex align-items-center">
                <i class="bi bi-cash-coin me-2"></i>
                <span>Payments</span>
            </a>
            <ul class="collapse list-unstyled" id="paymentSubmenu">
                <li>
                    <a href="/MARC/<?php echo $role_path; ?>/payments.php" class="d-flex align-items-center <?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>">
                        <i class="bi bi-chevron-right me-2"></i>
                        <span>Record Payment</span>
                    </a>
                </li>
                <li>
                    <a href="/MARC/<?php echo $role_path; ?>/payments.php#history" class="d-flex align-items-center">
                        <i class="bi bi-chevron-right me-2"></i>
                        <span>Payment History</span>
                    </a>
                </li>
            </ul>
        </li>

        <?php if ($role == 'registrar' || $role == 'system_admin'): ?>
        <li class="mb-2">
            <a href="/MARC/<?php echo $role_path; ?>/manage_students.php" class="d-flex align-items-center <?php echo ($current_page == 'manage_students.php') ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i>
                <span>Manage Students</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="mb-2">
            <a href="#gradeSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle d-flex align-items-center">
                <i class="bi bi-journal-text me-2"></i>
                <span>Grades</span>
            </a>
            <ul class="collapse list-unstyled" id="gradeSubmenu">
                <li>
                    <a href="/MARC/<?php echo $role_path; ?>/grades.php" class="d-flex align-items-center <?php echo ($current_page == 'grades.php') ? 'active' : ''; ?>">
                        <i class="bi bi-chevron-right me-2"></i>
                        <span>Manage Grades</span>
                    </a>
                </li>
                <li>
                    <a href="/MARC/<?php echo $role_path; ?>/reports.php" class="d-flex align-items-center <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                        <i class="bi bi-chevron-right me-2"></i>
                        <span>Grade Reports</span>
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>
        
        <?php if ($role == 'students'): ?>
        <li class="mb-2">
            <a href="/MARC/students/my_grades.php" class="d-flex align-items-center <?php echo ($current_page == 'my_grades.php') ? 'active' : ''; ?>">
                <i class="bi bi-journal-text me-2"></i>
                <span>My Grades</span>
            </a>
        </li>
        <li class="mb-2">
            <a href="/MARC/students/my_payments.php" class="d-flex align-items-center <?php echo ($current_page == 'my_payments.php') ? 'active' : ''; ?>">
                <i class="bi bi-credit-card me-2"></i>
                <span>My Payments</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li class="mb-2">
            <a href="/MARC/<?php echo $role_path; ?>/profile.php" class="d-flex align-items-center <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="bi bi-person me-2"></i>
                <span>My Profile</span>
            </a>
        </li>
        
        <li class="mt-4">
            <a href="/MARC/php/logout.php" class="d-flex align-items-center text-danger">
                <i class="bi bi-box-arrow-right me-2"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Toggle Button -->
<button type="button" id="sidebarToggleFloat" class="btn btn-outline-secondary position-fixed">
    <i class="bi bi-list"></i>
</button>
