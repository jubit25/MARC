<?php
require_once '../php/auth.php';
require_once '../php/db_connect.php';

// Only registrar or system admin can access
if (!in_array($_SESSION["role"] ?? '', ['registrar', 'system_admin', 'admin', 'school_admin'])) {
    header("location: ../index.php");
    exit;
}

$__EMBED = isset($_GET['embed']) && $_GET['embed'] === '1';

$payments = [];
$total_payments = 0;
$total_balance = 0.0;
$categories = [];
$studentsList = [];
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';

$result = $conn->query("SELECT COUNT(*) AS c FROM payments");
if ($result) {
    $row = $result->fetch_assoc();
    $total_payments = (int)($row['c'] ?? 0);
}

$result = $conn->query(
    "SELECT SUM(GREATEST(ps.amount_due - COALESCE(p.total_paid,0), 0)) AS balance
     FROM payment_schedule ps
     LEFT JOIN (
        SELECT student_id, payment_category_id, SUM(amount_paid) AS total_paid
        FROM payments
        GROUP BY student_id, payment_category_id
     ) p ON p.student_id = ps.student_id AND p.payment_category_id = ps.payment_category_id
     WHERE ps.status != 'paid'"
);
if ($result) {
    $row = $result->fetch_assoc();
    $total_balance = (float)($row['balance'] ?? 0);
}

// Load recent payments with optional single date filter and include student name and category
$where = [];
$params = [];
$types = '';
if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $where[] = 'p.payment_date = ?';
    $types .= 's';
    $params[] = $filterDate;
}
$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
$sqlRecent = "SELECT p.id, p.amount_paid, p.payment_date, pc.name AS category_name, u.first_name, u.last_name
              FROM payments p
              JOIN payment_categories pc ON pc.id = p.payment_category_id
              JOIN students s ON s.id = p.student_id
              JOIN users u ON u.id = s.user_id
              $whereSql
              ORDER BY p.payment_date DESC, p.id DESC
              LIMIT 100";
if (count($params) > 0) {
    if ($stmt = $conn->prepare($sqlRecent)) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) { $payments[] = $row; }
        }
        $stmt->close();
    }
} else {
    $res = $conn->query($sqlRecent);
    if ($res) { while ($row = $res->fetch_assoc()) { $payments[] = $row; } }
}

$resCat = $conn->query("SELECT id, name, description, amount, is_recurring, frequency FROM payment_categories ORDER BY name");
if ($resCat) {
    while ($row = $resCat->fetch_assoc()) { $categories[] = $row; }
}

