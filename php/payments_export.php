<?php
require_once 'auth.php';
require_once 'db_connect.php';

if (!in_array($_SESSION["role"] ?? '', ['registrar', 'system_admin'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';
$where = [];
$params = [];
$types = '';
if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $where[] = 'p.payment_date = ?';
    $types .= 's';
    $params[] = $filterDate;
}
$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT 
            p.payment_date,
            u.last_name AS student_last,
            u.first_name AS student_first,
            u.username AS student_username,
            pc.name AS category_name,
            p.amount_paid,
            p.payment_method,
            p.reference_number,
            ru.last_name AS received_by_last,
            ru.first_name AS received_by_first,
            ru.username AS received_by_username
        FROM payments p
        JOIN payment_categories pc ON pc.id = p.payment_category_id
        JOIN students s ON s.id = p.student_id
        JOIN users u ON u.id = s.user_id
        LEFT JOIN users ru ON ru.id = p.received_by
        $whereSql
        ORDER BY p.payment_date DESC, p.id DESC";

// Execute query (with or without params)
$result = null;
if (count($params) > 0) {
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    $result = $conn->query($sql);
}

// Prepare CSV headers
$filenameDate = $filterDate !== '' ? $filterDate : date('Y-m-d');
$filename = "payments_{$filenameDate}.csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// Optional UTF-8 BOM for Excel compatibility
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Date',
    'Student Last Name',
    'Student First Name',
    'Username',
    'Item',
    'Amount Paid',
    'Method',
    'Reference #',
    'Received By'
]);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rbLast = trim($row['received_by_last'] ?? '');
        $rbFirst = trim($row['received_by_first'] ?? '');
        $rbUser = trim($row['received_by_username'] ?? '');
        $receivedBy = $rbUser;
        if ($receivedBy === '' && ($rbLast !== '' || $rbFirst !== '')) {
            $receivedBy = trim($rbLast . ', ' . $rbFirst);
        }
        fputcsv($out, [
            $row['payment_date'],
            $row['student_last'],
            $row['student_first'],
            $row['student_username'],
            $row['category_name'],
            number_format((float)$row['amount_paid'], 2, '.', ''),
            $row['payment_method'],
            $row['reference_number'],
            $receivedBy,
        ]);
    }
}

fclose($out);
exit;
