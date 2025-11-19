<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-fluid">
        <div class="d-flex align-items-center">
            <button type="button" id="sidebarCollapse" class="btn btn-link text-decoration-none d-lg-none me-3">
                <i class="bi bi-list fs-4"></i>
            </button>
            <h5 class="mb-0 d-none d-md-block">
                <?php 
                $role = $_SESSION['role'] ?? '';
                $role_path = ($role === 'admin') ? 'registrar' : $role;
                $page_title = '';
                $current_page = basename($_SERVER['PHP_SELF']);
                
                switch($current_page) {
                    case 'dashboard.php':
                        $page_title = 'Dashboard';
                        break;
                    case 'manage_admins.php':
                        $page_title = 'Manage Administrators';
                        break;
                    case 'manage_students.php':
                        $page_title = 'Manage Students';
                        break;
                    case 'payments.php':
                        $page_title = 'Payment Management';
                        break;
                    case 'grades.php':
                        $page_title = 'Grade Management';
                        break;
                    case 'my_grades.php':
                        $page_title = 'My Grades';
                        break;
                    case 'my_payments.php':
                        $page_title = 'My Payments';
                        break;
                    case 'profile.php':
                        $page_title = 'My Profile';
                        break;
                    default:
                        $page_title = 'Dashboard';
                }
                echo $page_title;
                ?>
            </h5>
        </div>
        <button class="navbar-toggler ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#topnavLinks" aria-controls="topnavLinks" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="topnavLinks">
            <ul class="navbar-nav ms-auto me-3 align-items-lg-center">
                <?php if (in_array($role, ['registrar','system_admin','admin'], true)): ?>
                    <li class="nav-item"><a class="nav-link<?php echo ($current_page=='dashboard.php')?' active':''; ?>" href="/<?php echo $role_path; ?>/dashboard.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link<?php echo ($current_page=='payments.php')?' active':''; ?>" href="/<?php echo $role_path; ?>/payments.php">Payments</a></li>
                    <li class="nav-item"><a class="nav-link<?php echo ($current_page=='grades.php')?' active':''; ?>" href="/<?php echo $role_path; ?>/grades.php">Grades</a></li>
                    <li class="nav-item d-none d-lg-block"><span class="mx-2 text-muted">•</span></li>
                    <li class="nav-item"><a class="btn btn-primary btn-sm" href="/MARC/<?php echo $role_path; ?>/reports.php">Reports</a></li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center">
            <!-- User Dropdown -->
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="me-2 d-none d-sm-block text-end">
                        <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION["username"]); ?></h6>
                        <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $_SESSION["role"])); ?></small>
                    </div>
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <?php 
                        $name_parts = explode(' ', $_SESSION["username"]);
                        $initials = '';
                        foreach ($name_parts as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                            if (strlen($initials) >= 2) break;
                        }
                        echo $initials;
                        ?>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userDropdown">
                    <li>
                        <a class="dropdown-item" href="/<?php echo $role_path; ?>/profile.php">
                            <i class="bi bi-person me-2"></i> My Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="/<?php echo $role_path; ?>/profile.php#security">
                            <i class="bi bi-gear me-2"></i> Settings
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="/php/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            </div>
        </div>
    </div>
</nav>