$resStudents = $conn->query("SELECT s.id, u.first_name, u.last_name, u.username, s.grade_level, s.section FROM students s JOIN users u ON u.id = s.user_id ORDER BY u.last_name, u.first_name");
if ($resStudents) {
    while ($row = $resStudents->fetch_assoc()) { $studentsList[] = $row; }
}
// Load grade levels and sections for quick filters
$gradeLevels = [];
if ($res = $conn->query("SELECT DISTINCT grade_level FROM students WHERE grade_level IS NOT NULL AND grade_level<>'' ORDER BY grade_level")) {
    while ($r = $res->fetch_assoc()) { $gradeLevels[] = $r['grade_level']; }
}
$sections = [];
if ($res = $conn->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL AND section<>'' ORDER BY section")) {
    while ($r = $res->fetch_assoc()) { $sections[] = $r['section']; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments | MARC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/MARC/system_admin/assets/css/style.css" rel="stylesheet">
    <?php if ($__EMBED): ?>
    <style>
      body.embed nav.navbar { display:none !important; }
      body.embed #sidebar { display:none !important; }
      body.embed .wrapper { padding-left: 0 !important; }
      body.embed #content > .container-fluid > .d-flex:first-child { display:none !important; }
    </style>
    <?php endif; ?>
    <link rel="icon" type="image/png" href="/MARC/image/marclogo.png">
</head>
<body class="topnav <?php echo $__EMBED ? 'embed' : ''; ?>">
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../includes/navbar.php'; ?>
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Payments</h2>
                    <div class="d-flex gap-2">
                        <?php if (!$__EMBED): ?>
                        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
                        <?php endif; ?>
                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalRecordPayment"><i class="bi bi-credit-card me-2"></i>Record Payment</button>
                        <a href="../php/payments_export.php<?php echo $filterDate !== '' ? ('?date=' . urlencode($filterDate)) : ''; ?>" class="btn btn-outline-secondary">Export</a>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-warning bg-opacity-25">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small text-muted">Total Payments</div>
                                    <div class="h3 mb-0"><?php echo $total_payments; ?></div>
                                </div>
                                <i class="bi bi-credit-card fs-1 text-warning"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-danger bg-opacity-25">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small text-muted">Total Balance</div>
                                    <div class="h3 mb-0">₱<?php echo number_format($total_balance, 2); ?></div>
                                </div>
                                <i class="bi bi-cash-coin fs-1 text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Manage Payment Items</h6>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAddCategory"><i class="bi bi-plus-lg me-1"></i>Add</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Amount</th>
                                                <th>Frequency</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($categories) === 0): ?>
                                                <tr><td colspan="4" class="text-center text-muted">No items.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($categories as $cat): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                                        <td>₱<?php echo number_format((float)$cat['amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($cat['frequency']); ?></td>
                                                        <td class="text-end">
                                                            <button class="btn btn-sm btn-outline-primary" onclick='openEditCategory(<?php echo json_encode($cat, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>Edit</button>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(<?php echo (int)$cat['id']; ?>)">Delete</button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">Assign Payment To Students</h6>
                            </div>
                            <div class="card-body">
                                <form id="assignForm">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Payment Item</label>
                                            <select class="form-select" name="payment_category_id" required>
                                                <option value="">Select an item</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?> (₱<?php echo number_format((float)$cat['amount'], 2); ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Due Date</label>
                                            <input type="date" name="due_date" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Amount Due</label>
                                            <input type="number" name="amount_due" class="form-control" step="0.01" min="0" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Students</label>
                                            <div class="row g-2 mb-1">
                                                <div class="col-sm-6">
                                                    <select id="filterGrade" class="form-select">
                                                        <option value="">All Grade Levels</option>
                                                        <?php foreach ($gradeLevels as $gl): ?>
                                                            <option value="<?php echo htmlspecialchars($gl); ?>"><?php echo htmlspecialchars($gl); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-sm-6">
                                                    <select id="filterSection" class="form-select">
                                                        <option value="">All Sections</option>
                                                        <?php foreach ($sections as $sc): ?>
                                                            <option value="<?php echo htmlspecialchars($sc); ?>"><?php echo htmlspecialchars($sc); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="input-group mb-2">
                                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                <input type="search" id="studentSearch" class="form-control" placeholder="Search by name or username">
                                                <button class="btn btn-outline-secondary" type="button" id="clearStudentSearch">Clear</button>
                                            </div>
                                            <small class="text-muted d-block mb-2" id="studentHint">Type to search students. The list is hidden until you start typing.</small>
                                            <div id="selectedStudentsWrap" class="mb-2" style="display:none">
                                                <div class="d-flex align-items-center mb-1">
                                                    <strong class="me-2">Selected</strong>
                                                    <small class="text-muted" id="selectedCount"></small>
                                                </div>
                                                <div id="selectedStudentsChips" class="d-flex flex-wrap gap-2"></div>
                                            </div>
                                            <div class="form-check mb-2" id="selectAllRow" style="display:none">
                                                <input class="form-check-input" type="checkbox" id="toggleAllFiltered">
                                                <label class="form-check-label" for="toggleAllFiltered">Select all filtered</label>
                                            </div>
                                            <div class="border rounded p-2" style="max-height:280px; overflow:auto; display:none" id="studentList">
                                                <?php foreach ($studentsList as $s): ?>
                                                    <?php $display = ($s['last_name'] ?? '').', '.($s['first_name'] ?? '').' ('.($s['username'] ?? '').')'; ?>
                                                    <div class="form-check student-item" data-text="<?php echo htmlspecialchars(strtolower($display)); ?>" data-grade="<?php echo htmlspecialchars(strtolower($s['grade_level'] ?? '')); ?>" data-section="<?php echo htmlspecialchars(strtolower($s['section'] ?? '')); ?>">
                                                        <input class="form-check-input" type="checkbox" name="student_ids[]" value="<?php echo (int)$s['id']; ?>" id="stud_<?php echo (int)$s['id']; ?>">
                                                        <label class="form-check-label" for="stud_<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($display); ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <small class="text-muted" id="studentResultsInfo" style="display:none"></small>
                                        </div>
                                    </div>
                                    <div class="mt-3 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Assign</button>
                                        <button type="reset" class="btn btn-secondary">Clear</button>
                                    </div>
                                </form>
                                <div id="assignMsg" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" id="history">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Recent Payments</h6>
                        <form class="d-flex gap-2" method="get" action="payments.php">
                            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterDate); ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
                            <a class="btn btn-sm btn-outline-secondary" href="payments.php">Reset</a>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Student</th>
                                        <th scope="col">Item</th>
                                        <th scope="col">Amount Paid</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($payments) === 0): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No payments found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($payments as $index => $pay): ?>
                                            <tr>
                                                <th scope="row"><?php echo $index + 1; ?></th>
                                                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($pay['payment_date']))); ?></td>
                                                <td><?php echo htmlspecialchars(($pay['last_name'] ?? '') . ', ' . ($pay['first_name'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars($pay['category_name'] ?? ''); ?></td>
                                                <td>₱<?php echo number_format((float)$pay['amount_paid'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/MARC/system_admin/assets/js/script.js"></script>

    <!-- Record Payment Modal -->
    <div class="modal fade" id="modalRecordPayment" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="recordPaymentForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <select name="student_id" class="form-select" required>
                                <option value="">Select student</option>
                                <?php foreach ($studentsList as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['last_name'] . ', ' . $s['first_name'] . ' (' . $s['username'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Item</label>
                            <select name="payment_category_id" class="form-select" required>
                                <option value="">Select item</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?> (₱<?php echo number_format((float)$cat['amount'], 2); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Amount Paid</label>
                                <input type="number" name="amount_paid" class="form-control" step="0.01" min="0.01" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Date</label>
                                <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="online">Online</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference #</label>
                                <input type="text" name="reference_number" class="form-control">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div id="recordMsg" class="mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning">Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="modalAddCategory" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createCategoryForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Payment Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control"></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Frequency</label>
                                <select name="frequency" class="form-select">
                                    <option value="one_time">One time</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_recurring" id="add_is_recurring">
                                    <label class="form-check-label" for="add_is_recurring">Recurring</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="modalEditCategory" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editCategoryForm">
                    <input type="hidden" name="id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Payment Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control"></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Frequency</label>
                                <select name="frequency" class="form-select">
                                    <option value="one_time">One time</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_recurring" id="edit_is_recurring">
                                    <label class="form-check-label" for="edit_is_recurring">Recurring</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    const apiUrl = '../php/payments_api.php';

    function formToFD(form){
        const fd = new FormData(form);
        return fd;
    }

    // Create category
    document.getElementById('createCategoryForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = formToFD(e.target);
        fd.append('action', 'create_category');
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.ok){ location.reload(); } else { alert(data.message || 'Failed'); }
    });

    // Edit category modal open
    window.openEditCategory = (cat) => {
        const m = new bootstrap.Modal(document.getElementById('modalEditCategory'));
        const f = document.getElementById('editCategoryForm');
        f.id.value = cat.id;
        f.name.value = cat.name;
        f.description.value = cat.description || '';
        f.amount.value = cat.amount;
        f.frequency.value = cat.frequency;
        f.is_recurring.checked = (parseInt(cat.is_recurring) === 1 || cat.is_recurring === true);
        m.show();
    };

    // Update category
    document.getElementById('editCategoryForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = formToFD(e.target);
        fd.append('action', 'update_category');
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.ok){ location.reload(); } else { alert(data.message || 'Failed'); }
    });

    // Delete category
    window.deleteCategory = async (id) => {
        if(!confirm('Delete this payment item?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_category');
        fd.append('id', id);
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.ok){ location.reload(); } else { alert(data.message || 'Failed'); }
    };

    // Assign payments
    document.getElementById('assignForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = document.getElementById('assignMsg');
        msg.innerHTML = '';
        const fd = formToFD(e.target);
        fd.append('action', 'assign_payments');
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.ok){
            msg.innerHTML = '<div class="alert alert-success">'+data.message+'</div>';
            e.target.reset();
        } else {
            msg.innerHTML = '<div class="alert alert-danger">'+(data.message||'Failed')+'</div>';
        }
    });

    // Record payment
    document.getElementById('recordPaymentForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = document.getElementById('recordMsg');
        msg.innerHTML = '';
        const fd = formToFD(e.target);
        fd.append('action', 'record_payment');
        const res = await fetch(apiUrl, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.ok){
            msg.innerHTML = '<div class="alert alert-success">'+data.message+'</div>';
            e.target.reset();
        } else {
            msg.innerHTML = '<div class="alert alert-danger">'+(data.message||'Failed')+'</div>';
        }
    });

    // Students filter + select-all-visible for Assign Payment
    (function(){
        const searchInput = document.getElementById('studentSearch');
        const clearBtn = document.getElementById('clearStudentSearch');
        const list = document.getElementById('studentList');
        const toggleAll = document.getElementById('toggleAllFiltered');
        const chipsWrap = document.getElementById('selectedStudentsWrap');
        const chips = document.getElementById('selectedStudentsChips');
        const chipsCount = document.getElementById('selectedCount');

        const selected = new Map(); // id -> {id, label}

        function visibleItems(){
            return Array.from(list?.querySelectorAll('.student-item') || []).filter(el => el.style.display !== 'none' && !el.querySelector('input')?.checked);
        }
        function norm(s){ return (s||'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim(); }
        function syncSelectedFromDOM(){
            (list?.querySelectorAll('.student-item input.form-check-input')||[]).forEach(cb => {
                const id = cb.value;
                const label = cb.parentElement?.querySelector('label')?.textContent || '';
                if (cb.checked) selected.set(id, {id, label}); else selected.delete(id);
            });
            renderChips();
        }
        function renderChips(){
            if (!chips || !chipsWrap) return;
            chips.innerHTML = '';
            const arr = Array.from(selected.values());
            if (arr.length === 0){ chipsWrap.style.display = 'none'; return; }
            chipsWrap.style.display = '';
            if (chipsCount) chipsCount.textContent = `(${arr.length})`;
            arr.forEach(({id, label}) => {
                const span = document.createElement('span');
                span.className = 'badge rounded-pill bg-light text-dark border';
                span.innerHTML = `${label} <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" aria-label="Remove" data-id="${id}">×</button>`;
                chips.appendChild(span);
            });
        }

        function applyFilter(){
            if(!list) return;
            const q = norm(searchInput?.value || '');
            const tokens = q.length ? q.split(/\s+/).filter(Boolean) : [];
            const show = tokens.length > 0;
            const hint = document.getElementById('studentHint');
            const selAllRow = document.getElementById('selectAllRow');
            const info = document.getElementById('studentResultsInfo');
            if (hint) hint.style.display = show ? 'none' : '';
            if (list) list.style.display = show ? '' : 'none';
            if (selAllRow) selAllRow.style.display = show ? '' : 'none';
            const MAX = 100;
            let matchCount = 0, shown = 0;
            list.querySelectorAll('.student-item').forEach(item => {
                const hay = norm(item.dataset.text || '');
                const ok = tokens.length === 0 || tokens.every(t => hay.includes(t));
                const isSelected = item.querySelector('input')?.checked;
                if (ok && !isSelected) {
                    matchCount++;
                    if (shown < MAX) {
                        item.style.display = '';
                        shown++;
                    } else {
                        item.style.display = 'none';
                    }
                } else {
                    item.style.display = 'none';
                }
            });
            if (info) {
                if (!show) { info.style.display = 'none'; info.textContent=''; }
                else {
                    info.style.display = '';
                    info.textContent = matchCount > MAX
                        ? `Showing ${shown} of ${matchCount} results. Refine your search to narrow further.`
                        : `${matchCount} result${matchCount===1?'':'s'} found.`;
                }
            }
            syncSelectedFromDOM();
        }
        searchInput?.addEventListener('input', applyFilter);
        clearBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            applyFilter();
        });
        toggleAll?.addEventListener('change', () => {
            visibleItems().forEach(item => {
                const cb = item.querySelector('input.form-check-input');
                if (cb) cb.checked = toggleAll.checked;
            });
            syncSelectedFromDOM();
        });
        list?.addEventListener('change', (e)=>{
            if (e.target && e.target.matches('input.form-check-input')){
                syncSelectedFromDOM();
                applyFilter(); // hide selected from list
            }
        });
        chips?.addEventListener('click', (e)=>{
            const btn = e.target.closest('button[data-id]');
            if (!btn) return;
            const id = btn.getAttribute('data-id');
            // uncheck corresponding checkbox
            const cb = list?.querySelector(`input.form-check-input[value="${CSS.escape(id)}"]`);
            if (cb) { cb.checked = false; }
            selected.delete(id);
            renderChips();
            applyFilter();
        });
        // Clear selection on form reset
        document.getElementById('assignForm')?.addEventListener('reset', ()=>{
            selected.clear();
            renderChips();
            // hide list again until typing
            const hint = document.getElementById('studentHint');
            const selAllRow = document.getElementById('selectAllRow');
            if (hint) hint.style.display = '';
            if (list) list.style.display = 'none';
            if (selAllRow) selAllRow.style.display = 'none';
        });
        // initialize
        applyFilter();
    })();
    </script>
</body>
</html>
<?php $conn->close(); ?>
